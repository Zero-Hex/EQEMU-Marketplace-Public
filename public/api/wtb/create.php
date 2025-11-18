<?php
/**
 * Create a WTB (Want to Buy) Listing
 * POST /api/wtb/create.php
 */
require_once '../config.php';

handleCORS();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['error' => 'Method not allowed'], 405);
}

try {
    // Get POST data
    $data = getRequestData();

    // Validate required fields
    $requiredFields = ['char_id', 'item_id', 'quantity_wanted', 'price_per_unit'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            sendJSON(['error' => "Missing required field: $field"], 400);
        }
    }

    $db = new Database();
    $conn = $db->getConnection();

    // Validate character exists
    $stmt = $conn->prepare("SELECT id, name FROM character_data WHERE id = :char_id");
    $stmt->execute([':char_id' => $data['char_id']]);
    $character = $stmt->fetch();

    if (!$character) {
        sendJSON(['error' => 'Character not found'], 404);
    }

    // Validate item exists
    $stmt = $conn->prepare("SELECT id, name FROM items WHERE id = :item_id");
    $stmt->execute([':item_id' => $data['item_id']]);
    $item = $stmt->fetch();

    if (!$item) {
        sendJSON(['error' => 'Item not found'], 404);
    }

    // Validate quantity
    $quantity_wanted = intval($data['quantity_wanted']);
    if ($quantity_wanted < 1 || $quantity_wanted > 1000) {
        sendJSON(['error' => 'Quantity must be between 1 and 1000'], 400);
    }

    // Validate price (convert platinum to copper)
    $price_per_unit_pp = floatval($data['price_per_unit']);
    if ($price_per_unit_pp <= 0) {
        sendJSON(['error' => 'Price must be greater than 0'], 400);
    }

    $price_per_unit_copper = intval($price_per_unit_pp * 1000);

    // Optional expiration date (default 30 days)
    $expires_days = isset($data['expires_days']) ? intval($data['expires_days']) : 30;
    if ($expires_days < 1 || $expires_days > 90) {
        $expires_days = 30;
    }

    // Optional notes
    $notes = isset($data['notes']) ? trim($data['notes']) : null;
    if ($notes && strlen($notes) > 500) {
        sendJSON(['error' => 'Notes cannot exceed 500 characters'], 400);
    }

    // Create WTB listing
    $sql = "
        INSERT INTO marketplace_wtb (
            buyer_char_id,
            item_id,
            quantity_wanted,
            price_per_unit_copper,
            notes,
            expires_date,
            status
        ) VALUES (
            :buyer_char_id,
            :item_id,
            :quantity_wanted,
            :price_per_unit_copper,
            :notes,
            DATE_ADD(NOW(), INTERVAL :expires_days DAY),
            'active'
        )
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':buyer_char_id' => $data['char_id'],
        ':item_id' => $data['item_id'],
        ':quantity_wanted' => $quantity_wanted,
        ':price_per_unit_copper' => $price_per_unit_copper,
        ':notes' => $notes,
        ':expires_days' => $expires_days
    ]);

    $wtb_id = $conn->lastInsertId();

    sendJSON([
        'success' => true,
        'message' => 'WTB listing created successfully',
        'wtb_id' => intval($wtb_id),
        'item_name' => $item['name'],
        'quantity_wanted' => $quantity_wanted,
        'price_per_unit_pp' => $price_per_unit_pp
    ]);

} catch (Exception $e) {
    error_log("Create WTB error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to create WTB listing'], 500);
}
?>
