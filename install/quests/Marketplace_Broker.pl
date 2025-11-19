# Marketplace Broker NPC - Accepts items for marketplace listing and WTB fulfillment
# Place this file in your quests/global/ directory
# Create an NPC with this name or use #npcspawn Marketplace_Broker

# ============================================================================
# MARKETPLACE CONFIGURATION
# ============================================================================
# IMPORTANT: These settings MUST match your .env file configuration!
# The marketplace website and NPC must use the same settings to work correctly.
#
# After changing these values, run: #reloadquest in-game

# Alternate Currency Configuration (Optional High-Value Currency System)
# Set to 0 to use platinum-only marketplace (default)
# Set to 1 to enable custom alternate currency for high-value transactions
# MUST MATCH: USE_ALT_CURRENCY in .env file
our $USE_ALT_CURRENCY = 0;  # Default: platinum-only marketplace

# If $USE_ALT_CURRENCY is 1, configure these settings:
# MUST MATCH: ALT_CURRENCY_ITEM_ID in .env file
our $ALT_CURRENCY_ITEM_ID = 147623;          # Item ID for your alternate currency

# MUST MATCH: ALT_CURRENCY_VALUE_PLATINUM in .env file
our $ALT_CURRENCY_VALUE_PP = 1000000;        # How much platinum = 1 alt currency

# MUST MATCH: ALT_CURRENCY_NAME in .env file
our $ALT_CURRENCY_NAME = 'Alt Currency';          # Display name for your alternate currency

# Debug Mode - Set to 1 to enable detailed debug messages, 0 to disable
our $DEBUG_MODE = 0;                              # Default: disabled


# ============================================================================
# END CONFIGURATION - Do not modify below this line
# ============================================================================

