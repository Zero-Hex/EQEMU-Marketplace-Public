#!/usr/bin/perl

# Global Player Script for EQEMU Marketplace
# Place this in your server's quests/global/ directory

sub EVENT_ENTERZONE {
    # Check for pending WTB payments when player zones in
    # No repeating timer needed - payment and delivery happen on zone-in only
    check_wtb_pending_payments();
}

sub check_wtb_pending_payments {
    my $char_id = $client->CharacterID();
    my $db = plugin::LoadMysql();

    # Check for pending WTB payments for this character
    my $query = $db->prepare("
        SELECT id, bitcoin_amount, platinum_copper, seller_name, item_id, item_name, quantity
        FROM wtb_pending_payments
        WHERE buyer_char_id = ? AND processed = FALSE
        ORDER BY created_at ASC
        LIMIT 1
    ");
    $query->execute($char_id);

    my $payment = $query->fetchrow_hashref();
    $query->finish();

    if ($payment) {
        # Process this payment
        my $success = process_wtb_payment($db, $payment);

        if ($success) {
            # Deliver the item to buyer via parcel
            deliver_wtb_item($db, $char_id, $payment);

            # Mark payment as processed
            my $update = $db->prepare("
                UPDATE wtb_pending_payments
                SET processed = TRUE, processed_at = NOW()
                WHERE id = ?
            ");
            $update->execute($payment->{id});
            $update->finish();
        } else {
            # Payment failed - cancel the WTB order entirely
            $client->Message(13, "[Marketplace ERROR] Insufficient funds for WTB order! The order has been cancelled.");

            my $cancel_query = $db->prepare("
                UPDATE marketplace_wtb
                SET status = 'cancelled'
                WHERE id = (SELECT wtb_order_id FROM wtb_pending_payments WHERE id = ?)
            ");
            $cancel_query->execute($payment->{id});
            $cancel_query->finish();

            # Mark payment as processed (failed) so it doesn't retry
            my $update = $db->prepare("
                UPDATE wtb_pending_payments
                SET processed = TRUE, processed_at = NOW()
                WHERE id = ?
            ");
            $update->execute($payment->{id});
            $update->finish();
        }
    }
}

sub process_wtb_payment {
    my ($db, $payment) = @_;
    my $BITCOIN_ID = 147623;
    my $bitcoin_paid = 0;
    my $platinum_paid = 0;

    # Deduct Bitcoin if needed
    if ($payment->{bitcoin_amount} > 0) {
        # Remove from alternate currency first, then inventory
        $client->RemoveAlternateCurrencyValue($BITCOIN_ID, $payment->{bitcoin_amount});
        $client->RemoveItem($BITCOIN_ID, $payment->{bitcoin_amount});
        $bitcoin_paid = 1;
        $client->Message(15, "[Marketplace] Paid " . $payment->{bitcoin_amount} . " Bitcoin for WTB order");
    }

    # Deduct remaining platinum (after Bitcoin)
    if ($payment->{platinum_copper} > 0) {
        my $copper_amount = $payment->{platinum_copper};

        # Calculate denominations from copper
        my $pp = int($copper_amount / 1000);
        my $remainder = $copper_amount % 1000;
        my $gp = int($remainder / 100);
        $remainder = $remainder % 100;
        my $sp = int($remainder / 10);
        my $cp = $remainder % 10;

        # Use TakePlatinum with calculated denominations
        my $success = $client->TakePlatinum($pp, 1);

        if ($success) {
            $platinum_paid = 1;
            $client->Message(15, "[Marketplace] Paid " . $pp . "pp for WTB order");
        } else {
            $client->Message(13, "[Marketplace ERROR] Failed to deduct platinum! Please contact a GM.");
            # Don't return 0 here if Bitcoin was already taken - mark as processed to avoid re-trying
            if (!$bitcoin_paid) {
                return 0;
            }
        }
    }

    # Show completion message
    if ($bitcoin_paid || $platinum_paid) {
        $client->Message(15, "Seller " . $payment->{seller_name} . " fulfilled your WTB order:");
        $client->Message(15, $payment->{quantity} . "x " . $payment->{item_name} . " - check your parcels!");
    }

    return 1;
}

sub deliver_wtb_item {
    my ($db, $char_id, $payment) = @_;

    # Get next available parcel slot
    my $slot_query = $db->prepare("
        SELECT COALESCE(MAX(slot_id), -1) + 1 as next_slot
        FROM character_parcels
        WHERE char_id = ?
    ");
    $slot_query->execute($char_id);
    my $slot_row = $slot_query->fetchrow_hashref();
    my $next_slot = $slot_row ? $slot_row->{next_slot} : 0;
    $slot_query->finish();

    # Send item parcel
    my $parcel_query = $db->prepare("
        INSERT INTO character_parcels (
            char_id, slot_id, from_name, note, sent_date, quantity, item_id
        ) VALUES (?, ?, ?, ?, NOW(), ?, ?)
    ");

    $parcel_query->execute(
        $char_id,
        $next_slot,
        $payment->{seller_name},
        "WTB Order Fulfilled",
        $payment->{quantity},
        $payment->{item_id}
    );
    $parcel_query->finish();

    $client->Message(15, "[Marketplace] Item delivered! Check your parcels.");
}

sub EVENT_SAY {
    # !characterid command - returns the character's database ID
    if ($text =~/^!characterid$/i) {
        my $char_id = $client->CharacterID();
        my $char_name = $client->GetCleanName();

        quest::say("Your character ID is: $char_id");
        $client->Message(15, "Character: $char_name | ID: $char_id");
        $client->Message(15, "Use this ID to register your account on the marketplace.");
    }

    # !accountinfo command - returns account name and character ID
    elsif ($text =~/^!accountinfo$/i) {
        my $char_id = $client->CharacterID();
        my $char_name = $client->GetCleanName();
        my $account_id = $client->AccountID();
        my $account_name = $client->AccountName();

        $client->Message(15, "=== Account Information ===");
        $client->Message(15, "Account Name: $account_name");
        $client->Message(15, "Account ID: $account_id");
        $client->Message(15, "Character Name: $char_name");
        $client->Message(15, "Character ID: $char_id");
        $client->Message(15, "===========================");
        $client->Message(15, "Use your Account Name and Character ID to register on the marketplace.");
    }
}

# Return true to indicate the script loaded successfully
return 1;
