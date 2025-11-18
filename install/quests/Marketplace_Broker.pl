# Marketplace Broker NPC - Accepts items for marketplace listing and WTB fulfillment
# Place this file in your quests/global/ directory
# Create an NPC with this name or use #npcspawn Marketplace_Broker

# ============================================================================
# MARKETPLACE CONFIGURATION
# ============================================================================
# Configure these settings to match your api/config.php settings

# Alternate Currency Configuration (Optional High-Value Currency System)
# Set to 0 to use platinum-only marketplace (default)
# Set to 1 to enable custom alternate currency for high-value transactions
our $USE_ALT_CURRENCY = 0;  # Default: platinum-only marketplace

# If $USE_ALT_CURRENCY is 1, configure these settings:
our $ALT_CURRENCY_ITEM_ID = 147623;          # Item ID for your alternate currency
our $ALT_CURRENCY_VALUE_PP = 1000000;        # How much platinum = 1 alt currency
our $ALT_CURRENCY_NAME = 'Bitcoin';          # Display name for your alternate currency

# Legacy variable names for backwards compatibility
our $BITCOIN_ID = $ALT_CURRENCY_ITEM_ID;
our $BITCOIN_VALUE_PP = $ALT_CURRENCY_VALUE_PP;

# ============================================================================
# END CONFIGURATION - Do not modify below this line
# ============================================================================

sub EVENT_ITEM {
    my $item_id = 0;
    my $item_name = "";
    my $quantity = 0;
    my $is_tradable = 1;
    my $item_found = 0;

    # Create database connection
    my $db = Database::new(Database::Content);

    # Build the items hash for CheckHandin validation
    my %items_to_check;

    # Process each item slot that might have been handed in
    foreach my $slot_name (keys %itemcount) {
        next if $itemcount{$slot_name} == 0;

        # Get item details from the items table
        my $query = $db->prepare("SELECT id, Name, nodrop, norent, loregroup FROM items WHERE id = ?");
        $query->execute($slot_name);

        if (my $row = $query->fetch_hashref()) {
            $item_found = 1;
            $item_id = $row->{id};
            $item_name = $row->{Name};

            # Check if item is NO TRADE (nodrop = 0 means NO TRADE in EQEmu)
            if ($row->{nodrop} == 0) {
                quest::say("I'm sorry, but I cannot accept $item_name as it is marked NO TRADE. Here, take it back.");
                $is_tradable = 0;
                $query->close();
                last;
            }

            # Check if item is NO RENT (temporary item that disappears on logout)
            if ($row->{norent} == 0) {
                quest::say("I'm sorry, but I cannot accept $item_name as it is a temporary item. Here, take it back.");
                $is_tradable = 0;
                $query->close();
                last;
            }

            $quantity = $itemcount{$slot_name};
            $items_to_check{$item_id} = $quantity;
        } else {
            # Item not found in database
            $client->Message(15, "[DEBUG] Item ID $slot_name not found in items table.");
        }
        $query->close();
    }

    # Close database connection
    $db->close();

    # Return all items if not tradable
    if (!$is_tradable) {
        plugin::return_items(\%itemcount);
        return;
    }

    # If no valid item found, return all
    if (!$item_found || $item_id == 0) {
        quest::say("I don't recognize this item. Here, take it back.");
        plugin::return_items(\%itemcount);
        return;
    }

    # Get item instances for proper handling
    my @inst = (
        plugin::val('$item1_inst'),
        plugin::val('$item2_inst'),
        plugin::val('$item3_inst'),
        plugin::val('$item4_inst')
    );

    # CRITICAL: Use CheckHandin to validate the item handoff
    # This prevents duplication by ensuring items are properly consumed
    # Use empty %needs hash to accept ALL items without quantity matching
    my %needs = ();

    if ($npc->CheckHandin($client, \%items_to_check, \%needs, @inst)) {
        # Items successfully validated and consumed by CheckHandin
        my $char_id = $client->CharacterID();

        # Store item data in global hash for this character
        $qglobals{$char_id . "_pending_item_id"} = $item_id;
        $qglobals{$char_id . "_pending_item_name"} = $item_name;
        $qglobals{$char_id . "_pending_quantity"} = $quantity;

        # Debug message
        $client->Message(15, "[DEBUG] Accepting $item_name (ID: $item_id, Qty: $quantity). Item consumed, checking for WTB orders...");

        # Check for WTB orders for this item
        if (has_wtb_orders($item_id, $char_id)) {
            show_wtb_orders($client, $item_id, $char_id, $item_name);
            quest::say("Or say '" . quest::saylink("list normally", 1) . "' to list it on the marketplace instead.");
            quest::say("Say '" . quest::saylink("cancel", 1) . "' anytime to get your item back!");
        } else {
            quest::say("Excellent! I'll list your $item_name (quantity: $quantity) on the marketplace. What price would you like to set in [" . quest::saylink("platinum", 1) . "]? For example, say 'price 100' for 100 platinum.");
            quest::say("Say '" . quest::saylink("cancel", 1) . "' if you change your mind and want your item back!");
        }

        # Set a timer to clear this data after 5 minutes if no response
        quest::settimer("clear_pending_" . $char_id, 300);
    } else {
        # CheckHandin failed - return all items
        plugin::return_items(\%itemcount);
    }
}

sub EVENT_TIMER {
    # Extract character ID from timer name
    if ($timer =~ /^clear_pending_(\d+)$/) {
        my $char_id = $1;
        quest::stoptimer($timer);

        # Return the item if still pending
        if (defined $qglobals{$char_id . "_pending_item_id"}) {
            my $item_id = $qglobals{$char_id . "_pending_item_id"};
            my $quantity = $qglobals{$char_id . "_pending_quantity"};

            quest::summonitem($item_id, $quantity);

            # Clear the pending data
            delete $qglobals{$char_id . "_pending_item_id"};
            delete $qglobals{$char_id . "_pending_item_name"};
            delete $qglobals{$char_id . "_pending_quantity"};
        }
    }
}

