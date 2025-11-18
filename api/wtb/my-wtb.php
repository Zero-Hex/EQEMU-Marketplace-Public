<?php
/**
 * Get My WTB Listings
 * GET /api/wtb/my-wtb.php?char_id=123
 */
require_once '../config.php';

handleCORS();

try {
    // Require char_id parameter
    if (!isset($_GET['char_id']) || !is_numeric($_GET['char_id'])) {
        sendJSON(['error' => 'Character ID is required'], 400);
    }

    $char_id = intval($_GET['char_id']);

    $db = new Database();
    $conn = $db->getConnection();

    // Optional status filter (default: active only)
    $status = isset($_GET['status']) ? $_GET['status'] : 'active';
    $validStatuses = ['active', 'fulfilled', 'expired', 'cancelled', 'all'];

    if (!in_array($status, $validStatuses)) {
        $status = 'active';
    }

    // Build WHERE clause
    $where = ["w.buyer_char_id = :char_id"];
    $params = [':char_id' => $char_id];

    if ($status !== 'all') {
        $where[] = "w.status = :status";
        $params[':status'] = $status;
    }

    $whereClause = implode(' AND ', $where);

    // Query WTB listings
    $sql = "
        SELECT
            w.id,
            w.buyer_char_id,
            w.item_id,
            i.name as item_name,
            i.icon,
            w.quantity_wanted,
            w.quantity_fulfilled,
            (w.quantity_wanted - w.quantity_fulfilled) as quantity_remaining,
            w.price_per_unit_copper,
            w.notes,
            w.created_date,
            w.expires_date,
            w.status
        FROM marketplace_wtb w
        JOIN items i ON w.item_id = i.id
        WHERE $whereClause
        ORDER BY w.created_date DESC
        LIMIT 100
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $listings = $stmt->fetchAll();

    // Convert numeric fields
    foreach ($listings as &$listing) {
        $listing['id'] = intval($listing['id']);
        $listing['buyer_char_id'] = intval($listing['buyer_char_id']);
        $listing['item_id'] = intval($listing['item_id']);
        $listing['quantity_wanted'] = intval($listing['quantity_wanted']);
        $listing['quantity_fulfilled'] = intval($listing['quantity_fulfilled']);
        $listing['quantity_remaining'] = intval($listing['quantity_remaining']);
        $listing['price_per_unit_copper'] = intval($listing['price_per_unit_copper']);
    }

    sendJSON([
        'success' => true,
        'wtb_listings' => $listings,
        'count' => count($listings)
    ]);

} catch (Exception $e) {
    error_log("My WTB error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to fetch WTB listings'], 500);
}
?>
