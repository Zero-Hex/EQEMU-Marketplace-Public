<?php
/**
 * Link Additional Account
 * POST /api/accounts/link.php
 * Links an additional game account to the current marketplace profile
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

    if (!isset($data['account_name']) || !isset($data['character_id'])) {
        sendJSON(['error' => 'Account name and character ID required'], 400);
    }

    $accountName = trim($data['account_name']);
    $characterId = intval($data['character_id']);

    $db = new Database();
    $conn = $db->getConnection();

    // Get the marketplace user ID
    $stmt = $conn->prepare("SELECT id, account_id FROM marketplace_users WHERE account_id = :account_id");
    $stmt->execute([':account_id' => $payload['account_id']]);
    $marketplaceUser = $stmt->fetch();

    if (!$marketplaceUser) {
        sendJSON(['error' => 'Marketplace profile not found'], 404);
    }

    $marketplaceUserId = $marketplaceUser['id'];

    // Validate the account exists
    $stmt = $conn->prepare("SELECT id, name FROM account WHERE name = :name");
    $stmt->execute([':name' => $accountName]);
    $account = $stmt->fetch();

    if (!$account) {
        sendJSON(['error' => 'Account not found'], 404);
    }

    $accountId = intval($account['id']);

    // Check if this is the same as the primary account
    if ($accountId === $payload['account_id']) {
        sendJSON(['error' => 'This is your primary account. Cannot link to itself.'], 400);
    }

    // Validate the character exists and belongs to this account
    $stmt = $conn->prepare("
        SELECT id, name, account_id
        FROM character_data
        WHERE id = :char_id
    ");
    $stmt->execute([':char_id' => $characterId]);
    $character = $stmt->fetch();

    if (!$character) {
        sendJSON(['error' => 'Character not found'], 404);
    }

    if (intval($character['account_id']) !== $accountId) {
        sendJSON(['error' => 'Character does not belong to the specified account'], 400);
    }

    // Check if account is already linked to this marketplace user
    $stmt = $conn->prepare("
        SELECT id FROM marketplace_linked_accounts
        WHERE marketplace_user_id = :marketplace_user_id AND account_id = :account_id
    ");
    $stmt->execute([
        ':marketplace_user_id' => $marketplaceUserId,
        ':account_id' => $accountId
    ]);
    if ($stmt->fetch()) {
        sendJSON(['error' => 'Account is already linked to your profile'], 400);
    }

    // Check if account is linked to a different marketplace user
    $stmt = $conn->prepare("
        SELECT id FROM marketplace_linked_accounts
        WHERE account_id = :account_id
    ");
    $stmt->execute([':account_id' => $accountId]);
    if ($stmt->fetch()) {
        sendJSON(['error' => 'Account is already linked to another marketplace profile'], 400);
    }

    // Check if account has its own marketplace profile
    $stmt = $conn->prepare("SELECT id FROM marketplace_users WHERE account_id = :account_id");
    $stmt->execute([':account_id' => $accountId]);
    if ($stmt->fetch()) {
        sendJSON(['error' => 'Account already has its own marketplace profile. Cannot link.'], 400);
    }

    // Link the account
    $stmt = $conn->prepare("
        INSERT INTO marketplace_linked_accounts
        (marketplace_user_id, account_id, account_name, validation_character_id)
        VALUES (:marketplace_user_id, :account_id, :account_name, :validation_character_id)
    ");
    $stmt->execute([
        ':marketplace_user_id' => $marketplaceUserId,
        ':account_id' => $accountId,
        ':account_name' => $account['name'],
        ':validation_character_id' => $characterId
    ]);

    sendJSON([
        'success' => true,
        'message' => "Account '{$account['name']}' has been linked to your marketplace profile",
        'linked_account' => [
            'account_id' => $accountId,
            'account_name' => $account['name'],
            'validated_with_character' => $character['name'],
            'linked_date' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    error_log("Link account error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendJSON(['error' => 'Failed to link account: ' . $e->getMessage()], 500);
}
?>
