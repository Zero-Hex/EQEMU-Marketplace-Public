<?php
/**
 * Delete Marketplace Listing (Admin Only)
 * POST /api/admin/delete-listing.php
 * Requires GM status (status >= 80)
 */
require_once '../config.php';

handleCORS();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSON(['error' => 'Method not allowed'], 405);
}

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

    $data = getRequestData();

    if (!isset($data['listing_id'])) {
        sendJSON(['error' => 'Listing ID required'], 400);
    }

    $db = new Database();
    $conn = $db->getConnection();

    $listing_id = intval($data['listing_id']);

    // Start transaction
    $conn->beginTransaction();

    try {
        // Get listing details first
        $stmt = $conn->prepare("
            SELECT ml.*, cd.name as seller_name
            FROM marketplace_listings ml
            JOIN character_data cd ON ml.seller_char_id = cd.id
            WHERE ml.id = :listing_id
            FOR UPDATE
        ");
        $stmt->execute([':listing_id' => $listing_id]);
        $listing = $stmt->fetch();

        if (!$listing) {
            sendJSON(['error' => 'Listing not found'], 404);
        }

        // Return item to seller via parcel (admin-cancelled listing)
        $note = "Admin cancelled marketplace listing - Item returned";

        // Check if augment and slot_id columns exist in character_parcels
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

        // Cancel the listing (sets status to 'cancelled')
        $stmt = $conn->prepare("
            UPDATE marketplace_listings
            SET status = 'cancelled', cancelled_date = NOW()
            WHERE id = :listing_id
        ");
        $stmt->execute([':listing_id' => $listing_id]);

        $conn->commit();

        sendJSON([
            'success' => true,
            'message' => 'Listing cancelled and item returned to seller via parcel'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Admin delete listing error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to delete listing'], 500);
}
?>
