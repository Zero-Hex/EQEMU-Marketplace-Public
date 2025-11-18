<?php
/**
 * Delete WTB Order (Admin Only)
 * POST /api/admin/delete-wtb.php
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

    if (!isset($data['wtb_id'])) {
        sendJSON(['error' => 'WTB ID required'], 400);
    }

    $db = new Database();
    $conn = $db->getConnection();

    $wtb_id = intval($data['wtb_id']);

    // Get WTB details first
    $stmt = $conn->prepare("
        SELECT w.*, cd.name as buyer_name
        FROM marketplace_wtb w
        JOIN character_data cd ON w.buyer_char_id = cd.id
        WHERE w.id = :wtb_id
    ");
    $stmt->execute([':wtb_id' => $wtb_id]);
    $wtb = $stmt->fetch();

    if (!$wtb) {
        sendJSON(['error' => 'WTB order not found'], 404);
    }

    // Cancel the WTB order
    $stmt = $conn->prepare("
        UPDATE marketplace_wtb
        SET status = 'cancelled'
        WHERE id = :wtb_id
    ");
    $stmt->execute([':wtb_id' => $wtb_id]);

    sendJSON([
        'success' => true,
        'message' => 'WTB order cancelled successfully'
    ]);

} catch (Exception $e) {
    error_log("Admin delete WTB error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to delete WTB order'], 500);
}
?>
