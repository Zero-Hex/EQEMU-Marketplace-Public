<?php
/**
 * Remove Item from Watchlist
 * POST /api/watchlist/remove.php
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
    if (!isset($data['watchlist_id']) || !isset($data['char_id'])) {
        sendJSON(['error' => 'Missing watchlist_id or char_id'], 400);
    }

    $watchlist_id = intval($data['watchlist_id']);
    $char_id = intval($data['char_id']);

    $db = new Database();
    $conn = $db->getConnection();

    // Verify watchlist item exists and belongs to this character
    $stmt = $conn->prepare("
        SELECT id, char_id
        FROM marketplace_watchlist
        WHERE id = :watchlist_id
    ");
    $stmt->execute([':watchlist_id' => $watchlist_id]);
    $watchlist = $stmt->fetch();

    if (!$watchlist) {
        sendJSON(['error' => 'Watchlist item not found'], 404);
    }

    if ($watchlist['char_id'] != $char_id) {
        sendJSON(['error' => 'Unauthorized - this is not your watchlist item'], 403);
    }

    // Soft delete (set is_active = 0)
    $stmt = $conn->prepare("
        UPDATE marketplace_watchlist
        SET is_active = 0
        WHERE id = :watchlist_id
    ");
    $stmt->execute([':watchlist_id' => $watchlist_id]);

    sendJSON([
        'success' => true,
        'message' => 'Item removed from watchlist'
    ]);

} catch (Exception $e) {
    error_log("Remove watchlist error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to remove item from watchlist'], 500);
}
?>
