<?php
require_once '../config.php';

handleCORS();

// Require authentication
$user = requireAuth();

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get all characters for this account
    $stmt = $conn->prepare("
        SELECT id 
        FROM character_data 
        WHERE account_id = :account_id
    ");
    $stmt->execute([':account_id' => $user['account_id']]);
    $char_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($char_ids)) {
        sendJSON([
            'success' => true,
            'listings' => [],
            'count' => 0
        ]);
    }
    
    // Get active listings for all user's characters
    $placeholders = str_repeat('?,', count($char_ids) - 1) . '?';
    
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
            ml.listed_date,
            ml.expires_date
        FROM marketplace_listings ml
        JOIN character_data cd ON ml.seller_char_id = cd.id
        JOIN items i ON ml.item_id = i.id
        WHERE ml.seller_char_id IN ($placeholders)
        AND ml.status = 'active'
        ORDER BY ml.listed_date DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($char_ids);
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
    
    sendJSON([
        'success' => true,
        'listings' => $listings,
        'count' => count($listings)
    ]);
    
} catch (Exception $e) {
    error_log("My listings error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to fetch listings'], 500);
}
?>
