<?php
/**
 * Add Item to Watchlist
 * POST /api/watchlist/add.php
 */
require_once '../config.php';

handleCORS();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['error' => 'Method not allowed'], 405);
}

try {
    $data = getRequestData();

    // Validate required fields
    if (!isset($data['char_id'])) {
        sendJSON(['error' => 'Character ID is required'], 400);
    }

    // Must have either item_id or item_name_search
    if (empty($data['item_id']) && empty($data['item_name_search'])) {
        sendJSON(['error' => 'Either item_id or item_name_search is required'], 400);
    }

    $db = new Database();
    $conn = $db->getConnection();

    $char_id = intval($data['char_id']);
    $item_id = !empty($data['item_id']) ? intval($data['item_id']) : null;
    $item_name_search = !empty($data['item_name_search']) ? trim($data['item_name_search']) : null;

    // Validate item exists if item_id is provided
    if ($item_id) {
        $stmt = $conn->prepare("SELECT id, name FROM items WHERE id = :item_id");
        $stmt->execute([':item_id' => $item_id]);
        $item = $stmt->fetch();

        if (!$item) {
            sendJSON(['error' => 'Item not found'], 404);
        }
    }

    // Optional filters
    $max_price_copper = null;
    if (isset($data['max_price']) && is_numeric($data['max_price'])) {
        $max_price_copper = intval(floatval($data['max_price']) * 1000);
    }

    $min_ac = isset($data['min_ac']) && is_numeric($data['min_ac']) ? intval($data['min_ac']) : null;
    $min_hp = isset($data['min_hp']) && is_numeric($data['min_hp']) ? intval($data['min_hp']) : null;
    $min_mana = isset($data['min_mana']) && is_numeric($data['min_mana']) ? intval($data['min_mana']) : null;
    $notes = isset($data['notes']) ? trim($data['notes']) : null;

    if ($notes && strlen($notes) > 500) {
        sendJSON(['error' => 'Notes cannot exceed 500 characters'], 400);
    }

    // Check if already in watchlist
    $checkSql = "SELECT id FROM marketplace_watchlist WHERE char_id = :char_id AND is_active = 1";
    $checkParams = [':char_id' => $char_id];

    if ($item_id) {
        $checkSql .= " AND item_id = :item_id";
        $checkParams[':item_id'] = $item_id;
    }

    $stmt = $conn->prepare($checkSql);
    $stmt->execute($checkParams);

    if ($stmt->fetch()) {
        sendJSON(['error' => 'Item already in watchlist'], 400);
    }

    // Add to watchlist
    $sql = "
        INSERT INTO marketplace_watchlist (
            char_id,
            item_id,
            item_name_search,
            max_price_copper,
            min_ac,
            min_hp,
            min_mana,
            notes,
            is_active
        ) VALUES (
            :char_id,
            :item_id,
            :item_name_search,
            :max_price_copper,
            :min_ac,
            :min_hp,
            :min_mana,
            :notes,
            1
        )
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':char_id' => $char_id,
        ':item_id' => $item_id,
        ':item_name_search' => $item_name_search,
        ':max_price_copper' => $max_price_copper,
        ':min_ac' => $min_ac,
        ':min_hp' => $min_hp,
        ':min_mana' => $min_mana,
        ':notes' => $notes
    ]);

    $watchlist_id = $conn->lastInsertId();

    sendJSON([
        'success' => true,
        'message' => 'Item added to watchlist',
        'watchlist_id' => intval($watchlist_id)
    ]);

} catch (Exception $e) {
    error_log("Add watchlist error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to add item to watchlist'], 500);
}
?>
