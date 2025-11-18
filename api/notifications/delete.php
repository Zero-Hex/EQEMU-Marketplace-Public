<?php
/**
 * Delete Notifications
 * POST /api/notifications/delete.php
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

    // Can delete specific notification or all notifications
    if (isset($data['notification_id'])) {
        // Delete specific notification
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

        // Delete the notification
        $stmt = $conn->prepare("
            DELETE FROM marketplace_notifications
            WHERE id = :notification_id
        ");
        $stmt->execute([':notification_id' => $notification_id]);

        sendJSON([
            'success' => true,
            'message' => 'Notification deleted'
        ]);

    } else {
        // Delete all notifications for this character
        $stmt = $conn->prepare("
            DELETE FROM marketplace_notifications
            WHERE char_id = :char_id
        ");
        $stmt->execute([':char_id' => $char_id]);

        $count = $stmt->rowCount();

        sendJSON([
            'success' => true,
            'message' => "Deleted $count notifications",
            'count' => $count
        ]);
    }

} catch (Exception $e) {
    error_log("Delete notification error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to delete notifications'], 500);
}
?>
