<?php
require_once '../config.php';

handleCORS();

// Require authentication
$user = requireAuth();

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get filter parameters
    $filterAccountId = isset($_GET['account_id']) ? intval($_GET['account_id']) : null;

    // Get marketplace user to find all linked accounts
    $stmt = $conn->prepare("SELECT id, account_id FROM marketplace_users WHERE account_id = :account_id");
    $stmt->execute([':account_id' => $user['account_id']]);
    $marketplaceUser = $stmt->fetch();

    if (!$marketplaceUser) {
        sendJSON(['success' => true, 'purchases' => [], 'count' => 0]);
    }

    // Collect all account IDs (primary + linked)
    $accountIds = [intval($marketplaceUser['account_id'])];

    $stmt = $conn->prepare("
        SELECT account_id
        FROM marketplace_linked_accounts
        WHERE marketplace_user_id = :marketplace_user_id
    ");
    $stmt->execute([':marketplace_user_id' => $marketplaceUser['id']]);
    $linkedAccounts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($linkedAccounts as $accId) {
        $accountIds[] = intval($accId);
    }

    // If filtering by specific account, verify it belongs to user
    if ($filterAccountId) {
        if (!in_array($filterAccountId, $accountIds)) {
            sendJSON(['error' => 'Invalid account filter'], 403);
        }
        $accountIds = [$filterAccountId];
    }

    // Get all characters for these accounts
    $accPlaceholders = str_repeat('?,', count($accountIds) - 1) . '?';
    $stmt = $conn->prepare("
        SELECT id, account_id
        FROM character_data
        WHERE account_id IN ($accPlaceholders)
    ");
    $stmt->execute($accountIds);
    $characters = $stmt->fetchAll();

    if (empty($characters)) {
        sendJSON(['success' => true, 'purchases' => [], 'count' => 0]);
    }

    $char_ids = [];
    $charToAccount = [];
    foreach ($characters as $char) {
        $char_ids[] = intval($char['id']);
        $charToAccount[intval($char['id'])] = intval($char['account_id']);
    }

    // Get purchase history for all user's characters
    $placeholders = str_repeat('?,', count($char_ids) - 1) . '?';

    $sql = "
        SELECT
            mt.id,
            mt.listing_id,
            mt.seller_char_id,
            seller.name as seller_name,
            mt.buyer_char_id,
            buyer.name as buyer_name,
            mt.item_id,
            i.name as item_name,
            i.icon,
            mt.price_copper,
            mt.transaction_date
        FROM marketplace_transactions mt
        JOIN character_data seller ON mt.seller_char_id = seller.id
        JOIN character_data buyer ON mt.buyer_char_id = buyer.id
        JOIN items i ON mt.item_id = i.id
        WHERE mt.buyer_char_id IN ($placeholders)
        ORDER BY mt.transaction_date DESC
        LIMIT 100
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($char_ids);
    $purchases = $stmt->fetchAll();
    
    // Convert numeric fields and add account info
    foreach ($purchases as &$purchase) {
        $purchase['id'] = intval($purchase['id']);
        $purchase['listing_id'] = intval($purchase['listing_id']);
        $purchase['seller_char_id'] = intval($purchase['seller_char_id']);
        $purchase['buyer_char_id'] = intval($purchase['buyer_char_id']);
        $purchase['item_id'] = intval($purchase['item_id']);
        $purchase['price_copper'] = intval($purchase['price_copper']);
        $purchase['buyer_account_id'] = $charToAccount[$purchase['buyer_char_id']] ?? null;
    }
    
    sendJSON([
        'success' => true,
        'purchases' => $purchases,
        'count' => count($purchases)
    ]);
    
} catch (Exception $e) {
    error_log("Purchase history error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to fetch purchase history'], 500);
}
?>
