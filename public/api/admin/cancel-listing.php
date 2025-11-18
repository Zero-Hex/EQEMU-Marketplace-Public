<?php
/**
 * Cancel Listing Endpoint (Admin Only)
 * Handles canceling marketplace listings as an admin
 * Requires GM status (status >= 80)
 */

require_once '../config.php';
handleCORS();

try {
    // Check authentication
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

    if (!$token) {
        sendJSON(['error' => 'Authorization required'], 401);
    }

    $payload = JWT::decode($token);

    if (!$payload || !isset($payload['account_id'])) {
        sendJSON(['error' => 'Invalid token'], 401);
    }

    // Check GM status
    if (!isset($payload['status']) || $payload['status'] < 80) {
        sendJSON(['error' => 'Unauthorized. GM access required.'], 403);
    }

    $db = new Database();
    $conn = $db->getConnection();

    // Get POST data
    $input = getRequestData();

    if (!isset($input['listing_id'])) {
        sendJSON(['error' => 'Missing listing_id'], 400);
    }

    $listingId = intval($input['listing_id']);

    // Start transaction
    $conn->beginTransaction();

    try {
        // Get listing details
        $stmt = $conn->prepare("
            SELECT ml.*, cd.account_id, cd.name as seller_name
            FROM marketplace_listings ml
            JOIN character_data cd ON ml.seller_char_id = cd.id
            WHERE ml.id = :listing_id AND ml.status = 'active'
            FOR UPDATE
        ");
        $stmt->execute([':listing_id' => $listingId]);
        $listing = $stmt->fetch();

        if (!$listing) {
            throw new Exception('Listing not found or already completed');
        }

        // Admin can cancel any listing, no ownership check needed

        // Check if augment and slot_id columns exist in character_parcels - CACHED
        $hasAugmentColumns = $db->columnExists('character_parcels', 'augslot1');
        $hasSlotIdColumn = $db->columnExists('character_parcels', 'slot_id');

        // Get next available slot_id for this character
        $slotId = 0;
        if ($hasSlotIdColumn) {
            $stmt = $conn->prepare("SELECT COALESCE(MAX(slot_id), -1) + 1 as next_slot FROM character_parcels WHERE char_id = :char_id");
            $stmt->execute([':char_id' => $listing['seller_char_id']]);
            $result = $stmt->fetch();
            $slotId = intval($result['next_slot']);
        }

        // Return item to character via parcel with augments preserved
        $note = "Admin cancelled marketplace listing - Item returned";

        if ($hasAugmentColumns && $hasSlotIdColumn) {
            // Full parcel with augments and slot_id
            $stmt = $conn->prepare("
                INSERT INTO character_parcels
                (char_id, slot_id, from_name, note, sent_date, item_id, quantity,
                 augslot1, augslot2, augslot3, augslot4, augslot5, augslot6)
                VALUES (:char_id, :slot_id, :from_name, :note, NOW(), :item_id, :quantity,
                 :aug1, :aug2, :aug3, :aug4, :aug5, :aug6)
            ");
            $stmt->execute([
                ':char_id' => $listing['seller_char_id'],
                ':slot_id' => $slotId,
                ':from_name' => 'Marketplace Admin',
                ':note' => $note,
                ':item_id' => $listing['item_id'],
                ':quantity' => $listing['quantity'],
                ':aug1' => $listing['augment_1'] ?? 0,
                ':aug2' => $listing['augment_2'] ?? 0,
                ':aug3' => $listing['augment_3'] ?? 0,
                ':aug4' => $listing['augment_4'] ?? 0,
                ':aug5' => $listing['augment_5'] ?? 0,
                ':aug6' => $listing['augment_6'] ?? 0
            ]);
        } elseif ($hasAugmentColumns) {
            // Augments but no slot_id
            $stmt = $conn->prepare("
                INSERT INTO character_parcels
                (char_id, from_name, note, sent_date, item_id, quantity,
                 augslot1, augslot2, augslot3, augslot4, augslot5, augslot6)
                VALUES (:char_id, :from_name, :note, NOW(), :item_id, :quantity,
                 :aug1, :aug2, :aug3, :aug4, :aug5, :aug6)
            ");
            $stmt->execute([
                ':char_id' => $listing['seller_char_id'],
                ':from_name' => 'Marketplace Admin',
                ':note' => $note,
                ':item_id' => $listing['item_id'],
                ':quantity' => $listing['quantity'],
                ':aug1' => $listing['augment_1'] ?? 0,
                ':aug2' => $listing['augment_2'] ?? 0,
                ':aug3' => $listing['augment_3'] ?? 0,
                ':aug4' => $listing['augment_4'] ?? 0,
                ':aug5' => $listing['augment_5'] ?? 0,
                ':aug6' => $listing['augment_6'] ?? 0
            ]);
        } elseif ($hasSlotIdColumn) {
            // slot_id but no augments
            $stmt = $conn->prepare("
                INSERT INTO character_parcels (char_id, slot_id, from_name, item_id, quantity, note, sent_date)
                VALUES (:char_id, :slot_id, :from_name, :item_id, :quantity, :note, NOW())
            ");
            $stmt->execute([
                ':char_id' => $listing['seller_char_id'],
                ':slot_id' => $slotId,
                ':from_name' => 'Marketplace Admin',
                ':item_id' => $listing['item_id'],
                ':quantity' => $listing['quantity'],
                ':note' => $note
            ]);
        } else {
            // Basic parcel without augments or slot_id
            $stmt = $conn->prepare("
                INSERT INTO character_parcels (char_id, from_name, item_id, quantity, note, sent_date)
                VALUES (:char_id, :from_name, :item_id, :quantity, :note, NOW())
            ");
            $stmt->execute([
                ':char_id' => $listing['seller_char_id'],
                ':from_name' => 'Marketplace Admin',
                ':item_id' => $listing['item_id'],
                ':quantity' => $listing['quantity'],
                ':note' => $note
            ]);
        }

        // Mark listing as cancelled
        $stmt = $conn->prepare("
            UPDATE marketplace_listings
            SET status = 'cancelled'
            WHERE id = :listing_id
        ");
        $stmt->execute([':listing_id' => $listingId]);

        // Commit transaction
        $conn->commit();

        sendJSON([
            'success' => true,
            'message' => 'Listing cancelled by admin. Item has been sent via parcel to seller.'
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Admin cancel listing error: " . $e->getMessage());
    sendJSON(['error' => $e->getMessage()], 500);
}
?>
