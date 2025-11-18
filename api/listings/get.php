<?php
/**
 * Get Single Listing Details
 * GET /api/listings/get.php?id=123
 */
require_once '../config.php';

handleCORS();

try {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        sendJSON(['error' => 'Listing ID required'], 400);
    }

    $listing_id = intval($_GET['id']);

    $db = new Database();
    $conn = $db->getConnection();

    // Get listing with full item details
    $sql = "
        SELECT
            ml.id,
            ml.seller_char_id,
            cd.name as seller_name,
            ml.item_id,
            ml.quantity,
            ml.price_copper,
            ml.augment_1,
            ml.augment_2,
            ml.augment_3,
            ml.augment_4,
            ml.augment_5,
            ml.augment_6,
            ml.charges,
            ml.status,
            ml.listed_date,
            i.id as item_id,
            i.name as item_name,
            i.icon,
            i.lore,
            i.ac,
            i.hp,
            i.mana,
            i.endur,
            i.astr,
            i.adex,
            i.asta,
            i.aagi,
            i.aint,
            i.awis,
            i.acha,
            i.fr,
            i.cr,
            i.mr,
            i.pr,
            i.dr,
            i.damage,
            i.delay,
            i.itemtype,
            i.itemclass,
            i.weight,
            i.slots,
            i.classes,
            i.races
        FROM marketplace_listings ml
        JOIN character_data cd ON ml.seller_char_id = cd.id
        JOIN items i ON ml.item_id = i.id
        WHERE ml.id = :listing_id
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':listing_id' => $listing_id]);
    $listing = $stmt->fetch();

    if (!$listing) {
        sendJSON(['error' => 'Listing not found'], 404);
    }

    // Format augments as array
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

    // Create item object with all details
    $item = [
        'id' => intval($listing['item_id']),
        'name' => $listing['item_name'],
        'icon' => intval($listing['icon']),
        'lore' => $listing['lore'],
        'ac' => intval($listing['ac']),
        'hp' => intval($listing['hp']),
        'mana' => intval($listing['mana']),
        'endur' => intval($listing['endur']),
        'astr' => intval($listing['astr']),
        'adex' => intval($listing['adex']),
        'asta' => intval($listing['asta']),
        'aagi' => intval($listing['aagi']),
        'aint' => intval($listing['aint']),
        'awis' => intval($listing['awis']),
        'acha' => intval($listing['acha']),
        'fr' => intval($listing['fr']),
        'cr' => intval($listing['cr']),
        'mr' => intval($listing['mr']),
        'pr' => intval($listing['pr']),
        'dr' => intval($listing['dr']),
        'damage' => intval($listing['damage']),
        'delay' => intval($listing['delay']),
        'itemtype' => intval($listing['itemtype']),
        'itemclass' => intval($listing['itemclass']),
        'weight' => intval($listing['weight']),
        'slots' => intval($listing['slots']),
        'classes' => intval($listing['classes']),
        'races' => intval($listing['races'])
    ];

    // Build listing response
    $response = [
        'id' => intval($listing['id']),
        'seller_char_id' => intval($listing['seller_char_id']),
        'seller_name' => $listing['seller_name'],
        'item_id' => intval($listing['item_id']),
        'item_name' => $listing['item_name'],
        'icon' => intval($listing['icon']),
        'quantity' => intval($listing['quantity']),
        'price_copper' => intval($listing['price_copper']),
        'augments' => $listing['augments'],
        'charges' => intval($listing['charges']),
        'status' => $listing['status'],
        'listed_date' => $listing['listed_date'],
        'item' => $item
    ];

    sendJSON([
        'success' => true,
        'listing' => $response
    ]);

} catch (Exception $e) {
    error_log("Get listing error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to fetch listing'], 500);
}
?>