sub EVENT_ITEM {
    # Get client and NPC objects using plugin::val (required for proper access)
    my $client = plugin::val('$client');
    my $npc = plugin::val('$npc');

    my $item_id = 0;
    my $item_name = "";
    my $quantity = 0;
    my $is_tradable = 1;

    # Create database connection
    my $db = Database::new(Database::Content);

    # Debug: Show what we received in itemcount (excluding empty slots)
    if ($DEBUG_MODE) {
        $client->Message(15, "[DEBUG] ========== ITEMCOUNT HASH ==========");
        foreach my $key (keys %itemcount) {
            next if $key == 0; # Skip empty slots
            $client->Message(15, "[DEBUG] itemcount{$key} = " . $itemcount{$key});
        }
        $client->Message(15, "[DEBUG] ====================================");
    }

    # Process items from slots - need to get item instances for charges/quantity
    # %itemcount only shows item_id => 1, NOT the actual stack size
    # Stack size is stored in the item's charges which we get from $item1-$item4
    # NOTE: Empty trade slots show as item_id = 0, skip those
    # NOTE: If multiple slots have the same item, add quantities together

    my %item_quantities;  # Track quantities per item_id
    my $first_item_id = 0;

    for my $slot (1..4) {
        my $slot_item_id = plugin::val("\$item$slot");
        next unless $slot_item_id;
        next if $slot_item_id == 0;  # Skip empty slots

        # Get the actual item instance to read charges (stack size)
        my $item_inst = plugin::val("\$item${slot}_inst");
        my $slot_quantity = 1;  # Default for non-stackable items

        if ($item_inst) {
            my $charges = $item_inst->GetCharges();
            # Only use charges if it's greater than 0 (stackable item)
            # For non-stackable items, charges is 0, so keep default of 1
            if ($charges > 0) {
                $slot_quantity = $charges;
            }
            $client->Message(15, "[DEBUG] Slot $slot: Item $slot_item_id, charges: $charges, quantity: $slot_quantity") if $DEBUG_MODE;
        }

        # Track the first item we see
        if ($first_item_id == 0) {
            $first_item_id = $slot_item_id;
        }

        # Add to total quantity for this item
        $item_quantities{$slot_item_id} = ($item_quantities{$slot_item_id} || 0) + $slot_quantity;
    }

    # Check if player handed in multiple different item types
    my @unique_items = keys %item_quantities;
    if (scalar(@unique_items) > 1) {
        quest::say("I can only accept one type of item at a time. You handed me multiple different items. Here, take them back.");
        $client->Message(15, "[DEBUG] Multiple item types detected: " . join(", ", @unique_items)) if $DEBUG_MODE;
        $db->close();
        plugin::return_items(\%itemcount);
        return;
    }

    # If no items found, return
    if (scalar(@unique_items) == 0 || $first_item_id == 0) {
        quest::say("I don't see any valid items. Here, take everything back.");
        $db->close();
        plugin::return_items(\%itemcount);
        return;
    }

    # Get the single item type we're processing
    $item_id = $first_item_id;
    $quantity = $item_quantities{$item_id};

    # Get item details from the items table
    my $query = $db->prepare("SELECT id, Name, nodrop, norent, loregroup FROM items WHERE id = ?");
    $query->execute($item_id);

    if (my $row = $query->fetch_hashref()) {
        $item_name = $row->{Name};

        my $num_stacks = scalar(grep { $_ == $item_id } keys %item_quantities);
        $client->Message(15, "[DEBUG] Processing: $item_name (ID: $item_id, Total Qty: $quantity from multiple stacks)") if $DEBUG_MODE;

        # Check if item is NO TRADE (nodrop = 0 means NO TRADE in EQEmu)
        if ($row->{nodrop} == 0) {
            quest::say("I'm sorry, but I cannot accept $item_name as it is marked NO TRADE. Here, take it back.");
            $query->close();
            $db->close();
            plugin::return_items(\%itemcount);
            return;
        }

        # Check if item is NO RENT (temporary item that disappears on logout)
        if ($row->{norent} == 0) {
            quest::say("I'm sorry, but I cannot accept $item_name as it is a temporary item. Here, take it back.");
            $query->close();
            $db->close();
            plugin::return_items(\%itemcount);
            return;
        }

        $query->close();
    } else {
        # Item not found in database
        $client->Message(15, "[DEBUG] Item ID $item_id not found in items table.") if $DEBUG_MODE;
        $db->close();
        plugin::return_items(\%itemcount);
        return;
    }

    # Close database connection
    $db->close();

    $client->Message(15, "[DEBUG] Valid item found: ID=$item_id, Total Qty=$quantity, Name=$item_name") if $DEBUG_MODE;

    # Items validated successfully
    # Call plugin::check_handin with the ACTUAL quantity from charges
    # We need to tell check_handin we want ALL items in the stack
    my $char_id = $client->CharacterID();

    $client->Message(15, "[DEBUG] About to call plugin::check_handin") if $DEBUG_MODE;
    $client->Message(15, "[DEBUG] Will match: $item_id => $quantity (ALL items in stack)") if $DEBUG_MODE;

    # Store item data BEFORE check_handin
    $qglobals{$char_id . "_pending_item_id"} = $item_id;
    $qglobals{$char_id . "_pending_item_name"} = $item_name;
    $qglobals{$char_id . "_pending_quantity"} = $quantity;

    # Call check_handin with the FULL quantity - this consumes ALL items
    # Pass the quantity we got from charges (50), not 1
    if (plugin::check_handin(\%itemcount, $item_id => $quantity)) {
        $client->Message(15, "[DEBUG] check_handin SUCCESS - all $quantity items consumed") if $DEBUG_MODE;

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
        $client->Message(13, "[ERROR] check_handin FAILED - returning items");
        # Clear pending data since handin failed
        delete $qglobals{$char_id . "_pending_item_id"};
        delete $qglobals{$char_id . "_pending_item_name"};
        delete $qglobals{$char_id . "_pending_quantity"};
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
    # Get client and NPC objects using plugin::val (required for proper access)
    my $client = plugin::val('$client');
    my $npc = plugin::val('$npc');

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
        my $is_high_value = $USE_ALT_CURRENCY && ($price_pp > $ALT_CURRENCY_VALUE_PP);
        my $alt_currency_used = 0;
        my $platinum_used = 0;
        my $platinum_refund = 0;
        my $payment_success = 0;

        # Get current platinum and Alt currency (only if alternate currency enabled)
        my $current_platinum = $client->GetCarriedPlatinum();
        my $alt_currency_inventory = 0;
        my $alt_currency_alternate = 0;
        my $total_alt_currency = 0;

        if ($USE_ALT_CURRENCY) {
            $alt_currency_inventory = get_alt_currency_from_inventory($client, $char_id, $db);
            $alt_currency_alternate = get_alt_currency_from_alternate($client, $char_id, $db);
            $total_alt_currency = $alt_currency_inventory + $alt_currency_alternate;
            $client->Message(15, "[DEBUG] Available: $current_platinum pp, $total_alt_currency $ALT_CURRENCY_NAME (Inventory: $alt_currency_inventory, Alternate: $alt_currency_alternate)") if $DEBUG_MODE;
        } else {
            $client->Message(15, "[DEBUG] Available: $current_platinum pp (platinum-only mode)") if $DEBUG_MODE;
        }

        if ($is_high_value) {
            # HIGH-VALUE (>1M platinum): Alt currency FIRST, then platinum for remainder
            $client->Message(15, "[DEBUG] High-value purchase ($price_pp pp). Using Alt currency-first payment.") if $DEBUG_MODE;

            # Calculate how much Alt currency we need
            my $alt_currency_needed = int($price_pp / $ALT_CURRENCY_VALUE_PP);
            my $remainder_after_alt_currency = $price_pp - ($alt_currency_needed * $ALT_CURRENCY_VALUE_PP);

            if ($total_alt_currency >= $alt_currency_needed) {
                # We have enough Alt currency for the bulk
                # Check if platinum covers the remainder
                if ($current_platinum >= $remainder_after_alt_currency) {
                    # Perfect - use Alt currency + platinum
                    my $alt_currency_deducted = deduct_alt_currency($client, $char_id, $db, $alt_currency_needed, $alt_currency_inventory, $alt_currency_alternate);

                    if ($alt_currency_deducted == $alt_currency_needed) {
                        my $platinum_copper = $remainder_after_alt_currency * 1000;
                        if ($client->TakeMoneyFromPP($platinum_copper, 1)) {
                            $alt_currency_used = $alt_currency_needed;
                            $platinum_used = $remainder_after_alt_currency;
                            $payment_success = 1;
                            quest::say("Payment received! You paid with $alt_currency_used $ALT_CURRENCY_NAME and $platinum_used platinum.");
                        } else {
                            quest::say("Error deducting platinum. Refunding $ALT_CURRENCY_NAME...");
                            # Refund Alt currency via SummonItem
                            quest::summonitem($ALT_CURRENCY_ITEM_ID, $alt_currency_deducted);
                        }
                    }
                } else {
                    # Not enough platinum for remainder, need one more alt currency
                    $alt_currency_needed++;
                    if ($total_alt_currency >= $alt_currency_needed) {
                        my $alt_currency_deducted = deduct_alt_currency($client, $char_id, $db, $alt_currency_needed, $alt_currency_inventory, $alt_currency_alternate);

                        if ($alt_currency_deducted == $alt_currency_needed) {
                            # Calculate refund
                            $platinum_refund = ($alt_currency_needed * $ALT_CURRENCY_VALUE_PP) - $price_pp;
                            my $refund_copper = $platinum_refund * 1000;
                            give_money($refund_copper);

                            $alt_currency_used = $alt_currency_needed;
                            $platinum_used = 0;
                            $payment_success = 1;
                            quest::say("Payment received! You paid with $alt_currency_used $ALT_CURRENCY_NAME. Refunded $platinum_refund platinum.");
                        }
                    } else {
                        quest::say("You don't have enough funds. You need $alt_currency_needed $ALT_CURRENCY_NAME but only have $total_alt_currency.");
                    }
                }
            } else {
                # Not enough alt currency, use what we have + platinum
                my $alt_currency_value_provided = $total_alt_currency * $ALT_CURRENCY_VALUE_PP;
                my $platinum_still_needed = $price_pp - $alt_currency_value_provided;

                if ($current_platinum >= $platinum_still_needed) {
                    my $alt_currency_deducted = deduct_alt_currency($client, $char_id, $db, $total_alt_currency, $alt_currency_inventory, $alt_currency_alternate);
                    my $platinum_copper = $platinum_still_needed * 1000;

                    if ($alt_currency_deducted == $total_alt_currency && $client->TakeMoneyFromPP($platinum_copper, 1)) {
                        $alt_currency_used = $total_alt_currency;
                        $platinum_used = $platinum_still_needed;
                        $payment_success = 1;
                        quest::say("Payment received! You paid with $alt_currency_used Alt currency and $platinum_used platinum.");
                    }
                } else {
                    quest::say("You don't have enough funds. You need $price_pp pp but only have $current_platinum pp and $total_alt_currency $ALT_CURRENCY_NAME.");
                }
            }
        } else {
            # LOW-VALUE (<1M platinum): Platinum FIRST, then Alt currency if needed
            $client->Message(15, "[DEBUG] Standard purchase ($price_pp pp). Using platinum-first payment.") if $DEBUG_MODE;

            my $current_copper = $current_platinum * 1000;

            if ($current_copper >= $price_copper) {
                # Sufficient platinum alone
                $payment_success = $client->TakeMoneyFromPP($price_copper, 1);
                $platinum_used = $price_pp;
                $client->Message(15, "[DEBUG] Paid with platinum only.") if $payment_success;
            } elsif ($total_alt_currency > 0) {
                # Not enough platinum, check alt currency
                my $platinum_shortfall = $price_pp - $current_platinum;
                my $alt_currency_needed = int(($platinum_shortfall + $ALT_CURRENCY_VALUE_PP - 1) / $ALT_CURRENCY_VALUE_PP); # Ceiling

                if ($total_alt_currency >= $alt_currency_needed) {
                    # Take all platinum first
                    if ($current_platinum > 0) {
                        $client->TakeMoneyFromPP($current_copper, 1);
                    }

                    # Deduct alt currency
                    my $alt_currency_deducted = deduct_alt_currency($client, $char_id, $db, $alt_currency_needed, $alt_currency_inventory, $alt_currency_alternate);

                    if ($alt_currency_deducted == $alt_currency_needed) {
                        $alt_currency_used = $alt_currency_needed;
                        $platinum_used = $current_platinum;

                        # Calculate refund
                        my $total_paid_value = $current_platinum + ($alt_currency_needed * $ALT_CURRENCY_VALUE_PP);
                        $platinum_refund = $total_paid_value - $price_pp;

                        if ($platinum_refund > 0) {
                            my $refund_copper = $platinum_refund * 1000;
                            give_money($refund_copper);
                        }

                        $payment_success = 1;
                        if ($platinum_refund > 0) {
                            quest::say("Payment received! You paid with $platinum_used platinum and $alt_currency_used $ALT_CURRENCY_NAME. Refunded $platinum_refund platinum.");
                        } else {
                            quest::say("Payment received! You paid with $platinum_used platinum and $alt_currency_used $ALT_CURRENCY_NAME.");
                        }
                    } else {
                        quest::say("Error deducting $ALT_CURRENCY_NAME. Refunding platinum...");
                        if ($current_platinum > 0) {
                            give_money($current_copper);
                        }
                    }
                } else {
                    quest::say("You don't have enough funds. You need $price_pp pp but only have $current_platinum pp and $total_alt_currency $ALT_CURRENCY_NAME.");
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
                $client->Message(15, "[DEBUG] Created earnings record for seller: {$price_copper} copper") if $DEBUG_MODE;
            } else {
                $client->Message(15, "[DEBUG] Earnings already exist for this sale (count: $existing_earnings), skipping creation") if $DEBUG_MODE;
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
            $client->Message(15, "[DEBUG] Payment completed successfully for transaction $trans_id") if $DEBUG_MODE;
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

        $client->Message(15, "[DEBUG] Attempting to list $item_name (ID: $item_id, Qty: $quantity) for $price_platinum pp.") if $DEBUG_MODE;

        # Create database connection
        my $db = Database::new(Database::Content);

        # Insert into marketplace_listings
        my $insert_query = $db->prepare("INSERT INTO marketplace_listings (
            seller_char_id, item_id, quantity, price_copper,
            listed_date, status, charges
        ) VALUES (?, ?, ?, ?, NOW(), 'active', ?)");

        $insert_query->execute($char_id, $item_id, $quantity, $price_copper, $quantity);
        $insert_query->close();
        $db->close();

        $client->Message(15, "[DEBUG] Database insert successful. Item listed.") if $DEBUG_MODE;
        quest::say("Perfect! Your $item_name has been listed on the marketplace for $price_platinum platinum. Buyers can now purchase it through the marketplace website. You will receive your payment when it sells, which you can claim on the website.");

        # Clear the pending data
        delete $qglobals{$char_id . "_pending_item_id"};
        delete $qglobals{$char_id . "_pending_item_name"};
        delete $qglobals{$char_id . "_pending_quantity"};
        delete $qglobals{$char_id . "_wtb_orders"};

        # Stop the timeout timer
        quest::stoptimer("clear_pending_" . $char_id);
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
        my $alt_currency_needed = 0;
        my $alt_currency_value_copper = 0;
        my $platinum_copper = $total_copper;

        # Only calculate Alt currency needs if alternate currency is enabled
        if ($USE_ALT_CURRENCY && $total_pp > $ALT_CURRENCY_VALUE_PP) {
            $alt_currency_needed = int($total_pp / $ALT_CURRENCY_VALUE_PP);
            $alt_currency_value_copper = $alt_currency_needed * $ALT_CURRENCY_VALUE_PP * 1000;
            $platinum_copper = $total_copper - $alt_currency_value_copper;
        }

        # Check if buyer has sufficient funds
        if ($USE_ALT_CURRENCY && $total_pp > $ALT_CURRENCY_VALUE_PP) {
            # Check alt currency
            my $alt_currency_query = $db->prepare("
                SELECT COALESCE(SUM(charges), 0) as alt_currency_count
                FROM inventory
                WHERE character_id = ? AND item_id = ?
            ");
            $alt_currency_query->execute($buyer_char_id, $ALT_CURRENCY_ITEM_ID);
            my $alt_currency_row = $alt_currency_query->fetch_hashref();
            my $alt_currency_inventory = $alt_currency_row->{alt_currency_count} || 0;
            $alt_currency_query->close();

            my $alt_query = $db->prepare("
                SELECT amount FROM character_alt_currency
                WHERE char_id = ? AND currency_id = ?
            ");
            $alt_query->execute($buyer_char_id, $ALT_CURRENCY_ITEM_ID);
            my $alt_row = $alt_query->fetch_hashref();
            my $alt_currency_alternate = $alt_row ? $alt_row->{amount} : 0;
            $alt_query->close();

            my $total_alt_currency = $alt_currency_inventory + $alt_currency_alternate;

            if ($total_alt_currency < $alt_currency_needed) {
                quest::say("Error: Buyer doesn't have enough $ALT_CURRENCY_NAME! They need $alt_currency_needed but only have $total_alt_currency. Cancelling WTB order.");

                # Cancel the WTB order
                my $cancel_query = $db->prepare("UPDATE marketplace_wtb SET status = 'cancelled' WHERE id = ?");
                $cancel_query->execute($order->{id});
                $cancel_query->close();

                $db->close();
                return;
            }

            # Check platinum remainder if any
            my $platinum_needed = $total_pp - ($alt_currency_needed * $ALT_CURRENCY_VALUE_PP);
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
            INSERT INTO marketplace_wtb_pending_payments (
                buyer_char_id, alt_currency_amount, platinum_copper, wtb_order_id,
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
            $alt_currency_needed,
            $platinum_copper,
            $order->{id},
            $seller_name,
            $item_id,
            $item_name,
            $fulfill_qty
        );
        $pending_query->close();

        $client->Message(15, "[DEBUG] Buyer is online - funds verified, queued payment for processing: alt currency=$alt_currency_needed, Copper=$platinum_copper") if $DEBUG_MODE;
        quest::say("Order accepted! The buyer (" . $order->{buyer_name} . ") has sufficient funds and will be charged when they next zone.");

        # Skip item delivery for online buyers - will be delivered after payment confirmation
        # Continue with seller payment below
    } else {
        # Buyer is OFFLINE - deduct via database immediately
        $client->Message(15, "[DEBUG] Buyer is offline - deducting payment via database") if $DEBUG_MODE;

        # Check if this is a high-value payment (>1M platinum) AND alternate currency is enabled
        if ($USE_ALT_CURRENCY && $total_pp > $ALT_CURRENCY_VALUE_PP) {
        # Calculate Alt currency needed
        my $alt_currency_needed = int($total_pp / $ALT_CURRENCY_VALUE_PP);
        my $platinum_needed = $total_pp - ($alt_currency_needed * $ALT_CURRENCY_VALUE_PP);

        # Deduct Alt currency from buyer's inventory/alternate currency
        my $alt_currency_query = $db->prepare("
            SELECT COALESCE(SUM(charges), 0) as alt_currency_count
            FROM inventory
            WHERE character_id = ? AND item_id = ?
        ");
        $alt_currency_query->execute($buyer_char_id, $ALT_CURRENCY_ITEM_ID);
        my $alt_currency_row = $alt_currency_query->fetch_hashref();
        my $alt_currency_inventory = $alt_currency_row->{alt_currency_count} || 0;
        $alt_currency_query->close();

        # Check alternate currency
        my $alt_query = $db->prepare("
            SELECT amount FROM character_alt_currency
            WHERE char_id = ? AND currency_id = ?
        ");
        $alt_query->execute($buyer_char_id, $ALT_CURRENCY_ITEM_ID);
        my $alt_row = $alt_query->fetch_hashref();
        my $alt_currency_alternate = $alt_row ? $alt_row->{amount} : 0;
        $alt_query->close();

        my $total_alt_currency = $alt_currency_inventory + $alt_currency_alternate;

        if ($total_alt_currency < $alt_currency_needed) {
            quest::say("Error: Buyer doesn't have enough alt currency! They need $alt_currency_needed but only have $total_alt_currency. Cancelling WTB order.");

            # Cancel the WTB order
            my $cancel_query = $db->prepare("UPDATE marketplace_wtb SET status = 'cancelled' WHERE id = ?");
            $cancel_query->execute($order->{id});
            $cancel_query->close();

            $db->close();
            return;
        }

        # Deduct Alt currency from buyer (database method for offline buyers)
        # Try alternate currency first
        my $remaining_alt_currency = $alt_currency_needed;

        if ($alt_currency_alternate > 0 && $remaining_alt_currency > 0) {
            my $take_from_alt = $alt_currency_alternate > $remaining_alt_currency ? $remaining_alt_currency : $alt_currency_alternate;
            my $alt_update = $db->prepare("
                UPDATE character_alt_currency
                SET amount = amount - ?
                WHERE char_id = ? AND currency_id = ?
            ");
            $alt_update->execute($take_from_alt, $buyer_char_id, $ALT_CURRENCY_ITEM_ID);
            $alt_update->close();
            $remaining_alt_currency -= $take_from_alt;
            $client->Message(15, "[DEBUG] Deducted $take_from_alt Alt currency from buyer's alternate currency") if $DEBUG_MODE;
        }

        # Take remaining from inventory
        if ($remaining_alt_currency > 0 && $alt_currency_inventory > 0) {
            my $inv_query = $db->prepare("
                SELECT slot_id, charges
                FROM inventory
                WHERE character_id = ? AND item_id = ?
                ORDER BY slot_id ASC
            ");
            $inv_query->execute($buyer_char_id, $ALT_CURRENCY_ITEM_ID);

            while (my $item = $inv_query->fetch_hashref()) {
                last if $remaining_alt_currency <= 0;

                my $charges = $item->{charges};
                my $slot_id = $item->{slot_id};

                if ($charges <= $remaining_alt_currency) {
                    # Delete entire stack
                    my $del = $db->prepare("DELETE FROM inventory WHERE character_id = ? AND slot_id = ?");
                    $del->execute($buyer_char_id, $slot_id);
                    $del->close();
                    $remaining_alt_currency -= $charges;
                    $client->Message(15, "[DEBUG] Deleted Alt currency stack of $charges from buyer slot $slot_id") if $DEBUG_MODE;
                } else {
                    # Reduce charges
                    my $upd = $db->prepare("UPDATE inventory SET charges = charges - ? WHERE character_id = ? AND slot_id = ?");
                    $upd->execute($remaining_alt_currency, $buyer_char_id, $slot_id);
                    $upd->close();
                    $client->Message(15, "[DEBUG] Reduced Alt currency stack by $remaining_alt_currency from buyer slot $slot_id") if $DEBUG_MODE;
                    $remaining_alt_currency = 0;
                }
            }
            $inv_query->close();
        }

        if ($remaining_alt_currency > 0) {
            quest::say("Error: Failed to deduct $remaining_alt_currency $ALT_CURRENCY_NAME from buyer. Transaction aborted.");
            $db->close();
            return;
        }

        $client->Message(15, "[DEBUG] Successfully deducted $alt_currency_needed Alt currency from buyer char_id $buyer_char_id") if $DEBUG_MODE;

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
                $client->Message(15, "[DEBUG] Deducted " . sprintf("%.2f", $platinum_needed) . "pp from buyer") if $DEBUG_MODE;
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
            $client->Message(15, "[DEBUG] Deducted $total_pp" . "pp from buyer char_id $buyer_char_id") if $DEBUG_MODE;
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
    # Note: $ALT_CURRENCY_VALUE_PP and $ALT_CURRENCY_ITEM_ID already declared earlier in scope

    if ($total_pp > $ALT_CURRENCY_VALUE_PP) {
        # Payment over 1M platinum - convert to alt currency
        my $alt_currency_amount = int($total_pp / $ALT_CURRENCY_VALUE_PP);
        my $platinum_remainder = $total_pp - ($alt_currency_amount * $ALT_CURRENCY_VALUE_PP);
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

        # Send Alt currency parcel to seller
        my $alt_currency_parcel = $db->prepare("
            INSERT INTO character_parcels (
                char_id, slot_id, from_name, note, sent_date, quantity, item_id
            ) VALUES (?, ?, ?, ?, NOW(), ?, ?)
        ");
        $alt_currency_parcel->execute(
            $seller_char_id,
            $seller_next_slot,
            "Marketplace",
            "WTB Order Payment: $alt_currency_amount Alt currency + " . sprintf("%.2f", $platinum_remainder) . "pp",
            $alt_currency_amount,
            $ALT_CURRENCY_ITEM_ID
        );
        $alt_currency_parcel->close();

        # Give platinum remainder if any
        if ($remainder_copper > 0) {
            give_money($remainder_copper);
        }

        $db->close();

        quest::say("Excellent! I've fulfilled the order for " . $order->{buyer_name} . "!");
        if ($platinum_remainder > 0) {
            quest::say("You've been paid $alt_currency_amount $ALT_CURRENCY_NAME + " . sprintf("%.2f", $platinum_remainder) . " platinum. Check your parcels for the $ALT_CURRENCY_NAME!");
        } else {
            quest::say("You've been paid $alt_currency_amount $ALT_CURRENCY_NAME. Check your parcels!");
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
# Alt currency Helper Functions
# ==============================================================================

sub get_alt_currency_from_inventory {
    my ($client, $char_id, $db) = @_;
    # Use global $ALT_CURRENCY_ITEM_ID from configuration at top of file

    my $query = $db->prepare("
        SELECT COALESCE(SUM(charges), 0) as alt_currency_count
        FROM inventory
        WHERE character_id = ? AND item_id = ?
    ");
    $query->execute($char_id, $ALT_CURRENCY_ITEM_ID);

    my $result = $query->fetch_hashref();
    $query->close();

    return $result ? $result->{alt_currency_count} : 0;
}

sub get_alt_currency_from_alternate {
    my ($client, $char_id, $db) = @_;
    # Use global $ALT_CURRENCY_ITEM_ID from configuration at top of file

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
        SELECT COALESCE(amount, 0) as alt_currency_count
        FROM character_alt_currency
        WHERE char_id = ? AND currency_id = ?
    ");
    $query->execute($char_id, $ALT_CURRENCY_ITEM_ID);

    my $result = $query->fetch_hashref();
    $query->close();

    return $result ? $result->{alt_currency_count} : 0;
}

sub deduct_alt_currency {
    my ($client, $char_id, $db, $amount, $alt_currency_inventory, $alt_currency_alternate) = @_;
    # Use global $ALT_CURRENCY_ITEM_ID from configuration at top of file
    my $remaining = $amount;
    my $total_deducted = 0;

    # Try to take from alternate currency first using client method
    if ($alt_currency_alternate > 0 && $remaining > 0) {
        my $take_from_alternate = $alt_currency_alternate > $remaining ? $remaining : $alt_currency_alternate;

        # Use client method to remove alternate currency
        $client->RemoveAlternateCurrencyValue($ALT_CURRENCY_ITEM_ID, $take_from_alternate);

        $total_deducted += $take_from_alternate;
        $remaining -= $take_from_alternate;
        $client->Message(15, "[DEBUG] Deducted $take_from_alternate Alt currency from alternate currency") if $DEBUG_MODE;
    }

    # Take remaining from inventory using client method
    if ($remaining > 0 && $alt_currency_inventory > 0) {
        my $take_from_inventory = $alt_currency_inventory > $remaining ? $remaining : $alt_currency_inventory;

        # Use client method to remove items
        $client->RemoveItem($ALT_CURRENCY_ITEM_ID, $take_from_inventory);

        $total_deducted += $take_from_inventory;
        $remaining -= $take_from_inventory;
        $client->Message(15, "[DEBUG] Deducted $take_from_inventory Alt currency from inventory") if $DEBUG_MODE;
    }

    return $total_deducted;
}

sub add_alt_currency_to_alternate {
    my ($client, $char_id, $db, $amount) = @_;
    # Use global $ALT_CURRENCY_ITEM_ID from configuration at top of file

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
    $check_existing->execute($char_id, $ALT_CURRENCY_ITEM_ID);
    my $existing = $check_existing->fetch_hashref();
    $check_existing->close();

    if ($existing) {
        # Update existing
        my $update_query = $db->prepare("
            UPDATE character_alt_currency
            SET amount = amount + ?
            WHERE char_id = ? AND currency_id = ?
        ");
        $update_query->execute($amount, $char_id, $ALT_CURRENCY_ITEM_ID);
        $update_query->close();
    } else {
        # Insert new
        my $insert_query = $db->prepare("
            INSERT INTO character_alt_currency (char_id, currency_id, amount)
            VALUES (?, ?, ?)
        ");
        $insert_query->execute($char_id, $ALT_CURRENCY_ITEM_ID, $amount);
        $insert_query->close();
    }

    $client->Message(15, "[DEBUG] Added $amount Alt currency to alternate currency") if $DEBUG_MODE;
    return 1;
}
