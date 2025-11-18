<?php
require_once '../config.php';

handleCORS();

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Build query based on filters
    $where = ["ml.status = 'active'"];
    $params = [];
    
    // Search filter
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $where[] = "i.name LIKE :search";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    // Price filters
    if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
        $where[] = "ml.price_copper >= :min_price";
        $params[':min_price'] = intval($_GET['min_price']);
    }

    if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
        $where[] = "ml.price_copper <= :max_price";
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

    // Item stat filters
    if (isset($_GET['min_ac']) && is_numeric($_GET['min_ac'])) {
        $where[] = "i.ac >= :min_ac";
        $params[':min_ac'] = intval($_GET['min_ac']);
    }

    if (isset($_GET['min_hp']) && is_numeric($_GET['min_hp'])) {
        $where[] = "i.hp >= :min_hp";
        $params[':min_hp'] = intval($_GET['min_hp']);
    }

    if (isset($_GET['min_mana']) && is_numeric($_GET['min_mana'])) {
        $where[] = "i.mana >= :min_mana";
        $params[':min_mana'] = intval($_GET['min_mana']);
    }

    if (isset($_GET['min_str']) && is_numeric($_GET['min_str'])) {
        $where[] = "i.astr >= :min_str";
        $params[':min_str'] = intval($_GET['min_str']);
    }

    if (isset($_GET['min_dex']) && is_numeric($_GET['min_dex'])) {
        $where[] = "i.adex >= :min_dex";
        $params[':min_dex'] = intval($_GET['min_dex']);
    }

    if (isset($_GET['min_sta']) && is_numeric($_GET['min_sta'])) {
        $where[] = "i.asta >= :min_sta";
        $params[':min_sta'] = intval($_GET['min_sta']);
    }

    if (isset($_GET['min_agi']) && is_numeric($_GET['min_agi'])) {
        $where[] = "i.aagi >= :min_agi";
        $params[':min_agi'] = intval($_GET['min_agi']);
    }

    if (isset($_GET['min_int']) && is_numeric($_GET['min_int'])) {
        $where[] = "i.aint >= :min_int";
        $params[':min_int'] = intval($_GET['min_int']);
    }

    if (isset($_GET['min_wis']) && is_numeric($_GET['min_wis'])) {
        $where[] = "i.awis >= :min_wis";
        $params[':min_wis'] = intval($_GET['min_wis']);
    }

    if (isset($_GET['min_cha']) && is_numeric($_GET['min_cha'])) {
        $where[] = "i.acha >= :min_cha";
        $params[':min_cha'] = intval($_GET['min_cha']);
    }

    // Resistance filters
    if (isset($_GET['min_fr']) && is_numeric($_GET['min_fr'])) {
        $where[] = "i.fr >= :min_fr";
        $params[':min_fr'] = intval($_GET['min_fr']);
    }

    if (isset($_GET['min_cr']) && is_numeric($_GET['min_cr'])) {
        $where[] = "i.cr >= :min_cr";
        $params[':min_cr'] = intval($_GET['min_cr']);
    }

    if (isset($_GET['min_mr']) && is_numeric($_GET['min_mr'])) {
        $where[] = "i.mr >= :min_mr";
        $params[':min_mr'] = intval($_GET['min_mr']);
    }

    if (isset($_GET['min_pr']) && is_numeric($_GET['min_pr'])) {
        $where[] = "i.pr >= :min_pr";
        $params[':min_pr'] = intval($_GET['min_pr']);
    }

    if (isset($_GET['min_dr']) && is_numeric($_GET['min_dr'])) {
        $where[] = "i.dr >= :min_dr";
        $params[':min_dr'] = intval($_GET['min_dr']);
    }

    // Weapon stat filters
    if (isset($_GET['min_damage']) && is_numeric($_GET['min_damage'])) {
        $where[] = "i.damage >= :min_damage";
        $params[':min_damage'] = intval($_GET['min_damage']);
    }

    if (isset($_GET['max_delay']) && is_numeric($_GET['max_delay'])) {
        $where[] = "i.delay <= :max_delay";
        $params[':max_delay'] = intval($_GET['max_delay']);
    }

    $whereClause = implode(' AND ', $where);
    
    // Sort order
    $orderBy = "ml.listed_date DESC";
    if (isset($_GET['sort_by'])) {
        switch ($_GET['sort_by']) {
            case 'oldest':
                $orderBy = "ml.listed_date ASC";
                break;
            case 'price-low':
                $orderBy = "ml.price_copper ASC";
                break;
            case 'price-high':
                $orderBy = "ml.price_copper DESC";
                break;
            case 'name':
                $orderBy = "i.name ASC";
                break;
            default:
                $orderBy = "ml.listed_date DESC";
        }
    }

    // Pagination parameters
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;

    // Get total count for pagination
    $countSql = "
        SELECT COUNT(*) as total
        FROM marketplace_listings ml
        JOIN character_data cd ON ml.seller_char_id = cd.id
        JOIN items i ON ml.item_id = i.id
        WHERE $whereClause
    ";

    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = intval($countStmt->fetch()['total']);

    // Query listings
    $sql = "
        SELECT
            ml.id,
            ml.seller_char_id,
            cd.name as seller_name,
            ml.item_id,
            i.name as item_name,
            i.icon,
            ml.quantity,
            ml.price_copper,
            ml.augment_1,
            ml.augment_2,
            ml.augment_3,
            ml.augment_4,
            ml.augment_5,
            ml.augment_6,
            ml.charges,
            ml.listed_date
        FROM marketplace_listings ml
        JOIN character_data cd ON ml.seller_char_id = cd.id
        JOIN items i ON ml.item_id = i.id
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($sql);

    // Bind pagination parameters separately (PDO requirement for LIMIT/OFFSET)
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    // Bind other parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $listings = $stmt->fetchAll();
    
    // Format augments as array
    foreach ($listings as &$listing) {
        $listing['augments'] = [
            intval($listing['augment_1']),
            intval($listing['augment_2']),
            intval($listing['augment_3']),
            intval($listing['augment_4']),
            intval($listing['augment_5']),
            intval($listing['augment_6'])
        ];
        
        // Remove individual augment fields
        unset($listing['augment_1'], $listing['augment_2'], $listing['augment_3'],
              $listing['augment_4'], $listing['augment_5'], $listing['augment_6']);
        
        // Convert numeric fields
        $listing['id'] = intval($listing['id']);
        $listing['seller_char_id'] = intval($listing['seller_char_id']);
        $listing['item_id'] = intval($listing['item_id']);
        $listing['quantity'] = intval($listing['quantity']);
        $listing['price_copper'] = intval($listing['price_copper']);
        $listing['charges'] = intval($listing['charges']);
    }
    
    // Calculate pagination metadata
    $totalPages = ceil($totalCount / $limit);

    sendJSON([
        'success' => true,
        'listings' => $listings,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total_items' => $totalCount,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Listings error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to fetch listings'], 500);
}
?>
