<?php
/**
 * Get All Registered Marketplace Users (Admin Only)
 * GET /api/admin/all-users.php
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

    // Check GM status (80+ is GM level in EQEmu)
    if (!isset($payload['status']) || $payload['status'] < 80) {
        sendJSON(['error' => 'Unauthorized. GM access required.'], 403);
    }

    $db = new Database();
    $conn = $db->getConnection();

    // Build query based on filters
    $where = ["mp.registered_date IS NOT NULL"];
    $params = [];

    // Search filter
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $where[] = "a.name LIKE :search";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $whereClause = implode(' AND ', $where);

    // Query users
    $sql = "
        SELECT
            a.id as account_id,
            a.name as account_name,
            a.status,
            mp.email,
            mp.registered_date,
            mp.last_login,
            COUNT(DISTINCT cd.id) as character_count,
            COUNT(DISTINCT ml.id) as total_listings,
            COUNT(DISTINCT CASE WHEN ml.status = 'active' THEN ml.id END) as active_listings,
            COUNT(DISTINCT mw.id) as total_wtb_orders
        FROM account a
        INNER JOIN marketplace_users mp ON a.id = mp.account_id
        LEFT JOIN character_data cd ON cd.account_id = a.id
        LEFT JOIN marketplace_listings ml ON ml.seller_char_id = cd.id
        LEFT JOIN marketplace_wtb mw ON mw.buyer_char_id = cd.id
        WHERE $whereClause
        GROUP BY a.id, a.name, a.status, mp.email, mp.registered_date, mp.last_login
        ORDER BY mp.registered_date DESC
        LIMIT 200
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    // Convert numeric fields
    foreach ($users as &$user) {
        $user['account_id'] = intval($user['account_id']);
        $user['status'] = intval($user['status']);
        $user['character_count'] = intval($user['character_count']);
        $user['total_listings'] = intval($user['total_listings']);
        $user['active_listings'] = intval($user['active_listings']);
        $user['total_wtb_orders'] = intval($user['total_wtb_orders']);
    }

    sendJSON([
        'success' => true,
        'users' => $users,
        'count' => count($users)
    ]);

} catch (Exception $e) {
    error_log("Admin users error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendJSON(['error' => 'Failed to fetch users: ' . $e->getMessage()], 500);
}
?>
