<?php
require_once '../config.php';

handleCORS();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    sendJSON(['error' => 'Invalid item ID'], 400);
}

$item_id = intval($_GET['id']);

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get item details from EQEMU items table
    // Using SELECT * to get all columns regardless of schema differences
    $stmt = $conn->prepare("
        SELECT *
        FROM items 
        WHERE id = :item_id
    ");
    
    $stmt->execute([':item_id' => $item_id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        sendJSON(['error' => 'Item not found'], 404);
    }
    
    // Build description from available fields
    $description = [];
    
    // Handle both 'name' and 'Name' column variations
    $itemName = isset($item['name']) ? $item['name'] : (isset($item['Name']) ? $item['Name'] : 'Unknown Item');
    
    if (!empty($item['lore'])) {
        $description[] = "LORE ITEM";
    }
    if (!empty($item['nodrop'])) {
        $description[] = "NO DROP";
    }
    if (!empty($item['norent'])) {
        $description[] = "NO RENT";
    }
    if (!empty($item['magic'])) {
        $description[] = "MAGIC ITEM";
    }
    
    // Add stats if they exist
    if (isset($item['ac']) && $item['ac'] > 0) {
        $description[] = "AC: " . $item['ac'];
    }
    if (isset($item['hp']) && $item['hp'] > 0) {
        $description[] = "HP: +" . $item['hp'];
    }
    if (isset($item['mana']) && $item['mana'] > 0) {
        $description[] = "MANA: +" . $item['mana'];
    }
    if (isset($item['damage']) && isset($item['delay']) && $item['damage'] > 0) {
        $description[] = "Damage: " . $item['damage'] . " Delay: " . $item['delay'];
    }
    
    // Add stat bonuses if they exist
    $stats = [
        ['astr', 'STR'],
        ['asta', 'STA'], 
        ['aagi', 'AGI'],
        ['adex', 'DEX'],
        ['awis', 'WIS'],
        ['aint', 'INT'],
        ['acha', 'CHA']
    ];
    
    foreach ($stats as $stat) {
        $key = $stat[0];
        $label = $stat[1];
        if (isset($item[$key]) && $item[$key] > 0) {
            $description[] = $label . ": +" . $item[$key];
        }
    }
    
    // Add resistances if they exist
    $resists = [
        ['cr', 'Cold'],
        ['dr', 'Disease'],
        ['fr', 'Fire'],
        ['mr', 'Magic'],
        ['pr', 'Poison']
    ];
    
    foreach ($resists as $resist) {
        $key = $resist[0];
        $label = $resist[1];
        if (isset($item[$key]) && $item[$key] > 0) {
            $description[] = $label . " Resist: +" . $item[$key];
        }
    }
    
    // Add heroic stats if they exist
    $heroics = [
        ['heroic_str', 'Heroic STR'],
        ['heroic_sta', 'Heroic STA'],
        ['heroic_agi', 'Heroic AGI'],
        ['heroic_dex', 'Heroic DEX'],
        ['heroic_wis', 'Heroic WIS'],
        ['heroic_int', 'Heroic INT'],
        ['heroic_cha', 'Heroic CHA']
    ];
    
    foreach ($heroics as $heroic) {
        $key = $heroic[0];
        $label = $heroic[1];
        if (isset($item[$key]) && $item[$key] > 0) {
            $description[] = $label . ": +" . $item[$key];
        }
    }
    
    $item['description'] = implode(' | ', $description);
    
    sendJSON([
        'success' => true,
        'item' => $item
    ]);
    
} catch (Exception $e) {
    error_log("Item details error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to fetch item details: ' . $e->getMessage()], 500);
}
?>