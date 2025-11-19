<?php
/**
 * Claim Seller Earnings
 * Creates parcels with money for all unclaimed earnings
 */

require_once '../config.php';
handleCORS();

// Require authentication
$user = requireAuth();

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get account_id from authenticated user
    $account_id = $user['account_id'];

    // Get marketplace user to find all linked accounts
    $stmt = $conn->prepare("SELECT id, account_id FROM marketplace_users WHERE account_id = :account_id");
    $stmt->execute([':account_id' => $account_id]);
    $marketplaceUser = $stmt->fetch();

    // Collect all account IDs (primary + linked)
    $accountIds = [$account_id];

    if ($marketplaceUser) {
        $stmt = $conn->prepare("
            SELECT account_id
            FROM marketplace_linked_accounts
            WHERE marketplace_user_id = :marketplace_user_id
        ");
        $stmt->execute([':marketplace_user_id' => $marketplaceUser['id']]);
        $linkedAccounts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($linkedAccounts as $accId) {
            $accountIds[] = intval($accId);
        }
    }

    // Get all characters belonging to these accounts
    $accPlaceholders = str_repeat('?,', count($accountIds) - 1) . '?';
    $stmt = $conn->prepare("SELECT id, name, ingame FROM character_data WHERE account_id IN ($accPlaceholders)");
    $stmt->execute($accountIds);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($characters)) {
        sendJSON(['error' => 'No characters found for this account'], 404);
    }

    $char_ids = array_column($characters, 'id');
    $placeholders = implode(',', array_fill(0, count($char_ids), '?'));

    // Start transaction
    $conn->beginTransaction();

    try {
        // Get all unclaimed earnings
        $earnings_query = "
            SELECT
                e.id,
                e.seller_char_id,
                e.amount_copper,
                e.earned_date
            FROM marketplace_seller_earnings e
            WHERE e.seller_char_id IN ($placeholders)
            AND e.claimed = FALSE
            FOR UPDATE
        ";

        try {
            $stmt = $conn->prepare($earnings_query);
            $stmt->execute($char_ids);
            $earnings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Check if table doesn't exist
            if (strpos($e->getMessage(), 'marketplace_seller_earnings') !== false) {
                $conn->rollback();
                http_response_code(500);
                sendJSON([
                    'success' => false,
                    'message' => 'Database not initialized. Please run migration: api/migrations/002_add_seller_earnings.sql',
                    'error' => 'marketplace_seller_earnings table does not exist'
                ], 500);
                exit;
            }
            throw $e;
        }

        if (empty($earnings)) {
            $conn->commit();
            sendJSON([
                'success' => true,
                'message' => 'No unclaimed earnings to claim',
                'claimed_amount' => 0
            ]);
            exit;
        }

        // Group earnings by character
        $earnings_by_char = [];
        $all_earnings_debug = []; // Debug info for all earnings
        foreach ($earnings as $earning) {
            $char_id = $earning['seller_char_id'];
            if (!isset($earnings_by_char[$char_id])) {
                $earnings_by_char[$char_id] = [
                    'total_copper' => 0,
                    'earning_ids' => []
                ];
            }
            $earnings_by_char[$char_id]['total_copper'] += $earning['amount_copper'];
            $earnings_by_char[$char_id]['earning_ids'][] = $earning['id'];

            // Add debug info
            $all_earnings_debug[] = [
                'id' => $earning['id'],
                'char_id' => $char_id,
                'amount_copper' => $earning['amount_copper'],
                'source_listing_id' => $earning['source_listing_id'] ?? null
            ];
        }

        $total_claimed_copper = 0;
        $character_names = [];

        // Process each character's earnings
        foreach ($earnings_by_char as $char_id => $data) {
            $total_copper = $data['total_copper'];
            $earning_ids = $data['earning_ids'];

            // Get character info
            $stmt = $conn->prepare("SELECT name FROM character_data WHERE id = ?");
            $stmt->execute([$char_id]);
            $char_data = $stmt->fetch();
            $char_name = $char_data['name'];
            $character_names[] = $char_name;

            // Convert earnings to alt currency if alternate currency is enabled AND over 1M platinum
            // convertEarningsToAltCurrency has internal USE_ALT_CURRENCY check
            $earningsBreakdown = convertEarningsToAltCurrency($total_copper);
            $altCurrencyEarned = $earningsBreakdown['alt_currency'];
            $copperToAdd = $earningsBreakdown['copper_remainder'];
            $platinum_earned = floor($total_copper / 1000);

            // Check if slot_id exists - CACHED
            $hasSlotId = $db->columnExists('character_parcels', 'slot_id');

            // Always send earnings via parcel (safe for both online and offline characters)
            if ($hasSlotId) {
                // Find next available slot
                $stmt = $conn->prepare("
                    SELECT COALESCE(MAX(slot_id), -1) + 1 as next_slot
                    FROM character_parcels
                    WHERE char_id = ?
                ");
                $stmt->execute([$char_id]);
                $slotResult = $stmt->fetch();
                $nextSlot = $slotResult ? intval($slotResult['next_slot']) : 0;

                // Send money parcel if any copper to add
                if ($copperToAdd > 0) {
                    $parcel_copper = min($copperToAdd, 2147483647); // Max signed int
                    if (USE_ALT_CURRENCY && $altCurrencyEarned > 0) {
                        $platinum_remainder = $earningsBreakdown['platinum_remainder'];
                        $moneyNote = "Marketplace Earnings: {$platinum_remainder}pp (from total {$platinum_earned}pp) from " . count($earning_ids) . " sale(s). Check parcels for " . ALT_CURRENCY_NAME . "!";
                    } else {
                        $moneyNote = "Marketplace Earnings: {$platinum_earned}pp from " . count($earning_ids) . " sale(s)";
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO character_parcels (char_id, slot_id, from_name, item_id, quantity, note, sent_date)
                        VALUES (?, ?, 'Marketplace', 99990, ?, ?, NOW())
                    ");
                    $stmt->execute([$char_id, $nextSlot, $parcel_copper, $moneyNote]);
                    $nextSlot++;
                }

                // Send alt currency parcel if any (only if alternate currency enabled)
                if (USE_ALT_CURRENCY && $altCurrencyEarned > 0) {
                    $platinum_remainder = $earningsBreakdown['platinum_remainder'];
                    $altCurrencyNote = "Marketplace Earnings: {$altCurrencyEarned} " . ALT_CURRENCY_NAME . " (from total {$platinum_earned}pp)";

                    $stmt = $conn->prepare("
                        INSERT INTO character_parcels (char_id, slot_id, from_name, item_id, quantity, note, sent_date)
                        VALUES (?, ?, 'Marketplace', ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$char_id, $nextSlot, ALT_CURRENCY_ITEM_ID, $altCurrencyEarned, $altCurrencyNote]);
                    error_log("Sent {$altCurrencyEarned} " . ALT_CURRENCY_NAME . " via parcel to char {$char_id}");
                }
            } else {
                // No slot_id support
                if ($copperToAdd > 0) {
                    $parcel_copper = min($copperToAdd, 2147483647);
                    if (USE_ALT_CURRENCY && $altCurrencyEarned > 0) {
                        $platinum_remainder = $earningsBreakdown['platinum_remainder'];
                        $moneyNote = "Marketplace Earnings: {$platinum_remainder}pp (from total {$platinum_earned}pp) from " . count($earning_ids) . " sale(s). Check parcels for " . ALT_CURRENCY_NAME . "!";
                    } else {
                        $moneyNote = "Marketplace Earnings: {$platinum_earned}pp from " . count($earning_ids) . " sale(s)";
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO character_parcels (char_id, from_name, item_id, quantity, note, sent_date)
                        VALUES (?, 'Marketplace', 99990, ?, ?, NOW())
                    ");
                    $stmt->execute([$char_id, $parcel_copper, $moneyNote]);
                }

                // Send alt currency parcel if any (only if alternate currency enabled)
                if (USE_ALT_CURRENCY && $altCurrencyEarned > 0) {
                    $platinum_remainder = $earningsBreakdown['platinum_remainder'];
                    $altCurrencyNote = "Marketplace Earnings: {$altCurrencyEarned} " . ALT_CURRENCY_NAME . " (from total {$platinum_earned}pp)";

                    $stmt = $conn->prepare("
                        INSERT INTO character_parcels (char_id, from_name, item_id, quantity, note, sent_date)
                        VALUES (?, 'Marketplace', ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$char_id, ALT_CURRENCY_ITEM_ID, $altCurrencyEarned, $altCurrencyNote]);
                    error_log("Sent {$altCurrencyEarned} " . ALT_CURRENCY_NAME . " via parcel to char {$char_id}");
                }
            }

            // Mark earnings as claimed
            $earning_ids_placeholders = implode(',', array_fill(0, count($earning_ids), '?'));
            $stmt = $conn->prepare("
                UPDATE marketplace_seller_earnings
                SET claimed = TRUE, claimed_date = NOW()
                WHERE id IN ($earning_ids_placeholders)
            ");
            $stmt->execute($earning_ids);

            $total_claimed_copper += $total_copper;
        }

        // Commit transaction
        $conn->commit();

        $total_claimed_platinum = floor($total_claimed_copper / 1000);

        sendJSON([
            'success' => true,
            'message' => "Successfully claimed {$total_claimed_platinum}pp in earnings! Money sent via parcel to: " . implode(', ', $character_names) . ". Check the parcel merchant!",
            'claimed_amount_copper' => $total_claimed_copper,
            'claimed_amount_platinum' => $total_claimed_platinum,
            'characters_paid' => $character_names,
            'earnings_claimed' => count($earnings),
            'payment_method' => 'parcel',
            'debug_earnings' => $all_earnings_debug // Show which earnings were claimed
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Database error in earnings/claim.php: " . $e->getMessage());
    http_response_code(500);
    sendJSON(['error' => 'Database error occurred: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    error_log("Error in earnings/claim.php: " . $e->getMessage());
    http_response_code(500);
    sendJSON(['error' => $e->getMessage()], 500);
}
