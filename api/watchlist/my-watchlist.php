<?php
/**
 * Get My Watchlist
 * GET /api/watchlist/my-watchlist.php?char_id=123
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

    // Query watchlist
    $sql = "
        SELECT
            w.id,
            w.char_id,
            w.item_id,
            i.name as item_name,
            i.icon,
            w.item_name_search,
            w.max_price_copper,
            w.min_ac,
            w.min_hp,
            w.min_mana,
            w.notes,
            w.created_date,
            w.is_active
        FROM marketplace_watchlist w
        LEFT JOIN items i ON w.item_id = i.id
        WHERE w.char_id = :char_id
        AND w.is_active = 1
        ORDER BY w.created_date DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':char_id' => $char_id]);
    $watchlist = $stmt->fetchAll();

    // Convert numeric fields
    foreach ($watchlist as &$item) {
        $item['id'] = intval($item['id']);
        $item['char_id'] = intval($item['char_id']);
        $item['item_id'] = $item['item_id'] ? intval($item['item_id']) : null;
        $item['max_price_copper'] = $item['max_price_copper'] ? intval($item['max_price_copper']) : null;
        $item['min_ac'] = $item['min_ac'] ? intval($item['min_ac']) : null;
        $item['min_hp'] = $item['min_hp'] ? intval($item['min_hp']) : null;
        $item['min_mana'] = $item['min_mana'] ? intval($item['min_mana']) : null;
        $item['is_active'] = (bool)$item['is_active'];
    }

    sendJSON([
        'success' => true,
        'watchlist' => $watchlist,
        'count' => count($watchlist)
    ]);

} catch (Exception $e) {
    error_log("Watchlist error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to fetch watchlist'], 500);
}
?>
