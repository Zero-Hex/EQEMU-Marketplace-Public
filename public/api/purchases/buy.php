<?php
/**
 * Purchase Item Endpoint - SIMPLIFIED ALTERNATIVE
 *
 * NOTE: This is a simplified purchase implementation that ONLY supports
 * currency stored in character_data table. For full feature support including
 * character_currency table detection, use /listings/purchase.php instead.
 *
 * This endpoint may be deprecated in future versions.
 *
 * Features:
 * - Simple currency handling (character_data only)
 * - Supports augments on purchased items
 * - Sends payment notification parcels to sellers
 *
 * Limitations:
 * - Does NOT detect character_currency table
 * - Does NOT handle slot_id in parcel system
 */
require_once '../config.php';

handleCORS();

// Require authentication
$user = requireAuth();

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['listing_id']) || !isset($data['character_id'])) {
    sendJSON(['error' => 'Missing required fields'], 400);
}

$listing_id = intval($data['listing_id']);
$buyer_char_id = intval($data['character_id']);

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Verify character belongs to authenticated user
    $stmt = $conn->prepare("
        SELECT account_id, name, platinum, gold, silver, copper, online
        FROM character_data
        WHERE id = :char_id
    ");
    $stmt->execute([':char_id' => $buyer_char_id]);
    $buyer = $stmt->fetch();

    if (!$buyer || $buyer['account_id'] != $user['account_id']) {
        $conn->rollBack();
        sendJSON(['error' => 'Invalid character'], 403);
    }

    // Get listing details
    $stmt = $conn->prepare("
        SELECT 
            ml.seller_char_id, ml.item_id, ml.price_copper, ml.quantity,
            ml.augment_1, ml.augment_2, ml.augment_3, ml.augment_4, ml.augment_5, ml.augment_6,
            ml.charges, ml.custom_data, ml.status,
            i.name as item_name,
            cd.name as seller_name
        FROM marketplace_listings ml
        JOIN items i ON ml.item_id = i.id
        JOIN character_data cd ON ml.seller_char_id = cd.id
        WHERE ml.id = :listing_id
    ");
    $stmt->execute([':listing_id' => $listing_id]);
    $listing = $stmt->fetch();
    
    if (!$listing) {
        $conn->rollBack();
        sendJSON(['error' => 'Listing not found'], 404);
    }
    
    if ($listing['status'] !== 'active') {
        $conn->rollBack();
        sendJSON(['error' => 'Listing is no longer available'], 400);
    }
    
    // Check if buyer is trying to buy their own listing
    if ($listing['seller_char_id'] == $buyer_char_id) {
        $conn->rollBack();
        sendJSON(['error' => 'Cannot purchase your own listing'], 400);
    }
    
    $price = intval($listing['price_copper']);

    // Calculate buyer's total copper
    $total_copper = ($buyer['platinum'] * 1000) + ($buyer['gold'] * 100) +
                   ($buyer['silver'] * 10) + $buyer['copper'];

    // Calculate pending payments total
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(price_copper), 0) as pending_total
        FROM marketplace_transactions
        WHERE buyer_char_id = :char_id AND payment_status = 'pending'
    ");
    $stmt->execute([':char_id' => $buyer_char_id]);
    $pending_result = $stmt->fetch();
    $pending_copper = intval($pending_result['pending_total']);

    // Calculate available balance (current - pending)
    $available_copper = $total_copper - $pending_copper;

    if ($available_copper < $price) {
        $conn->rollBack();
        $price_pp = round($price / 1000, 2);
        $available_pp = round($available_copper / 1000, 2);
        $pending_pp = round($pending_copper / 1000, 2);
        sendJSON(['error' => "Insufficient funds. You need {$price_pp}pp but only have {$available_pp}pp available ({$pending_pp}pp in pending payments). Pay your pending purchases at the Marketplace Broker NPC."], 400);
    }

    // Update listing status (reserve for buyer)
    $stmt = $conn->prepare("
        UPDATE marketplace_listings
        SET status = 'sold', buyer_char_id = :buyer_id, purchased_date = NOW()
        WHERE id = :listing_id
    ");

    $stmt->execute([
        ':buyer_id' => $buyer_char_id,
        ':listing_id' => $listing_id
    ]);

    // Create pending payment transaction (item will be delivered after payment at NPC)
    $stmt = $conn->prepare("
        INSERT INTO marketplace_transactions
        (listing_id, seller_char_id, buyer_char_id, item_id, price_copper, transaction_date, payment_status, reserved_date)
        VALUES (:listing_id, :seller_id, :buyer_id, :item_id, :price, NOW(), 'pending', NOW())
    ");

    $stmt->execute([
        ':listing_id' => $listing_id,
        ':seller_id' => $listing['seller_char_id'],
        ':buyer_id' => $buyer_char_id,
        ':item_id' => $listing['item_id'],
        ':price' => $price
    ]);
    
    // Commit transaction
    $conn->commit();

    $price_pp = round($price / 1000, 2);

    sendJSON([
        'success' => true,
        'message' => "Purchase reserved! You need to visit the Marketplace Broker NPC in-game to pay {$price_pp} platinum and receive your item. Say 'pending' to the NPC to see your pending purchases.",
        'item_name' => $listing['item_name'],
        'buyer_name' => $buyer['name'],
        'price_to_pay' => $price,
        'price_to_pay_pp' => $price_pp,
        'payment_status' => 'pending'
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    error_log("Purchase error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to complete purchase: ' . $e->getMessage()], 500);
}
?>
