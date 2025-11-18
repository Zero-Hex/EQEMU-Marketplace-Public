<?php
/**
 * Get All Marketplace Listings (Admin Only)
 * GET /api/admin/all-listings.php
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
    $where = ["ml.status IN ('active', 'sold', 'cancelled')"];
    $params = [];

    // Search filter
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $where[] = "(i.name LIKE :search1 OR cd.name LIKE :search2)";
        $params[':search1'] = '%' . $_GET['search'] . '%';
        $params[':search2'] = '%' . $_GET['search'] . '%';
    }

    // Status filter
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $where[] = "ml.status = :status";
        $params[':status'] = $_GET['status'];
    }

    $whereClause = implode(' AND ', $where);

    // Query listings with payment status from transactions
    $sql = "
        SELECT
            ml.id,
            ml.seller_char_id,
            cd.name as seller_name,
            ml.buyer_char_id,
            buyer.name as buyer_name,
            ml.item_id,
            i.name as item_name,
            i.icon,
            ml.quantity,
            ml.price_copper,
            ml.status,
            ml.listed_date,
            ml.purchased_date,
            mt.payment_status,
            mt.id as transaction_id
        FROM marketplace_listings ml
        JOIN character_data cd ON ml.seller_char_id = cd.id
        LEFT JOIN character_data buyer ON ml.buyer_char_id = buyer.id
        LEFT JOIN marketplace_transactions mt ON ml.id = mt.listing_id
        JOIN items i ON ml.item_id = i.id
        WHERE $whereClause
        ORDER BY ml.listed_date DESC
        LIMIT 200
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $listings = $stmt->fetchAll();

    // Convert numeric fields
    foreach ($listings as &$listing) {
        $listing['id'] = intval($listing['id']);
        $listing['seller_char_id'] = intval($listing['seller_char_id']);
        $listing['buyer_char_id'] = $listing['buyer_char_id'] ? intval($listing['buyer_char_id']) : null;
        $listing['item_id'] = intval($listing['item_id']);
        $listing['quantity'] = intval($listing['quantity']);
        $listing['price_copper'] = intval($listing['price_copper']);
        $listing['transaction_id'] = $listing['transaction_id'] ? intval($listing['transaction_id']) : null;
    }

    sendJSON([
        'success' => true,
        'listings' => $listings,
        'count' => count($listings)
    ]);

} catch (Exception $e) {
    error_log("Admin listings error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendJSON(['error' => 'Failed to fetch listings: ' . $e->getMessage()], 500);
}
?>
