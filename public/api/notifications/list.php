<?php
/**
 * Get Notifications for a Character
 * GET /api/notifications/list.php?char_id=123
 */
require_once '../config.php';

handleCORS();

try {
    // Require char_id parameter
    if (!isset($_GET['char_id']) || !is_numeric($_GET['char_id'])) {
        sendJSON(['error' => 'Character ID is required'], 400);
    }

    $char_id = intval($_GET['char_id']);

    // Pagination parameters
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = isset($_GET['per_page']) && is_numeric($_GET['per_page']) ? intval($_GET['per_page']) : 20;

    // Cap per_page at reasonable limits
    $per_page = min(100, max(10, $per_page));

    $offset = ($page - 1) * $per_page;

    $db = new Database();
    $conn = $db->getConnection();

    // Optional filter by read status
    $where = ["n.char_id = :char_id"];
    $params = [':char_id' => $char_id];

    if (isset($_GET['unread_only']) && $_GET['unread_only'] == '1') {
        $where[] = "n.is_read = 0";
    }

    $whereClause = implode(' AND ', $where);

    // Count total notifications for pagination
    $countSql = "
        SELECT COUNT(*) as total
        FROM marketplace_notifications n
        WHERE $whereClause
    ";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = intval($countStmt->fetch()['total']);

    // Query notifications with pagination
    $sql = "
        SELECT
            n.id,
            n.char_id,
            n.notification_type,
            n.message,
            n.related_listing_id,
            n.related_wtb_id,
            n.related_item_id,
            n.is_read,
            n.created_date,
            i.name as item_name,
            i.icon as item_icon
        FROM marketplace_notifications n
        LEFT JOIN items i ON n.related_item_id = i.id
        WHERE $whereClause
        ORDER BY n.created_date DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($sql);
    // Bind all parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll();

    // Convert numeric fields and booleans
    foreach ($notifications as &$notif) {
        $notif['id'] = intval($notif['id']);
        $notif['char_id'] = intval($notif['char_id']);
        $notif['related_listing_id'] = $notif['related_listing_id'] ? intval($notif['related_listing_id']) : null;
        $notif['related_wtb_id'] = $notif['related_wtb_id'] ? intval($notif['related_wtb_id']) : null;
        $notif['related_item_id'] = $notif['related_item_id'] ? intval($notif['related_item_id']) : null;
        $notif['is_read'] = (bool)$notif['is_read'];
    }

    // Count unread notifications
    $stmt = $conn->prepare("
        SELECT COUNT(*) as unread_count
        FROM marketplace_notifications
        WHERE char_id = :char_id AND is_read = 0
    ");
    $stmt->execute([':char_id' => $char_id]);
    $unread = $stmt->fetch();

    sendJSON([
        'success' => true,
        'notifications' => $notifications,
        'count' => count($notifications),
        'unread_count' => intval($unread['unread_count']),
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $per_page),
            'has_more' => $page < ceil($totalCount / $per_page)
        ]
    ]);

} catch (Exception $e) {
    error_log("Notifications error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to fetch notifications'], 500);
}
?>
