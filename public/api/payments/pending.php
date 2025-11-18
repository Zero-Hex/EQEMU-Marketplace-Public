<?php
/**
 * Get Pending Payments Endpoint
 * Returns all pending marketplace payments for a character
 * Used by NPC quest to show what items are awaiting payment
 */
require_once '../config.php';
handleCORS();

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get character_id from query parameter
    if (!isset($_GET['character_id'])) {
        sendJSON(['error' => 'Missing character_id parameter'], 400);
    }

    $characterId = intval($_GET['character_id']);

    // Get all pending transactions for this character
    $stmt = $conn->prepare("
        SELECT
            mt.id as transaction_id,
            mt.listing_id,
            mt.item_id,
            mt.quantity,
            mt.price_copper,
            mt.reserved_date,
            i.name as item_name,
            cd.name as seller_name
        FROM marketplace_transactions mt
        JOIN items i ON mt.item_id = i.id
        JOIN character_data cd ON mt.seller_char_id = cd.id
        WHERE mt.buyer_char_id = :char_id
        AND mt.payment_status = 'pending'
        ORDER BY mt.reserved_date ASC
    ");

    $stmt->execute([':char_id' => $characterId]);
    $pending = $stmt->fetchAll();

    // Calculate totals
    $totalCopper = 0;
    foreach ($pending as &$payment) {
        $totalCopper += intval($payment['price_copper']);
        $payment['price_platinum'] = round($payment['price_copper'] / 1000, 2);
    }

    sendJSON([
        'success' => true,
        'pending_payments' => $pending,
        'total_pending_copper' => $totalCopper,
        'total_pending_platinum' => round($totalCopper / 1000, 2),
        'count' => count($pending)
    ]);

} catch (Exception $e) {
    error_log("Get pending payments error: " . $e->getMessage());
    sendJSON(['error' => 'Failed to retrieve pending payments: ' . $e->getMessage()], 500);
}
?>
