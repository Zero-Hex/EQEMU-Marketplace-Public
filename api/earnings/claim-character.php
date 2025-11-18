<?php
/**
 * Claim Earnings for a Specific Character
 * Creates a parcel with money for all unclaimed earnings for a single character
 */

require_once '../config.php';
handleCORS();

// Require authentication
$user = requireAuth();

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['character_id']) || !is_numeric($input['character_id'])) {
    sendJSON(['error' => 'Invalid character ID'], 400);
}

$character_id = intval($input['character_id']);

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get account_id from authenticated user
    $account_id = $user['account_id'];

    // Detect where currency is stored (character_data vs character_currency) - CACHED
    $hasCurrencyTable = $db->tableExists('character_currency');

    // Verify the character belongs to this account (check primary and linked accounts)
    $stmt = $conn->prepare("SELECT id, name, account_id, ingame FROM character_data WHERE id = ?");
    $stmt->execute([$character_id]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        sendJSON(['error' => 'Character not found'], 404);
    }

    // Check if character belongs to primary account or linked account
    $charAccountId = intval($character['account_id']);
    $ownsCharacter = false;

    if ($charAccountId === intval($account_id)) {
        $ownsCharacter = true;
    } else {
        // Check if it's a linked account
        $stmt = $conn->prepare("
            SELECT mla.id
            FROM marketplace_linked_accounts mla
            JOIN marketplace_users mu ON mla.marketplace_user_id = mu.id
            WHERE mu.account_id = :primary_account_id
            AND mla.account_id = :char_account_id
        ");
        $stmt->execute([
            ':primary_account_id' => $account_id,
            ':char_account_id' => $charAccountId
        ]);
        if ($stmt->fetch()) {
            $ownsCharacter = true;
        }
    }

    if (!$ownsCharacter) {
        sendJSON(['error' => 'You do not own this character'], 403);
    }

    // Start transaction
    $conn->beginTransaction();

    try {
        // Get all unclaimed earnings for this character
        $earnings_query = "
            SELECT
                e.id,
                e.seller_char_id,
                e.amount_copper,
                e.earned_date
            FROM marketplace_seller_earnings e
            WHERE e.seller_char_id = ?
            AND e.claimed = FALSE
            FOR UPDATE
        ";

        try {
            $stmt = $conn->prepare($earnings_query);
            $stmt->execute([$character_id]);
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
                'message' => 'No unclaimed earnings for this character',
                'claimed_amount' => 0
            ]);
            exit;
        }

        // Calculate total copper
        $total_copper = 0;
        $earning_ids = [];
        $earnings_debug = []; // Debug info
        foreach ($earnings as $earning) {
            $total_copper += $earning['amount_copper'];
            $earning_ids[] = $earning['id'];
            $earnings_debug[] = [
                'id' => $earning['id'],
                'amount_copper' => $earning['amount_copper'],
                'source_listing_id' => $earning['source_listing_id'] ?? null,
                'earned_date' => $earning['earned_date'] ?? null
            ];
        }

        // Convert earnings to Bitcoin if alternate currency is enabled AND over 1M platinum
        // convertEarningsToBitcoin has internal USE_ALT_CURRENCY check
        $earningsBreakdown = convertEarningsToBitcoin($total_copper);
        $bitcoinEarned = $earningsBreakdown['bitcoin'];
        $copperToAdd = $earningsBreakdown['copper_remainder'];
        $platinum_earned = floor($total_copper / 1000);
        $isOnline = isset($character['ingame']) && intval($character['ingame']) > 0;

        error_log("CLAIM DEBUG - Character online status: ingame_value={$character['ingame']}, isOnline=" . ($isOnline ? 'true' : 'false'));

        // Check if slot_id exists - CACHED
        $hasSlotId = $db->columnExists('character_parcels', 'slot_id');

        if ($isOnline) {
            error_log("CLAIM DEBUG - Character is ONLINE, sending money via parcel");
            // Character is online - must send parcels (database currency updates won't sync)
            if ($hasSlotId) {
                // Find next available slot
                $stmt = $conn->prepare("
                    SELECT COALESCE(MAX(slot_id), -1) + 1 as next_slot
                    FROM character_parcels
                    WHERE char_id = ?
                ");
                $stmt->execute([$character_id]);
                $slotResult = $stmt->fetch();
                $nextSlot = $slotResult ? intval($slotResult['next_slot']) : 0;

                // Send money parcel if any copper to add
                if ($copperToAdd > 0) {
                    $parcel_copper = min($copperToAdd, 2147483647); // Max signed int
                    if (USE_ALT_CURRENCY && $bitcoinEarned > 0) {
                        $platinum_remainder = $earningsBreakdown['platinum_remainder'];
                        $moneyNote = "Marketplace Earnings: {$platinum_remainder}pp (from total {$platinum_earned}pp) from " . count($earning_ids) . " sale(s). Check parcels for " . ALT_CURRENCY_NAME . "!";
                    } else {
                        $moneyNote = "Marketplace Earnings: {$platinum_earned}pp from " . count($earning_ids) . " sale(s)";
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO character_parcels (char_id, slot_id, from_name, item_id, quantity, note, sent_date)
                        VALUES (?, ?, 'Marketplace', 99990, ?, ?, NOW())
                    ");
                    $stmt->execute([$character_id, $nextSlot, $parcel_copper, $moneyNote]);
                    error_log("CLAIM DEBUG - Money parcel created: char_id={$character_id}, slot={$nextSlot}, item=99990, copper={$parcel_copper}");
                    $nextSlot++;
                }

                // Send Bitcoin parcel if any (only if alternate currency enabled)
                if (USE_ALT_CURRENCY && $bitcoinEarned > 0) {
                    $platinum_remainder = $earningsBreakdown['platinum_remainder'];
                    $bitcoinNote = "Marketplace Earnings: {$bitcoinEarned} " . ALT_CURRENCY_NAME . " (from total {$platinum_earned}pp)";

                    $stmt = $conn->prepare("
                        INSERT INTO character_parcels (char_id, slot_id, from_name, item_id, quantity, note, sent_date)
                        VALUES (?, ?, 'Marketplace', ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$character_id, $nextSlot, BITCOIN_ID, $bitcoinEarned, $bitcoinNote]);
                    error_log("Sent {$bitcoinEarned} " . ALT_CURRENCY_NAME . " via parcel to char {$character_id}");
                }
            } else {
                // No slot_id support
                if ($copperToAdd > 0) {
                    $parcel_copper = min($copperToAdd, 2147483647);
                    if (USE_ALT_CURRENCY && $bitcoinEarned > 0) {
                        $platinum_remainder = $earningsBreakdown['platinum_remainder'];
                        $moneyNote = "Marketplace Earnings: {$platinum_remainder}pp (from total {$platinum_earned}pp) from " . count($earning_ids) . " sale(s). Check parcels for " . ALT_CURRENCY_NAME . "!";
                    } else {
                        $moneyNote = "Marketplace Earnings: {$platinum_earned}pp from " . count($earning_ids) . " sale(s)";
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO character_parcels (char_id, from_name, item_id, quantity, note, sent_date)
                        VALUES (?, 'Marketplace', 99990, ?, ?, NOW())
                    ");
                    $stmt->execute([$character_id, $parcel_copper, $moneyNote]);
                }

                // Send Bitcoin parcel if any (only if alternate currency enabled)
                if (USE_ALT_CURRENCY && $bitcoinEarned > 0) {
                    $platinum_remainder = $earningsBreakdown['platinum_remainder'];
                    $bitcoinNote = "Marketplace Earnings: {$bitcoinEarned} " . ALT_CURRENCY_NAME . " (from total {$platinum_earned}pp)";

                    $stmt = $conn->prepare("
                        INSERT INTO character_parcels (char_id, from_name, item_id, quantity, note, sent_date)
                        VALUES (?, 'Marketplace', ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$character_id, BITCOIN_ID, $bitcoinEarned, $bitcoinNote]);
                    error_log("Sent {$bitcoinEarned} " . ALT_CURRENCY_NAME . " via parcel to char {$character_id}");
                }
            }
        } else {
            // Character is offline - update currency directly in database
            // Get current currency
            if ($hasCurrencyTable) {
                $stmt = $conn->prepare("
                    SELECT platinum, gold, silver, copper
                    FROM character_currency
                    WHERE id = ?
                ");
                $currencyLocation = 'character_currency';
            } else {
                $stmt = $conn->prepare("
                    SELECT platinum, gold, silver, copper
                    FROM character_data
                    WHERE id = ?
                ");
                $currencyLocation = 'character_data';
            }

            $stmt->execute([$character_id]);
            $currency = $stmt->fetch();

            if ($currency) {
                // Calculate new currency amounts with remainder platinum
                $currentTotalCopper = ($currency['platinum'] * 1000) +
                                     ($currency['gold'] * 100) +
                                     ($currency['silver'] * 10) +
                                     $currency['copper'] +
                                     $copperToAdd;

                $newPlatinum = floor($currentTotalCopper / 1000);
                $currentTotalCopper = $currentTotalCopper % 1000;
                $newGold = floor($currentTotalCopper / 100);
                $currentTotalCopper = $currentTotalCopper % 100;
                $newSilver = floor($currentTotalCopper / 10);
                $newCopper = $currentTotalCopper % 10;

                // Update currency
                error_log("Updating offline character {$character_id} currency: {$newPlatinum}pp, {$newGold}gp, {$newSilver}sp, {$newCopper}cp (added {$copperToAdd} copper from {$total_copper} total earnings)");
                $stmt = $conn->prepare("
                    UPDATE {$currencyLocation}
                    SET platinum = ?, gold = ?, silver = ?, copper = ?
                    WHERE id = ?
                ");
                $stmt->execute([$newPlatinum, $newGold, $newSilver, $newCopper, $character_id]);
                error_log("Currency updated successfully for character {$character_id}");

                // Send Bitcoin parcel if any (only if alternate currency enabled)
                // Money was already added to character currency above
                if (USE_ALT_CURRENCY && $bitcoinEarned > 0) {
                    if ($hasSlotId) {
                        // Find next available slot
                        $stmt = $conn->prepare("
                            SELECT COALESCE(MAX(slot_id), -1) + 1 as next_slot
                            FROM character_parcels
                            WHERE char_id = ?
                        ");
                        $stmt->execute([$character_id]);
                        $slotResult = $stmt->fetch();
                        $nextSlot = $slotResult ? intval($slotResult['next_slot']) : 0;

                        $platinum_remainder = $earningsBreakdown['platinum_remainder'];
                        $bitcoinNote = "Marketplace Earnings: {$bitcoinEarned} " . ALT_CURRENCY_NAME . " + {$platinum_remainder}pp (total: {$platinum_earned}pp) from " . count($earning_ids) . " sale(s). Platinum added to character.";

                        $stmt = $conn->prepare("
                            INSERT INTO character_parcels (char_id, slot_id, from_name, item_id, quantity, note, sent_date)
                            VALUES (?, ?, 'Marketplace', ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$character_id, $nextSlot, BITCOIN_ID, $bitcoinEarned, $bitcoinNote]);
                        error_log("Sent {$bitcoinEarned} " . ALT_CURRENCY_NAME . " via parcel to char {$character_id}");
                    } else {
                        // No slot_id support
                        $platinum_remainder = $earningsBreakdown['platinum_remainder'];
                        $bitcoinNote = "Marketplace Earnings: {$bitcoinEarned} " . ALT_CURRENCY_NAME . " + {$platinum_remainder}pp (total: {$platinum_earned}pp) from " . count($earning_ids) . " sale(s). Platinum added to character.";

                        $stmt = $conn->prepare("
                            INSERT INTO character_parcels (char_id, from_name, item_id, quantity, note, sent_date)
                            VALUES (?, 'Marketplace', ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$character_id, BITCOIN_ID, $bitcoinEarned, $bitcoinNote]);
                        error_log("Sent {$bitcoinEarned} " . ALT_CURRENCY_NAME . " via parcel to char {$character_id}");
                    }
                }
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

        // Commit transaction
        $conn->commit();

        $total_claimed_platinum = floor($total_copper / 1000);

        sendJSON([
            'success' => true,
            'message' => "Successfully claimed {$total_claimed_platinum}pp in earnings for {$character['name']}! " . ($isOnline ? "Money sent via parcel - check the parcel merchant!" : "Money added to your character."),
            'claimed_amount_copper' => $total_copper,
            'claimed_amount_platinum' => $total_claimed_platinum,
            'character_name' => $character['name'],
            'earnings_claimed' => count($earnings),
            'character_was_online' => $isOnline,
            'payment_method' => $isOnline ? 'parcel' : 'direct_currency_update',
            'debug_earnings' => $earnings_debug // Show which earnings were claimed
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Database error in earnings/claim-character.php: " . $e->getMessage());
    http_response_code(500);
    sendJSON(['error' => 'Database error occurred: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    error_log("Error in earnings/claim-character.php: " . $e->getMessage());
    http_response_code(500);
    sendJSON(['error' => $e->getMessage()], 500);
}
