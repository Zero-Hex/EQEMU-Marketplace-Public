<?php
/**
 * Get Linked Accounts
 * GET /api/accounts/linked.php
 * Returns all accounts linked to the current marketplace profile
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

    // Get the marketplace user
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

    $marketplaceUserId = $marketplaceUser['id'];
    $activeAccountId = $marketplaceUser['active_account_id'] ?: $marketplaceUser['account_id'];

    // Get primary account info
    $stmt = $conn->prepare("SELECT id, name FROM account WHERE id = :account_id");
    $stmt->execute([':account_id' => $marketplaceUser['account_id']]);
    $primaryAccount = $stmt->fetch();

    $accounts = [
        [
            'account_id' => intval($primaryAccount['id']),
            'account_name' => $primaryAccount['name'],
            'is_primary' => true,
            'is_active' => (intval($activeAccountId) === intval($primaryAccount['id'])),
            'linked_date' => null
        ]
    ];

    // Get linked accounts
    $stmt = $conn->prepare("
        SELECT
            mla.account_id,
            mla.account_name,
            mla.linked_date,
            cd.name as validated_character
        FROM marketplace_linked_accounts mla
        LEFT JOIN character_data cd ON mla.validation_character_id = cd.id
        WHERE mla.marketplace_user_id = :marketplace_user_id
        ORDER BY mla.linked_date DESC
    ");
    $stmt->execute([':marketplace_user_id' => $marketplaceUserId]);
    $linkedAccounts = $stmt->fetchAll();

    foreach ($linkedAccounts as $linked) {
        $accounts[] = [
            'account_id' => intval($linked['account_id']),
            'account_name' => $linked['account_name'],
            'is_primary' => false,
            'is_active' => (intval($activeAccountId) === intval($linked['account_id'])),
            'linked_date' => $linked['linked_date'],
            'validated_character' => $linked['validated_character']
        ];
    }

    sendJSON([
        'success' => true,
        'accounts' => $accounts,
        'active_account_id' => intval($activeAccountId),
        'count' => count($accounts)
    ]);

} catch (Exception $e) {
    error_log("Get linked accounts error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendJSON(['error' => 'Failed to fetch linked accounts: ' . $e->getMessage()], 500);
}
?>
