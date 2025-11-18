<?php
/**
 * Cancel a WTB Listing
 * POST /api/wtb/cancel.php
 */
require_once '../config.php';

handleCORS();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['error' => 'Method not allowed'], 405);
}

try {
    $data = getRequestData();

    // Validate required fields
    if (!isset($data['wtb_id']) || !isset($data['char_id'])) {
        sendJSON(['error' => 'Missing wtb_id or char_id'], 400);
    }

    $wtb_id = intval($data['wtb_id']);
    $char_id = intval($data['char_id']);

    $db = new Database();
    $conn = $db->getConnection();

    // Verify WTB listing exists and belongs to this character
    $stmt = $conn->prepare("
        SELECT id, buyer_char_id, status
        FROM marketplace_wtb
        WHERE id = :wtb_id
    ");
    $stmt->execute([':wtb_id' => $wtb_id]);
    $wtb = $stmt->fetch();

    if (!$wtb) {
        sendJSON(['error' => 'WTB listing not found'], 404);
    }

    if ($wtb['buyer_char_id'] != $char_id) {
        sendJSON(['error' => 'Unauthorized - this is not your WTB listing'], 403);
    }

    if ($wtb['status'] !== 'active') {
        sendJSON(['error' => 'Cannot cancel a WTB listing that is not active'], 400);
    }

    // Cancel the WTB listing
    $stmt = $conn->prepare("
        UPDATE marketplace_wtb
        SET status = 'cancelled'
        WHERE id = :wtb_id
    ");
    $stmt->execute([':wtb_id' => $wtb_id]);

    sendJSON([
        'success' => true,
        'message' => 'WTB listing cancelled successfully'
    ]);

} catch (Exception $e) {
    error_log("Cancel WTB error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to cancel WTB listing'], 500);
}
?>
