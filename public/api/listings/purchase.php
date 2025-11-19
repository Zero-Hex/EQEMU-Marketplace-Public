<?php
/**
 * Purchase Item Endpoint - PRIMARY PURCHASE IMPLEMENTATION
 *
 * This is the MAIN purchase endpoint with full feature support:
 * - Handles both character_data and character_currency tables (auto-detects)
 * - Supports augments on purchased items
 * - Handles slot_id for parcel system (when available)
 * - Full transaction safety with FOR UPDATE locks
 * - Comprehensive error logging
 *
 * NOTE: There is also a /purchases/buy.php endpoint which is a simpler alternative
 * that only supports character_data currency storage. Use this endpoint (purchase.php)
 * for production as it supports all EQEMU server configurations.
 */

require_once '../config.php';
handleCORS();

// Require authentication
$user = requireAuth();

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['listing_id']) || !isset($input['character_id'])) {
        sendJSON(['error' => 'Missing required fields'], 400);
    }
    
    $listingId = intval($input['listing_id']);
    $buyerCharId = intval($input['character_id']);

    // Detect where currency is stored (character_data vs character_currency) - CACHED
    $hasCurrencyTable = $db->tableExists('character_currency');
    
    // Start transaction
    $conn->beginTransaction();
    
    try {
        // Get listing details
        $stmt = $conn->prepare("
            SELECT ml.*, cd.name as seller_name, cd.account_id as seller_account_id, i.name as item_name
            FROM marketplace_listings ml
            JOIN character_data cd ON ml.seller_char_id = cd.id
            JOIN items i ON ml.item_id = i.id
            WHERE ml.id = :listing_id AND ml.status = 'active'
            FOR UPDATE
        ");
        $stmt->execute([':listing_id' => $listingId]);
        $listing = $stmt->fetch();
        
        if (!$listing) {
            throw new Exception('Listing not found or no longer available');
        }
        
        // Get buyer character details (including online status)
        $stmt = $conn->prepare("
            SELECT id, name, account_id, ingame
            FROM character_data
            WHERE id = :char_id
            FOR UPDATE
        ");
        $stmt->execute([':char_id' => $buyerCharId]);
        $buyer = $stmt->fetch();

        if (!$buyer) {
            throw new Exception('Character not found');
        }

        // Verify buyer owns this character (check primary account and linked accounts)
        $buyerAccountId = intval($buyer['account_id']);
        $ownsCharacter = false;

        // Check if it's the primary account
        if ($buyerAccountId === intval($user['account_id'])) {
            $ownsCharacter = true;
        } else {
            // Check if it's a linked account
            $stmt = $conn->prepare("
                SELECT mla.id
                FROM marketplace_linked_accounts mla
                JOIN marketplace_users mu ON mla.marketplace_user_id = mu.id
                WHERE mu.account_id = :primary_account_id
                AND mla.account_id = :buyer_account_id
            ");
            $stmt->execute([
                ':primary_account_id' => $user['account_id'],
                ':buyer_account_id' => $buyerAccountId
            ]);
            if ($stmt->fetch()) {
                $ownsCharacter = true;
            }
        }

        if (!$ownsCharacter) {
            throw new Exception('You do not own this character');
        }

        $isOnline = intval($buyer['ingame']) === 1;
        error_log("Buyer character '{$buyer['name']}' online status: " . ($isOnline ? 'ONLINE' : 'OFFLINE'));

        // Prevent buying your own items
        if ($listing['seller_char_id'] == $buyerCharId) {
            throw new Exception('You cannot purchase your own listings');
        }

        // Get buyer's currency - check both possible locations
        if ($hasCurrencyTable) {
            // Currency in separate table
            $stmt = $conn->prepare("
                SELECT platinum, gold, silver, copper
                FROM character_currency
                WHERE id = :char_id
            ");
            $stmt->execute([':char_id' => $buyerCharId]);
            $currency = $stmt->fetch();
            $currencyLocation = 'character_currency';
        } else {
            // Currency in character_data
            $stmt = $conn->prepare("
                SELECT platinum, gold, silver, copper
                FROM character_data
                WHERE id = :char_id
            ");
            $stmt->execute([':char_id' => $buyerCharId]);
            $currency = $stmt->fetch();
            $currencyLocation = 'character_data';
        }

        if (!$currency || !isset($currency['platinum'])) {
            throw new Exception('Character currency data not found. Currency location: ' . $currencyLocation);
        }

        error_log("Currency stored in: " . $currencyLocation);
        error_log("Buyer current currency: " . json_encode($currency));

        // Convert all currency to copper
        $totalCopper = ($currency['platinum'] * 1000) +
                       ($currency['gold'] * 100) +
                       ($currency['silver'] * 10) +
                       $currency['copper'];

        // Calculate pending payments total
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(price_copper), 0) as pending_total
            FROM marketplace_transactions
            WHERE buyer_char_id = :char_id AND payment_status = 'pending'
        ");
        $stmt->execute([':char_id' => $buyerCharId]);
        $pendingResult = $stmt->fetch();
        $pendingCopper = intval($pendingResult['pending_total']);

        // Calculate available balance (current - pending)
        $availableCopper = $totalCopper - $pendingCopper;

        $priceCopper = intval($listing['price_copper']);
        $pricePlatinum = $priceCopper / 1000;

        // Check if this is a high-value purchase (> 1 million platinum)
        // Only use alternate currency if enabled
        $isHighValuePurchase = USE_ALT_CURRENCY && ($pricePlatinum > ALT_CURRENCY_VALUE_PLATINUM);
        $altCurrencyPayment = null;

        // Get alt currency availability for ALL purchases (may be needed for <1M too)
        // Only check if alternate currency is enabled (getTotalAltCurrency has internal check)
        $altCurrencyData = getTotalAltCurrency($conn, $buyerCharId);
        $availablePlatinum = $totalCopper / 1000;

        if ($availableCopper < $priceCopper) {
            // Insufficient platinum alone - check if alt currency can help
            error_log("Insufficient platinum ({$availablePlatinum}pp) for {$pricePlatinum}pp purchase. Checking alt currency...");
            error_log("Alt currency available - Inventory: {$altCurrencyData['inventory']}, Alternate: {$altCurrencyData['alternate']}");

            // Calculate payment with appropriate priority
            // > 1M: Alt currency first, < 1M: Platinum first
            $payment = calculateAltCurrencyPayment($priceCopper, $availablePlatinum, $altCurrencyData['total'], $isHighValuePurchase);

            if ($payment['total_sufficient']) {
                $altCurrencyPayment = $payment;
                error_log("Payment breakdown (" . $payment['payment_method'] . "): " . json_encode($payment));
            } else {
                $priceInPP = round($priceCopper / 1000, 2);
                $availableInPP = round($availableCopper / 1000, 2);
                $pendingInPP = round($pendingCopper / 1000, 2);

                if ($altCurrencyData['total'] > 0) {
                    throw new Exception("Insufficient funds. You need {$priceInPP}pp but only have {$availableInPP}pp and {$altCurrencyData['total']} " . ALT_CURRENCY_NAME . ". " . ALT_CURRENCY_NAME . " is valued at " . number_format(ALT_CURRENCY_VALUE_PLATINUM) . " platinum each. Total value insufficient.");
                } else {
                    throw new Exception("Insufficient funds. You need {$priceInPP}pp but only have {$availableInPP}pp available ({$pendingInPP}pp in pending payments). Pay your pending purchases at the Marketplace Broker NPC.");
                }
            }
        } elseif ($isHighValuePurchase && $altCurrencyData['total'] > 0) {
            // High-value purchase with sufficient platinum, but check if alt currency should be used anyway
            // For >1M purchases, we prioritize alt currency even if platinum is sufficient
            $payment = calculateAltCurrencyPayment($priceCopper, $availablePlatinum, $altCurrencyData['total'], true);
            if ($payment['alt_currency_to_deduct'] > 0) {
                $altCurrencyPayment = $payment;
                error_log("High-value purchase: Using " . ALT_CURRENCY_NAME . "-first payment even though platinum is sufficient");
                error_log("Payment breakdown: " . json_encode($payment));
            }
        }

        error_log("Purchase processing for {$priceCopper} copper. Available balance: {$availableCopper} (Total: {$totalCopper}, Pending: {$pendingCopper})");

        // Mark listing as sold (reserved for buyer)
        $stmt = $conn->prepare("
            UPDATE marketplace_listings
            SET status = 'sold', buyer_char_id = :buyer_id, purchased_date = NOW()
            WHERE id = :listing_id
        ");
        $stmt->execute([
            ':buyer_id' => $buyerCharId,
            ':listing_id' => $listingId
        ]);

        if ($isOnline) {
            // ONLINE: Create pending payment (buyer must pay at NPC)
            $stmt = $conn->prepare("
                INSERT INTO marketplace_transactions
                (listing_id, seller_char_id, buyer_char_id, item_id, quantity, price_copper, transaction_date, payment_status, reserved_date)
                VALUES (:listing_id, :seller_id, :buyer_id, :item_id, :quantity, :price, NOW(), 'pending', NOW())
            ");
            $stmt->execute([
                ':listing_id' => $listingId,
                ':seller_id' => $listing['seller_char_id'],
                ':buyer_id' => $buyerCharId,
                ':item_id' => $listing['item_id'],
                ':quantity' => $listing['quantity'],
                ':price' => $priceCopper
            ]);

            error_log("Created pending payment transaction for {$priceCopper} copper. Buyer is ONLINE and must pay at NPC.");

            $conn->commit();

            $priceInPP = round($priceCopper / 1000, 2);

            // Build message based on payment type
            $message = "Purchase reserved! You are currently online. Visit the Marketplace Broker NPC in-game to pay ";
            $transactionData = [
                'item_id' => $listing['item_id'],
                'quantity' => $listing['quantity'],
                'price_to_pay' => $priceCopper,
                'price_to_pay_pp' => $priceInPP,
                'payment_status' => 'pending',
                'currency_location' => $currencyLocation,
                'buyer_online' => true
            ];

            if (USE_ALT_CURRENCY && $altCurrencyPayment !== null && $altCurrencyPayment['alt_currency_to_deduct'] > 0) {
                if ($isHighValuePurchase) {
                    $message .= "{$priceInPP} platinum (will use " . ALT_CURRENCY_NAME . " first) and receive your item. Say 'pending' to the NPC.";
                } else {
                    $message .= "{$priceInPP} platinum (will use " . ALT_CURRENCY_NAME . " if needed) and receive your item. Say 'pending' to the NPC.";
                }
                $transactionData['alt_currency_may_be_needed'] = $altCurrencyPayment['alt_currency_to_deduct'];
                $transactionData['platinum_may_be_needed'] = $altCurrencyPayment['platinum_to_deduct'];
                $transactionData['alt_currency_value_pp'] = ALT_CURRENCY_VALUE_PLATINUM;
                $transactionData['payment_method'] = $altCurrencyPayment['payment_method'];
            } else {
                $message .= "{$priceInPP} platinum and receive your item. Say 'pending' to the NPC.";
            }

            sendJSON([
                'success' => true,
                'message' => $message,
                'transaction' => $transactionData
            ]);
        } else {
            // OFFLINE: Auto-deduct money and send item via parcel
            error_log("Buyer is OFFLINE. Processing automatic payment and delivery.");

            $platinumRefund = 0;
            $altCurrencyUsed = 0;
            $platinumDeducted = 0;

            // Handle Alt currency payment if needed
            if ($altCurrencyPayment !== null && $altCurrencyPayment['alt_currency_to_deduct'] > 0) {
                error_log("Processing mixed payment (Alt currency + Platinum): Method = {$altCurrencyPayment['payment_method']}");

                // Execute Alt currency payment
                $altCurrencyResult = executeAltCurrencyPayment($conn, $buyerCharId, $altCurrencyPayment['alt_currency_to_deduct']);

                if (!$altCurrencyResult['success']) {
                    throw new Exception("Failed to deduct alt currency: " . ($altCurrencyResult['error'] ?? 'Unknown error'));
                }

                $altCurrencyUsed = $altCurrencyResult['total_deducted'];
                $platinumToDeduct = $altCurrencyPayment['platinum_to_deduct'];
                $platinumRefund = $altCurrencyPayment['platinum_to_refund'];

                // Calculate remaining platinum after deduction
                $remainingCopper = $totalCopper - ($platinumToDeduct * 1000);
                $newPlatinum = floor($remainingCopper / 1000) + floor($platinumRefund);
                $remainingCopper = $remainingCopper % 1000;
                $newGold = floor($remainingCopper / 100);
                $remainingCopper = $remainingCopper % 100;
                $newSilver = floor($remainingCopper / 10);
                $newCopper = $remainingCopper % 10;

                $platinumDeducted = $platinumToDeduct;

                error_log("Alt currency payment successful: Used {$altCurrencyUsed} alt currency (Inventory: {$altCurrencyResult['from_inventory']}, Alternate: {$altCurrencyResult['from_alternate']}), deducted {$platinumToDeduct}pp, refunded {$platinumRefund}pp");

                // Update buyer's currency
                $updateTable = ($currencyLocation === 'character_currency') ? 'character_currency' : 'character_data';
                $stmt = $conn->prepare("
                    UPDATE {$updateTable}
                    SET platinum = :platinum, gold = :gold, silver = :silver, copper = :copper
                    WHERE id = :char_id
                ");
                $stmt->execute([
                    ':platinum' => $newPlatinum,
                    ':gold' => $newGold,
                    ':silver' => $newSilver,
                    ':copper' => $newCopper,
                    ':char_id' => $buyerCharId
                ]);

                error_log("Final balance: {$newPlatinum}pp {$newGold}gp {$newSilver}sp {$newCopper}cp");
            } else {
                // Standard platinum-only payment
                $newCopper = $totalCopper - $priceCopper;

                // Convert back to platinum/gold/silver/copper
                $newPlatinum = floor($newCopper / 1000);
                $newCopper -= ($newPlatinum * 1000);
                $newGold = floor($newCopper / 100);
                $newCopper -= ($newGold * 100);
                $newSilver = floor($newCopper / 10);
                $newCopper -= ($newSilver * 10);

                // Update buyer's currency
                $updateTable = ($currencyLocation === 'character_currency') ? 'character_currency' : 'character_data';
                $stmt = $conn->prepare("
                    UPDATE {$updateTable}
                    SET platinum = :platinum, gold = :gold, silver = :silver, copper = :copper
                    WHERE id = :char_id
                ");
                $stmt->execute([
                    ':platinum' => $newPlatinum,
                    ':gold' => $newGold,
                    ':silver' => $newSilver,
                    ':copper' => $newCopper,
                    ':char_id' => $buyerCharId
                ]);

                error_log("Deducted {$priceCopper} copper from buyer. New balance: {$newPlatinum}pp {$newGold}gp {$newSilver}sp {$newCopper}cp");
            }

            // Seller payment is handled through earnings system - do NOT pay seller here
            // Seller will receive payment when they claim earnings via the website
            error_log("Seller will receive {$priceCopper} copper ({$pricePlatinum}pp) when they claim earnings");

            // Check if augment and slot_id columns exist in character_parcels
            $hasAugmentColumns = $db->columnExists('character_parcels', 'augslot1');
            $hasSlotIdColumn = $db->columnExists('character_parcels', 'slot_id');

            // Get next available slot_id for this character
            $slotId = 0;
            if ($hasSlotIdColumn) {
                $stmt = $conn->prepare("SELECT COALESCE(MAX(slot_id), -1) + 1 as next_slot FROM character_parcels WHERE char_id = :char_id");
                $stmt->execute([':char_id' => $buyerCharId]);
                $result = $stmt->fetch();
                $slotId = intval($result['next_slot']);
                error_log("Using slot_id {$slotId} for parcel to character {$buyerCharId}");
            }

            // Send item to buyer via parcel
            $note = "Marketplace purchase from " . $listing['seller_name'];

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
                    ':char_id' => $buyerCharId,
                    ':slot_id' => $slotId,
                    ':from_name' => 'Marketplace',
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
                    ':char_id' => $buyerCharId,
                    ':from_name' => 'Marketplace',
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
                    ':char_id' => $buyerCharId,
                    ':slot_id' => $slotId,
                    ':from_name' => 'Marketplace',
                    ':item_id' => $listing['item_id'],
                    ':quantity' => $listing['quantity'],
                    ':note' => $note
                ]);
            } else {
                // Basic parcel - no augments, no slot_id
                $stmt = $conn->prepare("
                    INSERT INTO character_parcels (char_id, from_name, item_id, quantity, note, sent_date)
                    VALUES (:char_id, :from_name, :item_id, :quantity, :note, NOW())
                ");
                $stmt->execute([
                    ':char_id' => $buyerCharId,
                    ':from_name' => 'Marketplace',
                    ':item_id' => $listing['item_id'],
                    ':quantity' => $listing['quantity'],
                    ':note' => $note
                ]);
            }

            error_log("Sent item to buyer via parcel");

            // Create completed transaction record
            $stmt = $conn->prepare("
                INSERT INTO marketplace_transactions
                (listing_id, seller_char_id, buyer_char_id, item_id, quantity, price_copper, transaction_date, payment_status, payment_date)
                VALUES (:listing_id, :seller_id, :buyer_id, :item_id, :quantity, :price, NOW(), 'paid', NOW())
            ");
            $stmt->execute([
                ':listing_id' => $listingId,
                ':seller_id' => $listing['seller_char_id'],
                ':buyer_id' => $buyerCharId,
                ':item_id' => $listing['item_id'],
                ':quantity' => $listing['quantity'],
                ':price' => $priceCopper
            ]);

            // Create seller earnings record
            // Check if earnings already exist for this listing
            $checkStmt = $conn->prepare("
                SELECT COUNT(*) as count FROM marketplace_seller_earnings
                WHERE source_listing_id = ? AND seller_char_id = ?
            ");
            $checkStmt->execute([$listingId, $listing['seller_char_id']]);
            $existing = $checkStmt->fetch();

            $stmt = $conn->prepare("
                INSERT INTO marketplace_seller_earnings
                (seller_char_id, amount_copper, earned_date, source_listing_id, notes, claimed)
                VALUES (:seller_id, :amount, NOW(), :listing_id, :notes, FALSE)
            ");
            $stmt->execute([
                ':seller_id' => $listing['seller_char_id'],
                ':amount' => $priceCopper,
                ':listing_id' => $listingId,
                ':notes' => 'Sale of ' . $listing['item_name']
            ]);
            $earningsId = $conn->lastInsertId();

            $conn->commit();

            $priceInPP = round($priceCopper / 1000, 2);

            // Build message based on payment type
            $message = "Purchase complete! ";
            $transactionData = [
                'item_id' => $listing['item_id'],
                'quantity' => $listing['quantity'],
                'price_paid' => $priceCopper,
                'price_paid_pp' => $priceInPP,
                'payment_status' => 'paid',
                'currency_location' => $currencyLocation,
                'buyer_online' => false,
                'delivery_method' => 'parcel'
            ];

            if ($altCurrencyUsed > 0) {
                if ($platinumRefund > 0) {
                    $message .= "Paid with {$altCurrencyUsed} alt currency and {$platinumDeducted}pp. Refunded {$platinumRefund}pp. The item has been sent via parcel - check your parcels in-game!";
                } else {
                    $message .= "Paid with {$altCurrencyUsed} alt currency and {$platinumDeducted}pp. The item has been sent via parcel - check your parcels in-game!";
                }
                $transactionData['alt_currency_used'] = $altCurrencyUsed;
                $transactionData['platinum_deducted'] = $platinumDeducted;
                $transactionData['platinum_refunded'] = $platinumRefund;
                $transactionData['payment_method'] = $altCurrencyPayment['payment_method'];
            } else {
                $message .= "{$priceInPP} platinum has been deducted from your character. The item has been sent via parcel - check your parcels in-game!";
            }

            sendJSON([
                'success' => true,
                'message' => $message,
                'transaction' => $transactionData
            ]);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Purchase error: " . $e->getMessage());
    sendJSON(['error' => $e->getMessage()], 500);
}