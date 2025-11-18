<?php
/**
 * Get Marketplace Statistics (Admin Only)
 * GET /api/admin/stats.php
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

    // Get total listings
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM marketplace_listings WHERE status = 'active'");
    $stmt->execute();
    $totalListings = intval($stmt->fetch()['count']);

    // Get total WTB orders
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM marketplace_wtb WHERE status = 'active'");
    $stmt->execute();
    $totalWTB = intval($stmt->fetch()['count']);

    // Get total transactions
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM marketplace_transactions");
    $stmt->execute();
    $totalTransactions = intval($stmt->fetch()['count']);

    // Get active users (users who have created listings or purchased in last 30 days)
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT seller_char_id) as count
        FROM marketplace_listings
        WHERE listed_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $activeUsers = intval($stmt->fetch()['count']);

    sendJSON([
        'success' => true,
        'stats' => [
            'total_listings' => $totalListings,
            'total_wtb' => $totalWTB,
            'total_transactions' => $totalTransactions,
            'active_users' => $activeUsers
        ]
    ]);

} catch (Exception $e) {
    error_log("Admin stats error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to fetch statistics'], 500);
}
?>
