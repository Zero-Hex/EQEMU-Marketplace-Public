<?php
/**
 * Mark Notifications as Read
 * POST /api/notifications/mark-read.php
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
    if (!isset($data['char_id'])) {
        sendJSON(['error' => 'Character ID is required'], 400);
    }

    $char_id = intval($data['char_id']);

    $db = new Database();
    $conn = $db->getConnection();

    // Can mark specific notification or all notifications
    if (isset($data['notification_id'])) {
        // Mark specific notification as read
        $notification_id = intval($data['notification_id']);

        // Verify notification belongs to this character
        $stmt = $conn->prepare("
            SELECT id, char_id
            FROM marketplace_notifications
            WHERE id = :notification_id
        ");
        $stmt->execute([':notification_id' => $notification_id]);
        $notification = $stmt->fetch();

        if (!$notification) {
            sendJSON(['error' => 'Notification not found'], 404);
        }

        if ($notification['char_id'] != $char_id) {
            sendJSON(['error' => 'Unauthorized'], 403);
        }

        // Mark as read
        $stmt = $conn->prepare("
            UPDATE marketplace_notifications
            SET is_read = 1
            WHERE id = :notification_id
        ");
        $stmt->execute([':notification_id' => $notification_id]);

        sendJSON([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);

    } else {
        // Mark all notifications as read for this character
        $stmt = $conn->prepare("
            UPDATE marketplace_notifications
            SET is_read = 1
            WHERE char_id = :char_id AND is_read = 0
        ");
        $stmt->execute([':char_id' => $char_id]);

        $count = $stmt->rowCount();

        sendJSON([
            'success' => true,
            'message' => "Marked $count notifications as read",
            'count' => $count
        ]);
    }

} catch (Exception $e) {
    error_log("Mark read error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to mark notifications as read'], 500);
}
?>
