<?php
/**
 * Switch Active Account
 * POST /api/accounts/switch.php
 * Switches the active account for marketplace operations
 */
require_once '../config.php';

handleCORS();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['error' => 'Method not allowed'], 405);
}

try {
    // Check authentication
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

    if (!$token) {
        sendJSON(['error' => 'Authorization required'], 401);
    }

    $payload = JWT::decode($token);

    if (!$payload || !isset($payload['account_id'])) {
        sendJSON(['error' => 'Invalid token'], 401);
    }

    $data = getRequestData();

    if (!isset($data['account_id'])) {
        sendJSON(['error' => 'Account ID required'], 400);
    }

    $targetAccountId = intval($data['account_id']);

    $db = new Database();
    $conn = $db->getConnection();

    // Get the marketplace user
    $stmt = $conn->prepare("
        SELECT id, account_id
        FROM marketplace_users
        WHERE account_id = :account_id
    ");
    $stmt->execute([':account_id' => $payload['account_id']]);
    $marketplaceUser = $stmt->fetch();

    if (!$marketplaceUser) {
        sendJSON(['error' => 'Marketplace profile not found'], 404);
    }

    $marketplaceUserId = $marketplaceUser['id'];
    $primaryAccountId = intval($marketplaceUser['account_id']);

    // Verify the target account is either the primary or a linked account
    $isValid = false;

    if ($targetAccountId === $primaryAccountId) {
        $isValid = true;
    } else {
        $stmt = $conn->prepare("
            SELECT id
            FROM marketplace_linked_accounts
            WHERE marketplace_user_id = :marketplace_user_id
            AND account_id = :account_id
        ");
        $stmt->execute([
            ':marketplace_user_id' => $marketplaceUserId,
            ':account_id' => $targetAccountId
        ]);
        if ($stmt->fetch()) {
            $isValid = true;
        }
    }

    if (!$isValid) {
        sendJSON(['error' => 'Invalid account. Account must be your primary account or a linked account.'], 400);
    }

    // Update the active account
    $stmt = $conn->prepare("
        UPDATE marketplace_users
        SET active_account_id = :active_account_id
        WHERE id = :marketplace_user_id
    ");
    $stmt->execute([
        ':active_account_id' => $targetAccountId,
        ':marketplace_user_id' => $marketplaceUserId
    ]);

    // Get account name
    $stmt = $conn->prepare("SELECT name FROM account WHERE id = :account_id");
    $stmt->execute([':account_id' => $targetAccountId]);
    $account = $stmt->fetch();

    sendJSON([
        'success' => true,
        'message' => "Switched to account: {$account['name']}",
        'active_account_id' => $targetAccountId,
        'active_account_name' => $account['name']
    ]);

} catch (Exception $e) {
    error_log("Switch account error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendJSON(['error' => 'Failed to switch account: ' . $e->getMessage()], 500);
}
?>
