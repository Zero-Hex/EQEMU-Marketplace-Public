<?php
/**
 * Search Items by Name
 * GET /api/items/search.php?search=sword&limit=10
 */
require_once '../config.php';

handleCORS();

try {
    // Require search parameter
    if (!isset($_GET['search']) || empty(trim($_GET['search']))) {
        sendJSON(['error' => 'Search query is required'], 400);
    }

    $search = trim($_GET['search']);
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? intval($_GET['limit']) : 10;

    // Cap limit at 50
    if ($limit > 50) {
        $limit = 50;
    }

    $db = new Database();
    $conn = $db->getConnection();

    // Search for items by name
    $sql = "
        SELECT
            id,
            name,
            icon,
            itemtype,
            ac,
            hp,
            mana,
            astr,
            adex,
            asta,
            aagi,
            aint,
            awis,
            acha
        FROM items
        WHERE name LIKE :search
        ORDER BY name ASC
        LIMIT :limit
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $items = $stmt->fetchAll();

    // Convert numeric fields
    foreach ($items as &$item) {
        $item['id'] = intval($item['id']);
        $item['itemtype'] = intval($item['itemtype']);
        $item['ac'] = intval($item['ac']);
        $item['hp'] = intval($item['hp']);
        $item['mana'] = intval($item['mana']);
        $item['astr'] = intval($item['astr']);
        $item['adex'] = intval($item['adex']);
        $item['asta'] = intval($item['asta']);
        $item['aagi'] = intval($item['aagi']);
        $item['aint'] = intval($item['aint']);
        $item['awis'] = intval($item['awis']);
        $item['acha'] = intval($item['acha']);
    }

    sendJSON([
        'success' => true,
        'items' => $items,
        'count' => count($items)
    ]);

} catch (Exception $e) {
    error_log("Item search error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to search items'], 500);
}
?>
