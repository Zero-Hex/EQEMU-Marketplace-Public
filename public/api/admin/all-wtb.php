<?php
/**
 * Get All WTB Orders (Admin Only)
 * GET /api/admin/all-wtb.php
 * Requires GM status (status >= 80)
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

    // Check GM status
    if (!isset($payload['status']) || $payload['status'] < 80) {
        sendJSON(['error' => 'Unauthorized. GM access required.'], 403);
    }

    $db = new Database();
    $conn = $db->getConnection();

    // Build query based on filters
    $where = ["w.status IN ('active', 'fulfilled', 'expired', 'cancelled')"];
    $params = [];

    // Search filter
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $where[] = "(i.name LIKE :search1 OR cd.name LIKE :search2)";
        $params[':search1'] = '%' . $_GET['search'] . '%';
        $params[':search2'] = '%' . $_GET['search'] . '%';
    }

    // Status filter
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $where[] = "w.status = :status";
        $params[':status'] = $_GET['status'];
    }

    $whereClause = implode(' AND ', $where);

    // Query WTB orders
    $sql = "
        SELECT
            w.id,
            w.buyer_char_id,
            cd.name as buyer_name,
            w.item_id,
            i.name as item_name,
            i.icon,
            w.quantity_wanted,
            w.quantity_fulfilled,
            (w.quantity_wanted - w.quantity_fulfilled) as quantity_remaining,
            w.price_per_unit_copper,
            w.notes,
            w.status,
            w.created_date,
            w.expires_date
        FROM marketplace_wtb w
        JOIN character_data cd ON w.buyer_char_id = cd.id
        JOIN items i ON w.item_id = i.id
        WHERE $whereClause
        ORDER BY w.created_date DESC
        LIMIT 200
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Convert numeric fields
    foreach ($orders as &$order) {
        $order['id'] = intval($order['id']);
        $order['buyer_char_id'] = intval($order['buyer_char_id']);
        $order['item_id'] = intval($order['item_id']);
        $order['quantity_wanted'] = intval($order['quantity_wanted']);
        $order['quantity_fulfilled'] = intval($order['quantity_fulfilled']);
        $order['quantity_remaining'] = intval($order['quantity_remaining']);
        $order['price_per_unit_copper'] = intval($order['price_per_unit_copper']);
    }

    sendJSON([
        'success' => true,
        'wtb_orders' => $orders,
        'count' => count($orders)
    ]);

} catch (Exception $e) {
    error_log("Admin WTB error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to fetch WTB orders'], 500);
}
?>
