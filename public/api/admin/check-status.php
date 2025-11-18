<?php
/**
 * Check User GM Status
 * GET /api/admin/check-status.php
 * Returns the current user's account status level
 */
require_once '../config.php';

handleCORS();

try {
    // Check authentication
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

    if (!$token) {
        sendJSON(['error' => 'Authorization required', 'authenticated' => false], 401);
    }

    $payload = JWT::decode($token);

    if (!$payload || !isset($payload['account_id'])) {
        sendJSON(['error' => 'Invalid token', 'authenticated' => false], 401);
    }

    // Get account info
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT id, name, status, gm FROM account WHERE id = :account_id");
    $stmt->execute([':account_id' => $payload['account_id']]);
    $account = $stmt->fetch();

    if (!$account) {
        sendJSON(['error' => 'Account not found'], 404);
    }

    sendJSON([
        'success' => true,
        'authenticated' => true,
        'account' => [
            'id' => intval($account['id']),
            'name' => $account['name'],
            'status' => intval($account['status']),
            'gm' => intval($account['gm']),
            'is_gm' => intval($account['status']) >= 80
        ],
        'message' => intval($account['status']) >= 80
            ? 'You have GM access (status: ' . $account['status'] . ')'
            : 'You do not have GM access. Required: 80+, Your status: ' . $account['status']
    ]);

} catch (Exception $e) {
    error_log("Check status error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to check status: ' . $e->getMessage()], 500);
}
?>