sub EVENT_SAY {
    my $char_id = $client->CharacterID();

    # Handle hail at the top
    if ($text =~ /hail/i) {
        quest::say("Greetings, $name! I am the Marketplace Broker. I offer several services:");
        quest::say("[" . quest::saylink("list items") . "] - Sell items on the marketplace");
        quest::say("[" . quest::saylink("wtb") . "] - Learn about Want to Buy orders");
        quest::say("[" . quest::saylink("pending") . "] - Pay for items you purchased online");
        quest::say("[" . quest::saylink("cancel") . "] - Get your item back if you change your mind");
        quest::say("Simply hand me any tradable item to get started!");
        return;
    }

    # Handle WTB inquiry
    if ($text =~ /wtb/i) {
        quest::say("Want to Buy (WTB) orders are created by players looking for specific items:");
        quest::say("• When you hand me an item, I automatically check if anyone wants to buy it");
        quest::say("• If there are active WTB orders, I'll show you the best offers");
        quest::say("• You can choose to fulfill a WTB order for instant payment");
        quest::say("• Or say '" . quest::saylink("list normally", 1) . "' to skip WTB and list on the marketplace instead");
        quest::say("WTB orders give you immediate payment, while marketplace listings pay when they sell!");
        return;
    }

    if ($text =~ /list items/i || $text =~ /sell/i) {
        quest::say("To list items on the marketplace:");
        quest::say("1. Hand me any tradable item (NO TRADE items will be returned)");
        quest::say("2. I'll check if anyone wants to buy it via WTB orders");
        quest::say("3. You can fulfill a WTB order for instant payment, or list it normally");
        quest::say("4. If listing normally, tell me the price in platinum (example: 'price 100')");
        quest::say("5. Your item will appear on the marketplace website for buyers");
        quest::say("• You can say '" . quest::saylink("cancel", 1) . "' anytime to get your item back!");
        quest::say("• Unsold items can be cancelled on the website and returned via parcel");
        return;
    }

    # Handle WTB fulfillment
    if ($text =~ /fulfill\s+(\d+)/i) {
        my $order_num = $1;

        # Check if player has pending item
        if (!defined $qglobals{$char_id . "_pending_item_id"}) {
            quest::say("You don't have any items pending. Hand me an item first.");
            return;
        }

        my $item_id = $qglobals{$char_id . "_pending_item_id"};
        fulfill_wtb_order($client, $char_id, $item_id, $order_num);
        return;
    }

    # Handle "list normally" - skip WTB and go to regular listing
    if ($text =~ /list normally/i) {
        if (defined $qglobals{$char_id . "_pending_item_id"}) {
            my $item_name = $qglobals{$char_id . "_pending_item_name"};
            my $quantity = $qglobals{$char_id . "_pending_quantity"};

            quest::say("No problem! I'll list your $item_name (quantity: $quantity) on the marketplace. What price would you like to set in platinum? For example, say 'price 100' for 100 platinum.");
            quest::say("Say '" . quest::saylink("cancel", 1) . "' if you change your mind and want your item back!");

            # Clear WTB orders but keep pending item
            delete $qglobals{$char_id . "_wtb_orders"};
        } else {
            quest::say("You don't have any items pending. Hand me an item to list it.");
        }
        return;
    }

    # Handle pending payments check
    if ($text =~ /pending/i || $text =~ /pay$/i || $text =~ /purchases/i) {
        my $char_id = $client->CharacterID();

        # Create database connection
        my $db = Database::new(Database::Content);

        # Get pending payments directly from database
        my $query = $db->prepare("
            SELECT
                mt.id as transaction_id,
                mt.price_copper,
                i.name as item_name
            FROM marketplace_transactions mt
            JOIN items i ON mt.item_id = i.id
            WHERE mt.buyer_char_id = ?
            AND mt.payment_status = 'pending'
            ORDER BY mt.transaction_date ASC
        ");
        $query->execute($char_id);

        my @pending_payments;
        my $total_copper = 0;

        while (my $row = $query->fetch_hashref()) {
            push @pending_payments, {
                transaction_id => $row->{transaction_id},
                item_name => $row->{item_name},
                price_copper => $row->{price_copper},
                price_platinum => int($row->{price_copper} / 1000)
            };
            $total_copper += $row->{price_copper};
        }
        $query->close();
        $db->close();

        my $count = scalar @pending_payments;

        if ($count > 0) {
            my $total_pp = int($total_copper / 1000);

            quest::say("You have $count pending purchase(s) totaling $total_pp platinum:");

            my $index = 1;
            foreach my $payment (@pending_payments) {
                my $item_name = $payment->{item_name};
                my $price_pp = $payment->{price_platinum};
                my $trans_id = $payment->{transaction_id};

                # Create clickable pay link
                my $pay_link = quest::saylink("pay $trans_id", 1);
                quest::say("[$index] $item_name - $price_pp platinum " . $pay_link);
                $index++;
            }

            quest::say("Click the " . quest::saylink("pay", 1) . " link next to each item to complete your purchase.");
        } else {
            quest::say("You have no pending purchases. Visit the marketplace website to buy items!");
        }
        return;
    }

    # Handle payment
    if ($text =~ /pay\s+(\d+)/i) {
        my $trans_id = $1;
        my $char_id = $client->CharacterID();

        # Create database connection
        my $db = Database::new(Database::Content);

        # Get transaction details directly from database
        my $query = $db->prepare("
            SELECT
                mt.id, mt.listing_id, mt.seller_char_id, mt.buyer_char_id,
                mt.item_id, mt.price_copper, mt.payment_status,
                ml.quantity, ml.augment_1, ml.augment_2, ml.augment_3,
                ml.augment_4, ml.augment_5, ml.augment_6,
                i.name as item_name,
                cd.name as seller_name
            FROM marketplace_transactions mt
            JOIN marketplace_listings ml ON mt.listing_id = ml.id
            JOIN items i ON mt.item_id = i.id
            JOIN character_data cd ON mt.seller_char_id = cd.id
            WHERE mt.id = ?
            AND mt.buyer_char_id = ?
            AND mt.payment_status = 'pending'
        ");
        $query->execute($trans_id, $char_id);

        my $transaction = $query->fetch_hashref();
        $query->close();

        if (!$transaction) {
            $db->close();
            quest::say("Transaction ID $trans_id not found in your pending purchases. Say 'pending' to see your purchases.");
            return;
        }

        my $price_copper = $transaction->{price_copper};
        my $price_pp = int($price_copper / 1000);
        my $item_name = $transaction->{item_name};
        my $item_id = $transaction->{item_id};
        my $quantity = $transaction->{quantity};
        my $seller_name = $transaction->{seller_name};
        my $seller_char_id = $transaction->{seller_char_id};
        my $listing_id = $transaction->{listing_id};

        # Use global alternate currency configuration from top of file
        # Check if this is a high-value purchase (> 1 million platinum)
        # Only use alternate currency if enabled
        my $is_high_value = $USE_ALT_CURRENCY && ($price_pp > $BITCOIN_VALUE_PP);
        my $bitcoin_used = 0;
        my $platinum_used = 0;
        my $platinum_refund = 0;
        my $payment_success = 0;

        # Get current platinum and Bitcoin (only if alternate currency enabled)
        my $current_platinum = $client->GetCarriedPlatinum();
        my $bitcoin_inventory = 0;
        my $bitcoin_alternate = 0;
        my $total_bitcoin = 0;

        if ($USE_ALT_CURRENCY) {
            $bitcoin_inventory = get_bitcoin_from_inventory($client, $char_id, $db);
            $bitcoin_alternate = get_bitcoin_from_alternate($client, $char_id, $db);
            $total_bitcoin = $bitcoin_inventory + $bitcoin_alternate;
            $client->Message(15, "[DEBUG] Available: $current_platinum pp, $total_bitcoin $ALT_CURRENCY_NAME (Inventory: $bitcoin_inventory, Alternate: $bitcoin_alternate)");
        } else {
            $client->Message(15, "[DEBUG] Available: $current_platinum pp (platinum-only mode)");
        }

        if ($is_high_value) {
            # HIGH-VALUE (>1M platinum): Bitcoin FIRST, then platinum for remainder
            $client->Message(15, "[DEBUG] High-value purchase ($price_pp pp). Using Bitcoin-first payment.");

            # Calculate how much Bitcoin we need
            my $bitcoin_needed = int($price_pp / $BITCOIN_VALUE_PP);
            my $remainder_after_bitcoin = $price_pp - ($bitcoin_needed * $BITCOIN_VALUE_PP);

            if ($total_bitcoin >= $bitcoin_needed) {
                # We have enough Bitcoin for the bulk
                # Check if platinum covers the remainder
                if ($current_platinum >= $remainder_after_bitcoin) {
                    # Perfect - use Bitcoin + platinum
                    my $bitcoin_deducted = deduct_bitcoin($client, $char_id, $db, $bitcoin_needed, $bitcoin_inventory, $bitcoin_alternate);

                    if ($bitcoin_deducted == $bitcoin_needed) {
                        my $platinum_copper = $remainder_after_bitcoin * 1000;
                        if ($client->TakeMoneyFromPP($platinum_copper, 1)) {
                            $bitcoin_used = $bitcoin_needed;
                            $platinum_used = $remainder_after_bitcoin;
                            $payment_success = 1;
                            quest::say("Payment received! You paid with $bitcoin_used $ALT_CURRENCY_NAME and $platinum_used platinum.");
                        } else {
                            quest::say("Error deducting platinum. Refunding $ALT_CURRENCY_NAME...");
                            # Refund Bitcoin via SummonItem
                            quest::summonitem($BITCOIN_ID, $bitcoin_deducted);
                        }
                    }
                } else {
                    # Not enough platinum for remainder, need one more Bitcoin
                    $bitcoin_needed++;
                    if ($total_bitcoin >= $bitcoin_needed) {
                        my $bitcoin_deducted = deduct_bitcoin($client, $char_id, $db, $bitcoin_needed, $bitcoin_inventory, $bitcoin_alternate);

                        if ($bitcoin_deducted == $bitcoin_needed) {
                            # Calculate refund
                            $platinum_refund = ($bitcoin_needed * $BITCOIN_VALUE_PP) - $price_pp;
                            my $refund_copper = $platinum_refund * 1000;
                            give_money($refund_copper);

                            $bitcoin_used = $bitcoin_needed;
                            $platinum_used = 0;
                            $payment_success = 1;
                            quest::say("Payment received! You paid with $bitcoin_used $ALT_CURRENCY_NAME. Refunded $platinum_refund platinum.");
                        }
                    } else {
                        quest::say("You don't have enough funds. You need $bitcoin_needed $ALT_CURRENCY_NAME but only have $total_bitcoin.");
                    }
                }
            } else {
                # Not enough Bitcoin, use what we have + platinum
                my $bitcoin_value_provided = $total_bitcoin * $BITCOIN_VALUE_PP;
                my $platinum_still_needed = $price_pp - $bitcoin_value_provided;

                if ($current_platinum >= $platinum_still_needed) {
                    my $bitcoin_deducted = deduct_bitcoin($client, $char_id, $db, $total_bitcoin, $bitcoin_inventory, $bitcoin_alternate);
                    my $platinum_copper = $platinum_still_needed * 1000;

                    if ($bitcoin_deducted == $total_bitcoin && $client->TakeMoneyFromPP($platinum_copper, 1)) {
                        $bitcoin_used = $total_bitcoin;
                        $platinum_used = $platinum_still_needed;
                        $payment_success = 1;
                        quest::say("Payment received! You paid with $bitcoin_used Bitcoin and $platinum_used platinum.");
                    }
                } else {
                    quest::say("You don't have enough funds. You need $price_pp pp but only have $current_platinum pp and $total_bitcoin $ALT_CURRENCY_NAME.");
                }
            }
        } else {
            # LOW-VALUE (<1M platinum): Platinum FIRST, then Bitcoin if needed
            $client->Message(15, "[DEBUG] Standard purchase ($price_pp pp). Using platinum-first payment.");

            my $current_copper = $current_platinum * 1000;

            if ($current_copper >= $price_copper) {
                # Sufficient platinum alone
                $payment_success = $client->TakeMoneyFromPP($price_copper, 1);
                $platinum_used = $price_pp;
                $client->Message(15, "[DEBUG] Paid with platinum only.") if $payment_success;
            } elsif ($total_bitcoin > 0) {
                # Not enough platinum, check Bitcoin
                my $platinum_shortfall = $price_pp - $current_platinum;
                my $bitcoin_needed = int(($platinum_shortfall + $BITCOIN_VALUE_PP - 1) / $BITCOIN_VALUE_PP); # Ceiling

                if ($total_bitcoin >= $bitcoin_needed) {
                    # Take all platinum first
                    if ($current_platinum > 0) {
                        $client->TakeMoneyFromPP($current_copper, 1);
                    }

                    # Deduct Bitcoin
                    my $bitcoin_deducted = deduct_bitcoin($client, $char_id, $db, $bitcoin_needed, $bitcoin_inventory, $bitcoin_alternate);

                    if ($bitcoin_deducted == $bitcoin_needed) {
                        $bitcoin_used = $bitcoin_needed;
                        $platinum_used = $current_platinum;

                        # Calculate refund
                        my $total_paid_value = $current_platinum + ($bitcoin_needed * $BITCOIN_VALUE_PP);
                        $platinum_refund = $total_paid_value - $price_pp;

                        if ($platinum_refund > 0) {
                            my $refund_copper = $platinum_refund * 1000;
                            give_money($refund_copper);
                        }

                        $payment_success = 1;
                        if ($platinum_refund > 0) {
                            quest::say("Payment received! You paid with $platinum_used platinum and $bitcoin_used $ALT_CURRENCY_NAME. Refunded $platinum_refund platinum.");
                        } else {
                            quest::say("Payment received! You paid with $platinum_used platinum and $bitcoin_used $ALT_CURRENCY_NAME.");
                        }
                    } else {
                        quest::say("Error deducting $ALT_CURRENCY_NAME. Refunding platinum...");
                        if ($current_platinum > 0) {
                            give_money($current_copper);
                        }
                    }
                } else {
                    quest::say("You don't have enough funds. You need $price_pp pp but only have $current_platinum pp and $total_bitcoin $ALT_CURRENCY_NAME.");
                }
            } else {
                quest::say("You don't have enough platinum. You need $price_pp platinum.");
            }
        }

        # Process transaction if payment successful
        if ($payment_success) {
            # Money taken successfully, now complete the transaction

            # Send item to buyer via parcel system
            my $note = "Purchased from marketplace - Seller: " . $seller_name;

            # Find next available slot
            my $slot_query = $db->prepare("
                SELECT COALESCE(MAX(slot_id), -1) + 1 as next_slot
                FROM character_parcels
                WHERE char_id = ?
            ");
            $slot_query->execute($char_id);
            my $slot_result = $slot_query->fetch_hashref();
            my $next_slot = $slot_result ? $slot_result->{next_slot} : 0;
            $slot_query->close();

            my $parcel_query = $db->prepare("
                INSERT INTO character_parcels
                (char_id, slot_id, from_name, item_id, quantity, note, sent_date,
                 aug_slot_1, aug_slot_2, aug_slot_3, aug_slot_4, aug_slot_5, aug_slot_6)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?)
            ");

            my $aug1 = $transaction->{augment_1} || 0;
            my $aug2 = $transaction->{augment_2} || 0;
            my $aug3 = $transaction->{augment_3} || 0;
            my $aug4 = $transaction->{augment_4} || 0;
            my $aug5 = $transaction->{augment_5} || 0;
            my $aug6 = $transaction->{augment_6} || 0;

            $parcel_query->execute(
                $char_id, $next_slot, $seller_name, $item_id, $quantity, $note,
                $aug1, $aug2, $aug3, $aug4, $aug5, $aug6
            );
            $parcel_query->close();

            # Check if earnings already exist for this transaction (safety check)
            my $earnings_check_query = $db->prepare("
                SELECT COUNT(*) as count
                FROM marketplace_seller_earnings
                WHERE seller_char_id = ?
                AND source_listing_id = ?
                AND claimed = FALSE
            ");
            $earnings_check_query->execute($seller_char_id, $listing_id);
            my $earnings_check_result = $earnings_check_query->fetch_hashref();
            my $existing_earnings = $earnings_check_result->{count} || 0;
            $earnings_check_query->close();

            if ($existing_earnings == 0) {
                # Create earnings record (only if doesn't exist)
                my $earnings_query = $db->prepare("
                    INSERT INTO marketplace_seller_earnings
                    (seller_char_id, amount_copper, source_listing_id, earned_date, claimed)
                    VALUES (?, ?, ?, NOW(), FALSE)
                ");
                $earnings_query->execute($seller_char_id, $price_copper, $listing_id);
                $earnings_query->close();
                $client->Message(15, "[DEBUG] Created earnings record for seller: {$price_copper} copper");
            } else {
                $client->Message(15, "[DEBUG] Earnings already exist for this sale (count: $existing_earnings), skipping creation");
            }

            # Mark transaction as paid
            my $update_query = $db->prepare("
                UPDATE marketplace_transactions
                SET payment_status = 'paid', payment_date = NOW()
                WHERE id = ?
            ");
            $update_query->execute($trans_id);
            $update_query->close();

            $db->close();

            quest::say("Payment received! Your $item_name has been sent via parcel. Check any 'Parcels and General Supplies' merchant to collect it. The seller will receive payment when they claim their earnings on the website.");
            $client->Message(15, "[DEBUG] Payment completed successfully for transaction $trans_id");
        } else {
            $db->close();
            quest::say("You don't have enough platinum to pay for this item. You need $price_pp platinum.");
        }
        return;
    }

    # Check if player is setting a price for pending item
    if (defined $qglobals{$char_id . "_pending_item_id"} && $text =~ /price\s+(\d+)/i) {
        my $price_platinum = $1;

        if ($price_platinum <= 0) {
            quest::say("The price must be greater than 0 platinum. Please try again with 'price <amount>'.");
            return;
        }

        if ($price_platinum > 10000000000000) {
            quest::say("That price seems too high! Please set a price under 10 trillion platinum.");
            return;
        }

        my $item_id = $qglobals{$char_id . "_pending_item_id"};
        my $item_name = $qglobals{$char_id . "_pending_item_name"};
        my $quantity = $qglobals{$char_id . "_pending_quantity"};
        my $price_copper = $price_platinum * 1000;  # Convert to copper

        $client->Message(15, "[DEBUG] Attempting to list $item_name (ID: $item_id, Qty: $quantity) for $price_platinum pp.");

        # Create database connection
        my $db = Database::new(Database::Content);

        # Insert into marketplace_listings
        my $insert_query = $db->prepare("INSERT INTO marketplace_listings (
            seller_char_id, item_id, quantity, price_copper,
            listed_date, status, charges
        ) VALUES (?, ?, ?, ?, NOW(), 'active', ?)");

        my $success = $insert_query->execute($char_id, $item_id, $quantity, $price_copper, $quantity);

        if ($success) {
            $client->Message(15, "[DEBUG] Database insert successful. Item listed.");
            quest::say("Perfect! Your $item_name has been listed on the marketplace for $price_platinum platinum. Buyers can now purchase it through the marketplace website. You will receive your payment when it sells, which you can claim on the website.");

            # Clear the pending data
            delete $qglobals{$char_id . "_pending_item_id"};
            delete $qglobals{$char_id . "_pending_item_name"};
            delete $qglobals{$char_id . "_pending_quantity"};
            delete $qglobals{$char_id . "_wtb_orders"};

            # Stop the timeout timer
            quest::stoptimer("clear_pending_" . $char_id);
        }

        $insert_query->close();
        $db->close();
        return;
    }

    # If player has pending item but didn't use proper format
    if (defined $qglobals{$char_id . "_pending_item_id"} && $text =~ /\d+/) {
        quest::say("Please use the format: 'price <amount>' where amount is in platinum. For example: 'price 100' for 100 platinum.");
        return;
    }

    if ($text =~ /cancel/i && defined $qglobals{$char_id . "_pending_item_id"}) {
        my $item_id = $qglobals{$char_id . "_pending_item_id"};
        my $quantity = $qglobals{$char_id . "_pending_quantity"};

        quest::say("No problem! Here's your item back.");
        quest::summonitem($item_id, $quantity);

        # Clear the pending data
        delete $qglobals{$char_id . "_pending_item_id"};
        delete $qglobals{$char_id . "_pending_item_name"};
        delete $qglobals{$char_id . "_pending_quantity"};
        delete $qglobals{$char_id . "_wtb_orders"};

        quest::stoptimer("clear_pending_" . $char_id);
    }
}

# ==============================================================================
# WTB (Want to Buy) System Functions
# ==============================================================================

sub has_wtb_orders {
    my ($item_id, $char_id) = @_;

    # Create database connection
    my $db = Database::new(Database::Content);

    # Query active WTB orders for this item
    my $query = $db->prepare("
        SELECT COUNT(*) as order_count
        FROM marketplace_wtb
        WHERE status = 'active'
        AND item_id = ?
        AND quantity_fulfilled < quantity_wanted
        AND buyer_char_id != ?
    ");

    $query->execute($item_id, $char_id);
    my $result = $query->fetch_hashref();
    $query->close();
    $db->close();

    return $result && $result->{order_count} > 0;
}

sub show_wtb_orders {
    my ($client, $item_id, $char_id, $item_name) = @_;

    # Create database connection
    my $db = Database::new(Database::Content);

    # Query active WTB orders for this item
    my $query = $db->prepare("
        SELECT
            w.id,
            w.buyer_char_id,
            w.quantity_wanted,
            w.quantity_fulfilled,
            (w.quantity_wanted - w.quantity_fulfilled) as quantity_remaining,
            w.price_per_unit_copper,
            c.name as buyer_name
        FROM marketplace_wtb w
        JOIN character_data c ON w.buyer_char_id = c.id
        WHERE w.status = 'active'
        AND w.item_id = ?
        AND w.quantity_fulfilled < w.quantity_wanted
        AND w.buyer_char_id != ?
        ORDER BY w.price_per_unit_copper DESC, w.created_date ASC
        LIMIT 5
    ");

    $query->execute($item_id, $char_id);

    my $count = 0;
    my $order_num = 1;

    quest::say("Excellent news! I found buyers looking for $item_name!");

    while (my $order = $query->fetch_hashref()) {
        my $price_pp = sprintf("%.2f", $order->{price_per_unit_copper} / 1000);
        my $total_price_pp = sprintf("%.2f", ($price_pp * $order->{quantity_remaining}));

        quest::say("[$order_num] " . $order->{buyer_name} . " wants " . $order->{quantity_remaining} .
                   " for " . $price_pp . " pp each (Total: " . $total_price_pp . " pp). Say '" .
                   quest::saylink("fulfill $order_num", 1) . "' to accept.");
        $order_num++;
        $count++;
    }

    $query->close();
    $db->close();
}

sub fulfill_wtb_order {
    my ($client, $seller_char_id, $item_id, $order_num) = @_;

    # Get pending item details
    my $item_name = $qglobals{$seller_char_id . "_pending_item_name"};
    my $quantity = $qglobals{$seller_char_id . "_pending_quantity"};

    if (!$item_id || !$quantity) {
        quest::say("Error: I can't find the item you're trying to sell. Please try again.");
        return;
    }

    # Query the database for active WTB orders
    my $db = Database::new(Database::Content);

    my $query = $db->prepare("
        SELECT
            w.id,
            w.buyer_char_id,
            w.quantity_wanted,
            w.quantity_fulfilled,
            (w.quantity_wanted - w.quantity_fulfilled) as quantity_remaining,
            w.price_per_unit_copper,
            c.name as buyer_name
        FROM marketplace_wtb w
        JOIN character_data c ON w.buyer_char_id = c.id
        WHERE w.status = 'active'
        AND w.item_id = ?
        AND w.quantity_fulfilled < w.quantity_wanted
        AND w.buyer_char_id != ?
        ORDER BY w.price_per_unit_copper DESC, w.created_date ASC
        LIMIT 5
    ");

    $query->execute($item_id, $seller_char_id);

    # Find the order by position
    my $current_num = 1;
    my $order = undef;

    while (my $row = $query->fetch_hashref()) {
        if ($current_num == $order_num) {
            $order = $row;
            last;
        }
        $current_num++;
    }

    $query->close();

    if (!$order) {
        quest::say("Sorry, that WTB order is no longer available or doesn't exist.");
        $db->close();
        return;
    }

    # Calculate how many we can fulfill
    my $remaining = $order->{quantity_remaining};
    my $fulfill_qty = $quantity > $remaining ? $remaining : $quantity;

    # Calculate payment
    my $total_copper = $fulfill_qty * $order->{price_per_unit_copper};
    my $total_pp = sprintf("%.2f", $total_copper / 1000);

    # Create fulfillment record
    my $insert_query = $db->prepare("
        INSERT INTO marketplace_wtb_fulfillments (
            wtb_id, seller_char_id, buyer_char_id, item_id,
            quantity, price_per_unit_copper, total_price_copper, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')
    ");

    $insert_query->execute(
        $order->{id},
        $seller_char_id,
        $order->{buyer_char_id},
        $item_id,
        $fulfill_qty,
        $order->{price_per_unit_copper},
        $total_copper
    );
    $insert_query->close();

    # Deduct payment from buyer (the person who created the WTB order)
    # Use global alternate currency configuration from top of file
    my $buyer_char_id = $order->{buyer_char_id};

    # Check if buyer is currently online
    my $buyer_online_query = $db->prepare("SELECT ingame FROM character_data WHERE id = ?");
    $buyer_online_query->execute($buyer_char_id);
    my $buyer_status = $buyer_online_query->fetch_hashref();
    $buyer_online_query->close();

    my $buyer_is_online = ($buyer_status && $buyer_status->{ingame} > 0) ? 1 : 0;

    if ($buyer_is_online) {
        # Buyer is ONLINE - verify funds BEFORE queuing payment
        my $bitcoin_needed = 0;
        my $bitcoin_value_copper = 0;
        my $platinum_copper = $total_copper;

        # Only calculate Bitcoin needs if alternate currency is enabled
        if ($USE_ALT_CURRENCY && $total_pp > $BITCOIN_VALUE_PP) {
            $bitcoin_needed = int($total_pp / $BITCOIN_VALUE_PP);
            $bitcoin_value_copper = $bitcoin_needed * $BITCOIN_VALUE_PP * 1000;
            $platinum_copper = $total_copper - $bitcoin_value_copper;
        }

        # Check if buyer has sufficient funds
        if ($USE_ALT_CURRENCY && $total_pp > $BITCOIN_VALUE_PP) {
            # Check Bitcoin
            my $bitcoin_query = $db->prepare("
                SELECT COALESCE(SUM(charges), 0) as bitcoin_count
                FROM inventory
                WHERE character_id = ? AND item_id = ?
            ");
            $bitcoin_query->execute($buyer_char_id, $BITCOIN_ID);
            my $bitcoin_row = $bitcoin_query->fetch_hashref();
            my $bitcoin_inventory = $bitcoin_row->{bitcoin_count} || 0;
            $bitcoin_query->close();

            my $alt_query = $db->prepare("
                SELECT amount FROM character_alt_currency
                WHERE char_id = ? AND currency_id = ?
            ");
            $alt_query->execute($buyer_char_id, $BITCOIN_ID);
            my $alt_row = $alt_query->fetch_hashref();
            my $bitcoin_alternate = $alt_row ? $alt_row->{amount} : 0;
            $alt_query->close();

            my $total_bitcoin = $bitcoin_inventory + $bitcoin_alternate;

            if ($total_bitcoin < $bitcoin_needed) {
                quest::say("Error: Buyer doesn't have enough $ALT_CURRENCY_NAME! They need $bitcoin_needed but only have $total_bitcoin. Cancelling WTB order.");

                # Cancel the WTB order
                my $cancel_query = $db->prepare("UPDATE marketplace_wtb SET status = 'cancelled' WHERE id = ?");
                $cancel_query->execute($order->{id});
                $cancel_query->close();

                $db->close();
                return;
            }

            # Check platinum remainder if any
            my $platinum_needed = $total_pp - ($bitcoin_needed * $BITCOIN_VALUE_PP);
            if ($platinum_needed > 0) {
                my $copper_needed = int($platinum_needed * 1000);
                my $curr_query = $db->prepare("
                    SELECT platinum, gold, silver, copper
                    FROM character_currency
                    WHERE id = ?
                ");
                $curr_query->execute($buyer_char_id);
                my $curr = $curr_query->fetch_hashref();
                $curr_query->close();

                if ($curr) {
                    my $buyer_total_copper = ($curr->{platinum} * 1000) +
                                            ($curr->{gold} * 100) +
                                            ($curr->{silver} * 10) +
                                            $curr->{copper};

                    if ($buyer_total_copper < $copper_needed) {
                        quest::say("Error: Buyer doesn't have enough platinum for remainder! Needs " . sprintf("%.2f", $platinum_needed) . "pp. Cancelling WTB order.");

                        # Cancel the WTB order
                        my $cancel_query = $db->prepare("UPDATE marketplace_wtb SET status = 'cancelled' WHERE id = ?");
                        $cancel_query->execute($order->{id});
                        $cancel_query->close();

                        $db->close();
                        return;
                    }
                }
            }
        } else {
            # Standard platinum payment - check funds
            my $curr_query = $db->prepare("
                SELECT platinum, gold, silver, copper
                FROM character_currency
                WHERE id = ?
            ");
            $curr_query->execute($buyer_char_id);
            my $curr = $curr_query->fetch_hashref();
            $curr_query->close();

            if ($curr) {
                my $buyer_total_copper = ($curr->{platinum} * 1000) +
                                        ($curr->{gold} * 100) +
                                        ($curr->{silver} * 10) +
                                        $curr->{copper};

                if ($buyer_total_copper < $total_copper) {
                    quest::say("Error: Buyer doesn't have enough platinum! Needs $total_pp" . "pp but only has " . sprintf("%.2f", $buyer_total_copper / 1000) . "pp. Cancelling WTB order.");

                    # Cancel the WTB order
                    my $cancel_query = $db->prepare("UPDATE marketplace_wtb SET status = 'cancelled' WHERE id = ?");
                    $cancel_query->execute($order->{id});
                    $cancel_query->close();

                    $db->close();
                    return;
                }
            }
        }

        # Funds verified - queue payment for processing on zone-in
        my $pending_query = $db->prepare("
            INSERT INTO wtb_pending_payments (
                buyer_char_id, bitcoin_amount, platinum_copper, wtb_order_id,
                seller_name, item_id, item_name, quantity
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        my $seller_name = $client->GetName();
        my $item_name_query = $db->prepare("SELECT name FROM items WHERE id = ?");
        $item_name_query->execute($item_id);
        my $item_row = $item_name_query->fetch_hashref();
        my $item_name = $item_row ? $item_row->{name} : "Unknown Item";
        $item_name_query->close();

        $pending_query->execute(
            $buyer_char_id,
            $bitcoin_needed,
            $platinum_copper,
            $order->{id},
            $seller_name,
            $item_id,
            $item_name,
            $fulfill_qty
        );
        $pending_query->close();

        $client->Message(15, "[DEBUG] Buyer is online - funds verified, queued payment for processing: Bitcoin=$bitcoin_needed, Copper=$platinum_copper");
        quest::say("Order accepted! The buyer (" . $order->{buyer_name} . ") has sufficient funds and will be charged when they next zone.");

        # Skip item delivery for online buyers - will be delivered after payment confirmation
        # Continue with seller payment below
    } else {
        # Buyer is OFFLINE - deduct via database immediately
        $client->Message(15, "[DEBUG] Buyer is offline - deducting payment via database");

        # Check if this is a high-value payment (>1M platinum) AND alternate currency is enabled
        if ($USE_ALT_CURRENCY && $total_pp > $BITCOIN_VALUE_PP) {
        # Calculate Bitcoin needed
        my $bitcoin_needed = int($total_pp / $BITCOIN_VALUE_PP);
        my $platinum_needed = $total_pp - ($bitcoin_needed * $BITCOIN_VALUE_PP);

        # Deduct Bitcoin from buyer's inventory/alternate currency
        my $bitcoin_query = $db->prepare("
            SELECT COALESCE(SUM(charges), 0) as bitcoin_count
            FROM inventory
            WHERE character_id = ? AND item_id = ?
        ");
        $bitcoin_query->execute($buyer_char_id, $BITCOIN_ID);
        my $bitcoin_row = $bitcoin_query->fetch_hashref();
        my $bitcoin_inventory = $bitcoin_row->{bitcoin_count} || 0;
        $bitcoin_query->close();

        # Check alternate currency
        my $alt_query = $db->prepare("
            SELECT amount FROM character_alt_currency
            WHERE char_id = ? AND currency_id = ?
        ");
        $alt_query->execute($buyer_char_id, $BITCOIN_ID);
        my $alt_row = $alt_query->fetch_hashref();
        my $bitcoin_alternate = $alt_row ? $alt_row->{amount} : 0;
        $alt_query->close();

        my $total_bitcoin = $bitcoin_inventory + $bitcoin_alternate;

        if ($total_bitcoin < $bitcoin_needed) {
            quest::say("Error: Buyer doesn't have enough Bitcoin! They need $bitcoin_needed but only have $total_bitcoin. Cancelling WTB order.");

            # Cancel the WTB order
            my $cancel_query = $db->prepare("UPDATE marketplace_wtb SET status = 'cancelled' WHERE id = ?");
            $cancel_query->execute($order->{id});
            $cancel_query->close();

            $db->close();
            return;
        }

        # Deduct Bitcoin from buyer (database method for offline buyers)
        # Try alternate currency first
        my $remaining_bitcoin = $bitcoin_needed;

        if ($bitcoin_alternate > 0 && $remaining_bitcoin > 0) {
            my $take_from_alt = $bitcoin_alternate > $remaining_bitcoin ? $remaining_bitcoin : $bitcoin_alternate;
            my $alt_update = $db->prepare("
                UPDATE character_alt_currency
                SET amount = amount - ?
                WHERE char_id = ? AND currency_id = ?
            ");
            $alt_update->execute($take_from_alt, $buyer_char_id, $BITCOIN_ID);
            $alt_update->close();
            $remaining_bitcoin -= $take_from_alt;
            $client->Message(15, "[DEBUG] Deducted $take_from_alt Bitcoin from buyer's alternate currency");
        }

        # Take remaining from inventory
        if ($remaining_bitcoin > 0 && $bitcoin_inventory > 0) {
            my $inv_query = $db->prepare("
                SELECT slot_id, charges
                FROM inventory
                WHERE character_id = ? AND item_id = ?
                ORDER BY slot_id ASC
            ");
            $inv_query->execute($buyer_char_id, $BITCOIN_ID);

            while (my $item = $inv_query->fetch_hashref()) {
                last if $remaining_bitcoin <= 0;

                my $charges = $item->{charges};
                my $slot_id = $item->{slot_id};

                if ($charges <= $remaining_bitcoin) {
                    # Delete entire stack
                    my $del = $db->prepare("DELETE FROM inventory WHERE character_id = ? AND slot_id = ?");
                    $del->execute($buyer_char_id, $slot_id);
                    $del->close();
                    $remaining_bitcoin -= $charges;
                    $client->Message(15, "[DEBUG] Deleted Bitcoin stack of $charges from buyer slot $slot_id");
                } else {
                    # Reduce charges
                    my $upd = $db->prepare("UPDATE inventory SET charges = charges - ? WHERE character_id = ? AND slot_id = ?");
                    $upd->execute($remaining_bitcoin, $buyer_char_id, $slot_id);
                    $upd->close();
                    $client->Message(15, "[DEBUG] Reduced Bitcoin stack by $remaining_bitcoin from buyer slot $slot_id");
                    $remaining_bitcoin = 0;
                }
            }
            $inv_query->close();
        }

        if ($remaining_bitcoin > 0) {
            quest::say("Error: Failed to deduct $remaining_bitcoin $ALT_CURRENCY_NAME from buyer. Transaction aborted.");
            $db->close();
            return;
        }

        $client->Message(15, "[DEBUG] Successfully deducted $bitcoin_needed Bitcoin from buyer char_id $buyer_char_id");

        # Deduct platinum remainder if any
        if ($platinum_needed > 0) {
            my $copper_needed = int($platinum_needed * 1000);
            my $curr_query = $db->prepare("
                SELECT platinum, gold, silver, copper
                FROM character_currency
                WHERE id = ?
            ");
            $curr_query->execute($buyer_char_id);
            my $curr = $curr_query->fetch_hashref();
            $curr_query->close();

            if ($curr) {
                my $buyer_total_copper = ($curr->{platinum} * 1000) +
                                        ($curr->{gold} * 100) +
                                        ($curr->{silver} * 10) +
                                        $curr->{copper};

                if ($buyer_total_copper < $copper_needed) {
                    quest::say("Error: Buyer doesn't have enough platinum for remainder! Needs " . sprintf("%.2f", $platinum_needed) . "pp. Cancelling WTB order.");

                    # Cancel the WTB order
                    my $cancel_query = $db->prepare("UPDATE marketplace_wtb SET status = 'cancelled' WHERE id = ?");
                    $cancel_query->execute($order->{id});
                    $cancel_query->close();

                    $db->close();
                    return;
                }

                my $new_copper = $buyer_total_copper - $copper_needed;
                my $new_pp = int($new_copper / 1000);
                $new_copper = $new_copper % 1000;
                my $new_gp = int($new_copper / 100);
                $new_copper = $new_copper % 100;
                my $new_sp = int($new_copper / 10);
                $new_copper = $new_copper % 10;

                my $update_query = $db->prepare("
                    UPDATE character_currency
                    SET platinum = ?, gold = ?, silver = ?, copper = ?
                    WHERE id = ?
                ");
                $update_query->execute($new_pp, $new_gp, $new_sp, $new_copper, $buyer_char_id);
                $update_query->close();
                $client->Message(15, "[DEBUG] Deducted " . sprintf("%.2f", $platinum_needed) . "pp from buyer");
            }
        }
    } else {
        # Standard platinum payment (<1M)
        my $curr_query = $db->prepare("
            SELECT platinum, gold, silver, copper
            FROM character_currency
            WHERE id = ?
        ");
        $curr_query->execute($buyer_char_id);
        my $curr = $curr_query->fetch_hashref();
        $curr_query->close();

        if ($curr) {
            my $buyer_total_copper = ($curr->{platinum} * 1000) +
                                    ($curr->{gold} * 100) +
                                    ($curr->{silver} * 10) +
                                    $curr->{copper};

            if ($buyer_total_copper < $total_copper) {
                quest::say("Error: Buyer doesn't have enough platinum! Needs $total_pp" . "pp but only has " . sprintf("%.2f", $buyer_total_copper / 1000) . "pp. Cancelling WTB order.");

                # Cancel the WTB order
                my $cancel_query = $db->prepare("UPDATE marketplace_wtb SET status = 'cancelled' WHERE id = ?");
                $cancel_query->execute($order->{id});
                $cancel_query->close();

                $db->close();
                return;
            }

            my $new_copper = $buyer_total_copper - $total_copper;
            my $new_pp = int($new_copper / 1000);
            $new_copper = $new_copper % 1000;
            my $new_gp = int($new_copper / 100);
            $new_copper = $new_copper % 100;
            my $new_sp = int($new_copper / 10);
            $new_copper = $new_copper % 10;

            my $update_query = $db->prepare("
                UPDATE character_currency
                SET platinum = ?, gold = ?, silver = ?, copper = ?
                WHERE id = ?
            ");
            $update_query->execute($new_pp, $new_gp, $new_sp, $new_copper, $buyer_char_id);
            $update_query->close();
            $client->Message(15, "[DEBUG] Deducted $total_pp" . "pp from buyer char_id $buyer_char_id");
        }
        }
    } # End offline buyer payment block

    # Update WTB order
    my $new_fulfilled = $order->{quantity_fulfilled} + $fulfill_qty;
    my $new_status = $new_fulfilled >= $order->{quantity_wanted} ? 'fulfilled' : 'active';

    my $update_query = $db->prepare("
        UPDATE marketplace_wtb
        SET quantity_fulfilled = ?, status = ?
        WHERE id = ?
    ");
    $update_query->execute($new_fulfilled, $new_status, $order->{id});
    $update_query->close();

    # Send item to buyer via parcel (ONLY for offline buyers)
    # Online buyers will receive item after payment confirmation in global_player.pl
    if (!$buyer_is_online) {
        # First get the next available slot_id
        my $slot_query = $db->prepare("
            SELECT COALESCE(MAX(slot_id), -1) + 1 as next_slot
            FROM character_parcels
            WHERE char_id = ?
        ");
        $slot_query->execute($order->{buyer_char_id});
        my $slot_row = $slot_query->fetch_hashref();
        my $next_slot = $slot_row ? $slot_row->{next_slot} : 0;
        $slot_query->close();

        my $parcel_query = $db->prepare("
            INSERT INTO character_parcels (
                char_id, slot_id, from_name, note, sent_date, quantity, item_id
            ) VALUES (?, ?, ?, ?, NOW(), ?, ?)
        ");

        my $seller_name = $client->GetName();
        $parcel_query->execute(
            $order->{buyer_char_id},
            $next_slot,
            $seller_name,
            "WTB Order Fulfilled",
            $fulfill_qty,
            $item_id
        );
        $parcel_query->close();
    }

    # Pay the seller immediately (add money to seller)
    # Note: $BITCOIN_VALUE_PP and $BITCOIN_ID already declared earlier in scope

    if ($total_pp > $BITCOIN_VALUE_PP) {
        # Payment over 1M platinum - convert to Bitcoin
        my $bitcoin_amount = int($total_pp / $BITCOIN_VALUE_PP);
        my $platinum_remainder = $total_pp - ($bitcoin_amount * $BITCOIN_VALUE_PP);
        my $remainder_copper = int($platinum_remainder * 1000);

        # Get next slot for seller's parcels
        my $seller_slot_query = $db->prepare("
            SELECT COALESCE(MAX(slot_id), -1) + 1 as next_slot
            FROM character_parcels
            WHERE char_id = ?
        ");
        $seller_slot_query->execute($seller_char_id);
        my $seller_slot_row = $seller_slot_query->fetch_hashref();
        my $seller_next_slot = $seller_slot_row ? $seller_slot_row->{next_slot} : 0;
        $seller_slot_query->close();

        # Send Bitcoin parcel to seller
        my $bitcoin_parcel = $db->prepare("
            INSERT INTO character_parcels (
                char_id, slot_id, from_name, note, sent_date, quantity, item_id
            ) VALUES (?, ?, ?, ?, NOW(), ?, ?)
        ");
        $bitcoin_parcel->execute(
            $seller_char_id,
            $seller_next_slot,
            "Marketplace",
            "WTB Order Payment: $bitcoin_amount Bitcoin + " . sprintf("%.2f", $platinum_remainder) . "pp",
            $bitcoin_amount,
            $BITCOIN_ID
        );
        $bitcoin_parcel->close();

        # Give platinum remainder if any
        if ($remainder_copper > 0) {
            give_money($remainder_copper);
        }

        $db->close();

        quest::say("Excellent! I've fulfilled the order for " . $order->{buyer_name} . "!");
        if ($platinum_remainder > 0) {
            quest::say("You've been paid $bitcoin_amount $ALT_CURRENCY_NAME + " . sprintf("%.2f", $platinum_remainder) . " platinum. Check your parcels for the $ALT_CURRENCY_NAME!");
        } else {
            quest::say("You've been paid $bitcoin_amount $ALT_CURRENCY_NAME. Check your parcels!");
        }
    } else {
        # Payment under 1M platinum - give as regular currency
        $db->close();
        give_money($total_copper);

        quest::say("Excellent! I've fulfilled the order for " . $order->{buyer_name} . "!");
        quest::say("You've been paid " . $total_pp . " platinum. The item has been sent to " . $order->{buyer_name} . " via parcel.");
    }

    # Handle leftover items or clear pending data
    if ($fulfill_qty < $quantity) {
        my $leftover = $quantity - $fulfill_qty;
        quest::say("You had $leftover extra. Would you like to list those on the marketplace? Say 'price X' to set a price.");

        # Update pending quantity for remaining items
        $qglobals{$seller_char_id . "_pending_item_id"} = $item_id;
        $qglobals{$seller_char_id . "_pending_item_name"} = $item_name;
        $qglobals{$seller_char_id . "_pending_quantity"} = $leftover;

        # Restart timer for leftover items
        quest::settimer("clear_pending_" . $seller_char_id, 300);
    } else {
        # All items fulfilled - clear pending data
        delete $qglobals{$seller_char_id . "_pending_item_id"};
        delete $qglobals{$seller_char_id . "_pending_item_name"};
        delete $qglobals{$seller_char_id . "_pending_quantity"};
        delete $qglobals{$seller_char_id . "_wtb_orders"};
        quest::stoptimer("clear_pending_" . $seller_char_id);
    }
}

# ==============================================================================
# Money Helper Functions
# ==============================================================================

sub give_money {
    my ($total_copper) = @_;

    # Convert total copper to proper denominations
    my $platinum = int($total_copper / 1000);
    my $remaining = $total_copper % 1000;
    my $gold = int($remaining / 100);
    $remaining = $remaining % 100;
    my $silver = int($remaining / 10);
    my $copper = $remaining % 10;

    quest::givecash($copper, $silver, $gold, $platinum);
}

# ==============================================================================
# Bitcoin Helper Functions
# ==============================================================================

sub get_bitcoin_from_inventory {
    my ($client, $char_id, $db) = @_;
    # Use global $BITCOIN_ID from configuration at top of file

    my $query = $db->prepare("
        SELECT COALESCE(SUM(charges), 0) as bitcoin_count
        FROM inventory
        WHERE character_id = ? AND item_id = ?
    ");
    $query->execute($char_id, $BITCOIN_ID);

    my $result = $query->fetch_hashref();
    $query->close();

    return $result ? $result->{bitcoin_count} : 0;
}

sub get_bitcoin_from_alternate {
    my ($client, $char_id, $db) = @_;
    # Use global $BITCOIN_ID from configuration at top of file

    # Check if table exists
    my $check_query = $db->prepare("
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'character_alt_currency'
    ");
    $check_query->execute();

    if (!$check_query->fetch_hashref()) {
        $check_query->close();
        return 0; # Table doesn't exist
    }
    $check_query->close();

    my $query = $db->prepare("
        SELECT COALESCE(amount, 0) as bitcoin_count
        FROM character_alt_currency
        WHERE char_id = ? AND currency_id = ?
    ");
    $query->execute($char_id, $BITCOIN_ID);

    my $result = $query->fetch_hashref();
    $query->close();

    return $result ? $result->{bitcoin_count} : 0;
}

sub deduct_bitcoin {
    my ($client, $char_id, $db, $amount, $bitcoin_inventory, $bitcoin_alternate) = @_;
    # Use global $BITCOIN_ID from configuration at top of file
    my $remaining = $amount;
    my $total_deducted = 0;

    # Try to take from alternate currency first using client method
    if ($bitcoin_alternate > 0 && $remaining > 0) {
        my $take_from_alternate = $bitcoin_alternate > $remaining ? $remaining : $bitcoin_alternate;

        # Use client method to remove alternate currency
        $client->RemoveAlternateCurrencyValue($BITCOIN_ID, $take_from_alternate);

        $total_deducted += $take_from_alternate;
        $remaining -= $take_from_alternate;
        $client->Message(15, "[DEBUG] Deducted $take_from_alternate Bitcoin from alternate currency");
    }

    # Take remaining from inventory using client method
    if ($remaining > 0 && $bitcoin_inventory > 0) {
        my $take_from_inventory = $bitcoin_inventory > $remaining ? $remaining : $bitcoin_inventory;

        # Use client method to remove items
        $client->RemoveItem($BITCOIN_ID, $take_from_inventory);

        $total_deducted += $take_from_inventory;
        $remaining -= $take_from_inventory;
        $client->Message(15, "[DEBUG] Deducted $take_from_inventory Bitcoin from inventory");
    }

    return $total_deducted;
}

sub add_bitcoin_to_alternate {
    my ($client, $char_id, $db, $amount) = @_;
    # Use global $BITCOIN_ID from configuration at top of file

    if ($amount <= 0) {
        return 1;
    }

    # Check if table exists
    my $check_query = $db->prepare("
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'character_alt_currency'
    ");
    $check_query->execute();

    if (!$check_query->fetch_hashref()) {
        $check_query->close();
        return 0; # Table doesn't exist
    }
    $check_query->close();

    # Check if record exists
    my $check_existing = $db->prepare("
        SELECT amount
        FROM character_alt_currency
        WHERE char_id = ? AND currency_id = ?
    ");
    $check_existing->execute($char_id, $BITCOIN_ID);
    my $existing = $check_existing->fetch_hashref();
    $check_existing->close();

    if ($existing) {
        # Update existing
        my $update_query = $db->prepare("
            UPDATE character_alt_currency
            SET amount = amount + ?
            WHERE char_id = ? AND currency_id = ?
        ");
        $update_query->execute($amount, $char_id, $BITCOIN_ID);
        $update_query->close();
    } else {
        # Insert new
        my $insert_query = $db->prepare("
            INSERT INTO character_alt_currency (char_id, currency_id, amount)
            VALUES (?, ?, ?)
        ");
        $insert_query->execute($char_id, $BITCOIN_ID, $amount);
        $insert_query->close();
    }

    $client->Message(15, "[DEBUG] Added $amount Bitcoin to alternate currency");
    return 1;
}
