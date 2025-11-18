<?php
/**
 * List Active WTB (Want to Buy) Listings
 * GET /api/wtb/list.php
 */
require_once '../config.php';

handleCORS();

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Build query based on filters
    $where = ["w.status = 'active'", "w.quantity_fulfilled < w.quantity_wanted"];
    $params = [];

    // Search filter
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $where[] = "i.name LIKE :search";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    // Price filters (per unit)
    if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
        $where[] = "w.price_per_unit_copper >= :min_price";
        $params[':min_price'] = intval($_GET['min_price']);
    }

    if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
        $where[] = "w.price_per_unit_copper <= :max_price";
        $params[':max_price'] = intval($_GET['max_price']);
    }

    // Item type filter
    if (isset($_GET['item_type']) && is_numeric($_GET['item_type'])) {
        $where[] = "i.itemtype = :item_type";
        $params[':item_type'] = intval($_GET['item_type']);
    }

    // Item class filter (bitmask check)
    if (isset($_GET['item_class']) && is_numeric($_GET['item_class'])) {
        $classValue = intval($_GET['item_class']);
        $where[] = "(i.classes & :item_class) != 0";
        $params[':item_class'] = $classValue;
    }

    // Specific item filter
    if (isset($_GET['item_id']) && is_numeric($_GET['item_id'])) {
        $where[] = "w.item_id = :item_id";
        $params[':item_id'] = intval($_GET['item_id']);
    }

    $whereClause = implode(' AND ', $where);

    // Sort order
    $orderBy = "w.created_date DESC";
    if (isset($_GET['sort_by'])) {
        switch ($_GET['sort_by']) {
            case 'oldest':
                $orderBy = "w.created_date ASC";
                break;
            case 'price-low':
                $orderBy = "w.price_per_unit_copper ASC";
                break;
            case 'price-high':
                $orderBy = "w.price_per_unit_copper DESC";
                break;
            case 'quantity':
                $orderBy = "(w.quantity_wanted - w.quantity_fulfilled) DESC";
                break;
            default:
                $orderBy = "w.created_date DESC";
        }
    }

    // Query WTB listings
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
            w.created_date,
            w.expires_date
        FROM marketplace_wtb w
        JOIN character_data cd ON w.buyer_char_id = cd.id
        JOIN items i ON w.item_id = i.id
        WHERE $whereClause
        ORDER BY $orderBy
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
    error_log("WTB listings error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to fetch WTB listings'], 500);
}
?>
