<?php
/**
 * Complete Payment Endpoint
 * Marks a pending transaction as paid and delivers the item
 * Called by NPC quest after player pays platinum in-game
 */
require_once '../config.php';
handleCORS();

// This endpoint is called from NPC quest, no user auth required
// Security: Validates character_id matches transaction buyer_char_id

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['transaction_id']) || !isset($input['character_id'])) {
        sendJSON(['error' => 'Missing required fields'], 400);
    }

    $transactionId = intval($input['transaction_id']);
    $characterId = intval($input['character_id']);

    // Start transaction
    $conn->beginTransaction();

    try {
        // Get transaction details and verify it belongs to this character
        $stmt = $conn->prepare("
            SELECT
                mt.*,
                ml.quantity,
                ml.augment_1, ml.augment_2, ml.augment_3,
                ml.augment_4, ml.augment_5, ml.augment_6,
                cd.name as seller_name
            FROM marketplace_transactions mt
            JOIN marketplace_listings ml ON mt.listing_id = ml.id
            JOIN character_data cd ON mt.seller_char_id = cd.id
            WHERE mt.id = :trans_id
            AND mt.buyer_char_id = :char_id
            AND mt.payment_status = 'pending'
            FOR UPDATE
        ");

        $stmt->execute([
            ':trans_id' => $transactionId,
            ':char_id' => $characterId
        ]);

        $transaction = $stmt->fetch();

        if (!$transaction) {
            throw new Exception('Transaction not found or already paid');
        }

        // Get quantity from the transaction/listing
        $quantity = intval($transaction['quantity']);

        // Check if slot_id exists in parcels table
        $hasSlotId = $db->columnExists('character_parcels', 'slot_id');

        // Send item to buyer via parcel system
        $note = "Purchased from marketplace - Seller: " . $transaction['seller_name'];

        if ($hasSlotId) {
            // Find next available slot
            $stmt = $conn->prepare("
                SELECT COALESCE(MAX(slot_id), -1) + 1 as next_slot
                FROM character_parcels
                WHERE char_id = :char_id
            ");
            $stmt->execute([':char_id' => $characterId]);
            $slotResult = $stmt->fetch();
            $nextSlot = $slotResult ? intval($slotResult['next_slot']) : 0;

            $stmt = $conn->prepare("
                INSERT INTO character_parcels
                (char_id, slot_id, from_name, item_id, quantity, note, sent_date,
                 augslot1, augslot2, augslot3, augslot4, augslot5, augslot6)
                VALUES
                (:char_id, :slot_id, :from_name, :item_id, :quantity, :note, NOW(),
                 :aug1, :aug2, :aug3, :aug4, :aug5, :aug6)
            ");

            $stmt->execute([
                ':char_id' => $characterId,
                ':slot_id' => $nextSlot,
                ':from_name' => $transaction['seller_name'],
                ':item_id' => $transaction['item_id'],
                ':quantity' => $quantity,
                ':note' => $note,
                ':aug1' => $transaction['augment_1'] ?? 0,
                ':aug2' => $transaction['augment_2'] ?? 0,
                ':aug3' => $transaction['augment_3'] ?? 0,
                ':aug4' => $transaction['augment_4'] ?? 0,
                ':aug5' => $transaction['augment_5'] ?? 0,
                ':aug6' => $transaction['augment_6'] ?? 0
            ]);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO character_parcels
                (char_id, from_name, item_id, quantity, note, sent_date,
                 augslot1, augslot2, augslot3, augslot4, augslot5, augslot6)
                VALUES
                (:char_id, :from_name, :item_id, :quantity, :note, NOW(),
                 :aug1, :aug2, :aug3, :aug4, :aug5, :aug6)
            ");

            $stmt->execute([
                ':char_id' => $characterId,
                ':from_name' => $transaction['seller_name'],
                ':item_id' => $transaction['item_id'],
                ':quantity' => $quantity,
                ':note' => $note,
                ':aug1' => $transaction['augment_1'] ?? 0,
                ':aug2' => $transaction['augment_2'] ?? 0,
                ':aug3' => $transaction['augment_3'] ?? 0,
                ':aug4' => $transaction['augment_4'] ?? 0,
                ':aug5' => $transaction['augment_5'] ?? 0,
                ':aug6' => $transaction['augment_6'] ?? 0
            ]);
        }

        // Add earnings to seller's pending earnings
        $stmt = $conn->prepare("
            INSERT INTO marketplace_seller_earnings
            (seller_char_id, amount_copper, source_listing_id, earned_date, claimed)
            VALUES (:seller_id, :amount, :listing_id, NOW(), FALSE)
        ");

        $stmt->execute([
            ':seller_id' => $transaction['seller_char_id'],
            ':amount' => $transaction['price_copper'],
            ':listing_id' => $transaction['listing_id']
        ]);

        // Mark transaction as paid
        $stmt = $conn->prepare("
            UPDATE marketplace_transactions
            SET payment_status = 'paid', payment_date = NOW()
            WHERE id = :trans_id
        ");

        $stmt->execute([':trans_id' => $transactionId]);

        // Commit transaction
        $conn->commit();

        sendJSON([
            'success' => true,
            'message' => 'Payment completed and item delivered to parcels',
            'item_id' => $transaction['item_id'],
            'quantity' => $quantity,
            'price_paid' => $transaction['price_copper']
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Complete payment error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to complete payment: ' . $e->getMessage()], 500);
}
?>
