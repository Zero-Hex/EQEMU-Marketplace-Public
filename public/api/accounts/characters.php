<?php
/**
 * Get Characters for Active Account
 * GET /api/accounts/characters.php
 * Returns characters for the currently active account
 */
require_once '../config.php';

handleCORS();

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

    $db = new Database();
    $conn = $db->getConnection();

    // Get the marketplace user and active account
    $stmt = $conn->prepare("
        SELECT id, account_id, active_account_id
        FROM marketplace_users
        WHERE account_id = :account_id
    ");
    $stmt->execute([':account_id' => $payload['account_id']]);
    $marketplaceUser = $stmt->fetch();

    if (!$marketplaceUser) {
        sendJSON(['error' => 'Marketplace profile not found'], 404);
    }

    // Get all account IDs (primary + linked accounts)
    $accountIds = [$marketplaceUser['account_id']];
    $accountNames = [];

    // Get primary account name
    $stmt = $conn->prepare("SELECT id, name FROM account WHERE id = :account_id");
    $stmt->execute([':account_id' => $marketplaceUser['account_id']]);
    $primaryAccount = $stmt->fetch();
    if ($primaryAccount) {
        $accountNames[intval($primaryAccount['id'])] = $primaryAccount['name'];
    }

    // Get linked account IDs and names
    $stmt = $conn->prepare("
        SELECT account_id, account_name
        FROM marketplace_linked_accounts
        WHERE marketplace_user_id = :marketplace_user_id
    ");
    $stmt->execute([':marketplace_user_id' => $marketplaceUser['id']]);
    $linkedAccounts = $stmt->fetchAll();

    foreach ($linkedAccounts as $linkedAccount) {
        $accountIds[] = intval($linkedAccount['account_id']);
        $accountNames[intval($linkedAccount['account_id'])] = $linkedAccount['account_name'];
    }

    // Fetch characters for ALL linked accounts (exclude deleted characters)
    $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
    $stmt = $conn->prepare("
        SELECT id, name, level, class, account_id
        FROM character_data
        WHERE account_id IN ($placeholders)
        AND deleted_at IS NULL
        ORDER BY name ASC
    ");
    $stmt->execute($accountIds);
    $characters = $stmt->fetchAll();

    // Convert numeric fields and add account name
    foreach ($characters as &$character) {
        $character['id'] = intval($character['id']);
        $character['level'] = intval($character['level']);
        $character['class'] = intval($character['class']);
        $character['account_id'] = intval($character['account_id']);
        $character['account_name'] = $accountNames[$character['account_id']] ?? 'Unknown';
    }

    sendJSON([
        'success' => true,
        'characters' => $characters,
        'count' => count($characters)
    ]);

} catch (Exception $e) {
    error_log("Get characters error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendJSON(['error' => 'Failed to fetch characters: ' . $e->getMessage()], 500);
}
?>
