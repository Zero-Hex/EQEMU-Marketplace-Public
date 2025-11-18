<?php
/**
 * Get Seller Earnings
 * Returns the total unclaimed earnings for the authenticated user
 */

require_once '../config.php';
handleCORS();

// Require authentication
$user = requireAuth();

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get account_id from authenticated user
    $account_id = $user['account_id'];

    // Get marketplace user to find all linked accounts
    $stmt = $conn->prepare("SELECT id, account_id FROM marketplace_users WHERE account_id = :account_id");
    $stmt->execute([':account_id' => $account_id]);
    $marketplaceUser = $stmt->fetch();

    // Collect all account IDs (primary + linked)
    $accountIds = [$account_id];

    if ($marketplaceUser) {
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
    }

    // Get all characters belonging to these accounts
    $accPlaceholders = str_repeat('?,', count($accountIds) - 1) . '?';
    $char_query = "SELECT id, name FROM character_data WHERE account_id IN ($accPlaceholders)";
    $char_stmt = $conn->prepare($char_query);
    $char_stmt->execute($accountIds);
    $characters = $char_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($characters)) {
        sendJSON([
            'success' => true,
            'total_unclaimed_copper' => 0,
            'total_unclaimed_platinum' => 0,
            'earnings' => [],
            'characters' => []
        ]);
        exit;
    }

    $char_ids = array_column($characters, 'id');
    $placeholders = implode(',', array_fill(0, count($char_ids), '?'));

    // Get unclaimed earnings for all characters
    $earnings_query = "
        SELECT
            e.id,
            e.seller_char_id,
            e.amount_copper,
            e.earned_date,
            e.source_listing_id,
            e.notes,
            c.name as character_name,
            i.Name as item_name,
            l.quantity,
            l.buyer_char_id,
            cb.name as buyer_name
        FROM marketplace_seller_earnings e
        JOIN character_data c ON e.seller_char_id = c.id
        LEFT JOIN marketplace_listings l ON e.source_listing_id = l.id
        LEFT JOIN items i ON l.item_id = i.id
        LEFT JOIN character_data cb ON l.buyer_char_id = cb.id
        WHERE e.seller_char_id IN ($placeholders)
        AND e.claimed = FALSE
        ORDER BY e.earned_date DESC
    ";

    try {
        $earnings_stmt = $conn->prepare($earnings_query);
        $earnings_stmt->execute($char_ids);
        $earnings = $earnings_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Check if table doesn't exist
        if (strpos($e->getMessage(), 'marketplace_seller_earnings') !== false) {
            http_response_code(500);
            sendJSON([
                'success' => false,
                'message' => 'Database not initialized. Please run migration: api/migrations/002_add_seller_earnings.sql',
                'error' => 'marketplace_seller_earnings table does not exist'
            ], 500);
            exit;
        }
        throw $e;
    }

    // Calculate total unclaimed
    $total_copper = 0;
    foreach ($earnings as $earning) {
        $total_copper += $earning['amount_copper'];
    }

    $total_platinum = floor($total_copper / 1000);

    // Format earnings for response
    $formatted_earnings = [];
    foreach ($earnings as $earning) {
        $formatted_earnings[] = [
            'id' => $earning['id'],
            'character_name' => $earning['character_name'],
            'character_id' => $earning['seller_char_id'],
            'amount_copper' => $earning['amount_copper'],
            'amount_platinum' => floor($earning['amount_copper'] / 1000),
            'earned_date' => $earning['earned_date'],
            'item_name' => $earning['item_name'],
            'quantity' => $earning['quantity'],
            'buyer_name' => $earning['buyer_name'],
            'notes' => $earning['notes']
        ];
    }

    sendJSON([
        'success' => true,
        'total_unclaimed_copper' => $total_copper,
        'total_unclaimed_platinum' => $total_platinum,
        'earnings_count' => count($earnings),
        'earnings' => $formatted_earnings,
        'characters' => $characters
    ]);

} catch (PDOException $e) {
    error_log("Database error in earnings/get.php: " . $e->getMessage());
    http_response_code(500);
    sendJSON(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    error_log("Error in earnings/get.php: " . $e->getMessage());
    http_response_code(500);
    sendJSON(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
}
