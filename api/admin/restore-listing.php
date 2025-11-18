<?php
/**
 * Restore Listing (Cancel Purchase) - Admin Only
 * POST /api/admin/restore-listing.php
 *
 * Cancels a pending purchase and restores the listing to active status
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

    // Check GM status (80+ is GM level in EQEmu)
    if (!isset($payload['status']) || $payload['status'] < 80) {
        sendJSON(['error' => 'Unauthorized. GM access required.'], 403);
    }

    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['listing_id'])) {
        sendJSON(['error' => 'Missing listing_id'], 400);
    }

    $listingId = intval($input['listing_id']);

    $db = new Database();
    $conn = $db->getConnection();

    // Start transaction
    $conn->beginTransaction();

    try {
        // Get listing details
        $stmt = $conn->prepare("
            SELECT ml.*, i.name as item_name, cd.name as seller_name, buyer.name as buyer_name
            FROM marketplace_listings ml
            JOIN items i ON ml.item_id = i.id
            JOIN character_data cd ON ml.seller_char_id = cd.id
            LEFT JOIN character_data buyer ON ml.buyer_char_id = buyer.id
            WHERE ml.id = :listing_id
            FOR UPDATE
        ");
        $stmt->execute([':listing_id' => $listingId]);
        $listing = $stmt->fetch();

        if (!$listing) {
            throw new Exception('Listing not found');
        }

        // Check if listing is sold
        if ($listing['status'] !== 'sold') {
            throw new Exception('Only sold listings can be restored');
        }

        // Get associated transaction (if exists)
        $stmt = $conn->prepare("
            SELECT id, payment_status
            FROM marketplace_transactions
            WHERE listing_id = :listing_id
        ");
        $stmt->execute([':listing_id' => $listingId]);
        $transaction = $stmt->fetch();

        // Log the restore action
        error_log("Admin {$payload['account_id']} restoring listing {$listingId} ({$listing['item_name']}) - Seller: {$listing['seller_name']}, Buyer: " . ($listing['buyer_name'] ?? 'none'));

        // If transaction exists and was paid, we need to refund
        if ($transaction && $transaction['payment_status'] === 'paid') {
            // Mark transaction as cancelled instead of deleting (for audit trail)
            $stmt = $conn->prepare("
                UPDATE marketplace_transactions
                SET payment_status = 'cancelled'
                WHERE id = :transaction_id
            ");
            $stmt->execute([':transaction_id' => $transaction['id']]);

            error_log("Marked transaction {$transaction['id']} as cancelled (was paid - admin should verify refund needed)");
        } elseif ($transaction && $transaction['payment_status'] === 'pending') {
            // Pending payment - safe to just mark as cancelled
            $stmt = $conn->prepare("
                UPDATE marketplace_transactions
                SET payment_status = 'cancelled'
                WHERE id = :transaction_id
            ");
            $stmt->execute([':transaction_id' => $transaction['id']]);

            error_log("Marked transaction {$transaction['id']} as cancelled (was pending)");
        }

        // Restore listing to active status
        $stmt = $conn->prepare("
            UPDATE marketplace_listings
            SET status = 'active',
                buyer_char_id = NULL,
                purchased_date = NULL
            WHERE id = :listing_id
        ");
        $stmt->execute([':listing_id' => $listingId]);

        $conn->commit();

        $message = "Listing restored to active status.";
        if ($transaction && $transaction['payment_status'] === 'paid') {
            $message .= " WARNING: Transaction was already paid. You may need to manually refund the buyer.";
        }

        sendJSON([
            'success' => true,
            'message' => $message,
            'listing' => [
                'id' => $listingId,
                'item_name' => $listing['item_name'],
                'seller_name' => $listing['seller_name'],
                'buyer_name' => $listing['buyer_name'],
                'transaction_status' => $transaction ? $transaction['payment_status'] : 'none'
            ]
        ]);

    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Admin restore listing error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendJSON(['error' => $e->getMessage()], 500);
}
?>
