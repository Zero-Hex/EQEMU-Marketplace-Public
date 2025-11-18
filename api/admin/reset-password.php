<?php
/**
 * Reset User Password (Admin Only)
 * POST /api/admin/reset-password.php
 * Requires GM status (status >= 80)
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

    // Check GM status
    if (!isset($payload['status']) || $payload['status'] < 80) {
        sendJSON(['error' => 'Unauthorized. GM access required.'], 403);
    }

    $data = getRequestData();

    if (!isset($data['account_id']) || !isset($data['new_password'])) {
        sendJSON(['error' => 'Account ID and new password required'], 400);
    }

    $account_id = intval($data['account_id']);
    $new_password = $data['new_password'];

    // Validate password length
    if (strlen($new_password) < 6) {
        sendJSON(['error' => 'Password must be at least 6 characters'], 400);
    }

    $db = new Database();
    $conn = $db->getConnection();

    // Check if account exists and has marketplace profile
    $stmt = $conn->prepare("
        SELECT a.id, a.name, mp.account_id
        FROM account a
        LEFT JOIN marketplace_users mp ON a.id = mp.account_id
        WHERE a.id = :account_id
    ");
    $stmt->execute([':account_id' => $account_id]);
    $account = $stmt->fetch();

    if (!$account) {
        sendJSON(['error' => 'Account not found'], 404);
    }

    if (!$account['account_id']) {
        sendJSON(['error' => 'Account does not have a marketplace profile'], 404);
    }

    // Hash the new password
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    // Update the marketplace profile password
    $stmt = $conn->prepare("
        UPDATE marketplace_users
        SET password_hash = :password_hash
        WHERE account_id = :account_id
    ");
    $stmt->execute([
        ':password_hash' => $password_hash,
        ':account_id' => $account_id
    ]);

    sendJSON([
        'success' => true,
        'message' => 'Password reset successfully for account: ' . $account['name']
    ]);

} catch (Exception $e) {
    error_log("Admin password reset error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to reset password'], 500);
}
?>
