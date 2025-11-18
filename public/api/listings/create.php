<?php
/**
 * Create Listing Endpoint
 * Handles creating new marketplace listings
 */

require_once '../config.php';
handleCORS();

// Require authentication
$user = requireAuth();

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $requiredFields = ['seller_char_id', 'slot_id', 'item_id', 'quantity', 'price_copper'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            sendJSON(['error' => "Missing required field: $field"], 400);
        }
    }
    
    $sellerCharId = intval($input['seller_char_id']);
    $slotId = intval($input['slot_id']);
    $itemId = intval($input['item_id']);
    $quantity = intval($input['quantity']);
    $priceCopper = intval($input['price_copper']);
    
    // Validate values
    if ($quantity < 1) {
        sendJSON(['error' => 'Quantity must be at least 1'], 400);
    }
    
    if ($priceCopper < 0) {
        sendJSON(['error' => 'Price cannot be negative'], 400);
    }
    
    // Start transaction
    $conn->begin_transaction();

    // Verify character belongs to user and is not online
    $stmt = $conn->prepare("
        SELECT id, name, account_id, online
        FROM character_data
        WHERE id = ?
    ");
    $stmt->bind_param("i", $sellerCharId);
    $stmt->execute();
    $result = $stmt->get_result();
    $character = $result->fetch_assoc();

    if (!$character) {
        $conn->rollback();
        sendJSON(['error' => 'Character not found'], 404);
    }

    if ($character['account_id'] != $user['id']) {
        $conn->rollback();
        sendJSON(['error' => 'You do not own this character'], 403);
    }

    // Prevent listing items while character is online to avoid inventory desync
    if (isset($character['online']) && $character['online'] > 0) {
        $conn->rollback();
        sendJSON(['error' => 'Cannot list items while character is online. Please log out first to prevent inventory issues.'], 400);
    }
    
    // Verify item exists in character's inventory at specified slot
    $stmt = $conn->prepare("
        SELECT inv.*, i.name as item_name, i.icon, i.nodrop, i.norent
        FROM inventory inv
        JOIN items i ON inv.item_id = i.id
        WHERE inv.character_id = ? AND inv.slot_id = ? AND inv.item_id = ?
    ");
    $stmt->bind_param("iii", $sellerCharId, $slotId, $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $inventoryItem = $result->fetch_assoc();

    if (!$inventoryItem) {
        $conn->rollback();
        sendJSON(['error' => 'Item not found in specified inventory slot'], 404);
    }

    // Check if item is NO TRADE (nodrop = 0 means NO TRADE in EQEmu)
    if ($inventoryItem['nodrop'] == 0) {
        $conn->rollback();
        sendJSON(['error' => 'Cannot list NO TRADE items on the marketplace'], 400);
    }

    // Check if item is NO RENT (temporary item that disappears on logout)
    if ($inventoryItem['norent'] == 0) {
        $conn->rollback();
        sendJSON(['error' => 'Cannot list temporary (NO RENT) items on the marketplace'], 400);
    }

    // Verify quantity
    $itemCharges = intval($inventoryItem['charges']);
    if ($itemCharges < $quantity) {
        $conn->rollback();
        sendJSON(['error' => "Insufficient quantity. You have $itemCharges but tried to list $quantity"], 400);
    }
    
    // Get augment data if any
    $augments = [
        $inventoryItem['augslot1'] ?? 0,
        $inventoryItem['augslot2'] ?? 0,
        $inventoryItem['augslot3'] ?? 0,
        $inventoryItem['augslot4'] ?? 0,
        $inventoryItem['augslot5'] ?? 0,
        $inventoryItem['augslot6'] ?? 0
    ];
    $augmentsJson = json_encode($augments);
    
    // Remove or reduce item from inventory
    // Double-check the item is still at the expected slot with correct item_id to prevent duplication
    if ($itemCharges == $quantity) {
        // Remove entire stack - verify item is still there
        $stmt = $conn->prepare("DELETE FROM inventory WHERE character_id = ? AND slot_id = ? AND item_id = ?");
        $stmt->bind_param("iii", $sellerCharId, $slotId, $itemId);
    } else {
        // Reduce stack - verify item is still there and has enough charges
        $newCharges = $itemCharges - $quantity;
        $stmt = $conn->prepare("
            UPDATE inventory
            SET charges = ?
            WHERE character_id = ? AND slot_id = ? AND item_id = ? AND charges >= ?
        ");
        $stmt->bind_param("iiiii", $newCharges, $sellerCharId, $slotId, $itemId, $quantity);
    }

    if (!$stmt->execute()) {
        $conn->rollback();
        sendJSON(['error' => 'Failed to remove item from inventory'], 500);
    }

    // Verify that the operation actually affected rows (item was found and removed/updated)
    if ($stmt->affected_rows == 0) {
        $conn->rollback();
        sendJSON(['error' => 'Item not found in inventory slot. The item may have been moved or removed. Please refresh and try again.'], 400);
    }
    
    // Create marketplace listing - note: augments should be individual columns, not JSON
    $stmt = $conn->prepare("
        INSERT INTO marketplace_listings
        (seller_char_id, item_id, quantity, price_copper, charges,
         augment_1, augment_2, augment_3, augment_4, augment_5, augment_6,
         listed_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'active')
    ");
    $stmt->bind_param("iiiiiiiiiii",
        $sellerCharId,
        $itemId,
        $quantity,
        $priceCopper,
        $quantity,
        $augments[0],
        $augments[1],
        $augments[2],
        $augments[3],
        $augments[4],
        $augments[5]
    );

    if (!$stmt->execute()) {
        $conn->rollback();
        sendJSON(['error' => 'Failed to create marketplace listing'], 500);
    }

    // Commit transaction
    $conn->commit();

    sendJSON([
        'success' => true,
        'message' => 'Listing created successfully',
        'listing_id' => $conn->insert_id
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log("Create listing error: " . $e->getMessage());
    sendJSON(['error' => $e->getMessage()], 500);
}