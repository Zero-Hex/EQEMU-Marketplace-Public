# EQEMU Marketplace SQL Files

This directory contains SQL scripts for installing and managing the EQEMU Marketplace database.

## Quick Start - Fresh Installation

For a **new installation**, you only need **ONE file**:

```bash
mysql -u root -p your_eqemu_database < fresh_install.sql
```

That's it! This creates all 10 tables, views, triggers, and stored procedures.

---

## File Guide

| File | Purpose | Required? |
|------|---------|-----------|
| `fresh_install.sql` | **Complete installation** - Creates all tables, views, triggers, procedures | **YES** |
| `00_drop_all.sql` | Clean uninstall - Drops all marketplace tables (WARNING: deletes data!) | Optional |

---

## What Gets Created

The `fresh_install.sql` script creates:

### Tables (10 total)
1. **marketplace_users** - User authentication and accounts
2. **marketplace_listings** - Item listings for sale
3. **marketplace_transactions** - Purchase history
4. **marketplace_seller_earnings** - Pending seller payments
5. **marketplace_wtb** - Want to Buy orders
6. **marketplace_wtb_fulfillments** - WTB transaction history
7. **marketplace_watchlist** - User item watchlists
8. **marketplace_notifications** - Notification system
9. **marketplace_linked_accounts** - Multi-account linking
10. **marketplace_wtb_pending_payments** - WTB payment queue for online players

### Views (2 total)
- `vw_active_marketplace_listings` - Active listings with item details
- `vw_active_wtb_listings` - Active WTB orders with details

### Triggers (5 total)
- `expire_old_listings` - Auto-expire listings past expiration date
- `notify_item_sold` - Notify seller when item sells
- `notify_wtb_fulfilled` - Notify buyer when WTB order is fulfilled
- `check_watchlist_on_new_listing` - Notify watchlist users of new matches
- `notify_wtb_match_on_listing` - Notify WTB users when matching item is listed

### Stored Procedures (3 total)
- `cleanup_expired_listings()` - Mark expired listings
- `cleanup_expired_wtb()` - Mark expired WTB orders
- `cleanup_old_notifications()` - Delete old read notifications

---

## Installation Instructions

### Fresh Install (Recommended)

```bash
# 1. Navigate to SQL directory
cd /path/to/eqemu-marketplace/install/sql

# 2. Run fresh install
mysql -u root -p your_eqemu_database < fresh_install.sql

# 3. Verify installation
mysql -u root -p your_eqemu_database -e "SHOW TABLES LIKE 'marketplace_%';"
```

### Clean Reinstall

If you want to start fresh and remove all existing marketplace data:

```bash
# WARNING: This deletes ALL marketplace data!
mysql -u root -p your_eqemu_database < 00_drop_all.sql

# Then run fresh install
mysql -u root -p your_eqemu_database < fresh_install.sql
```

---

## Verification

After installation, verify all tables were created:

```sql
USE your_eqemu_database;

-- Should show 10 tables
SHOW TABLES LIKE 'marketplace_%';

-- Check views
SHOW FULL TABLES WHERE Table_type = 'VIEW';

-- Check triggers
SHOW TRIGGERS LIKE 'marketplace_%';

-- Check stored procedures
SHOW PROCEDURE STATUS WHERE Db = DATABASE() AND Name LIKE '%marketplace%';
SHOW PROCEDURE STATUS WHERE Db = DATABASE() AND Name LIKE '%wtb%';
```

Expected output:
- **10 tables** (marketplace_*)
- **2 views** (vw_active_marketplace_listings, vw_active_wtb_listings)
- **5 triggers**
- **3 stored procedures**

---

## Troubleshooting

### Error: "Table already exists"

If you see errors about tables already existing, drop the existing tables first:

```bash
# WARNING: This deletes all marketplace data!
mysql -u root -p your_eqemu_database < 00_drop_all.sql

# Then run fresh install
mysql -u root -p your_eqemu_database < fresh_install.sql
```

### Error: "Trigger already exists"

Drop and recreate:

```sql
DROP TRIGGER IF EXISTS expire_old_listings;
DROP TRIGGER IF EXISTS notify_item_sold;
DROP TRIGGER IF EXISTS notify_wtb_fulfilled;
DROP TRIGGER IF EXISTS check_watchlist_on_new_listing;
DROP TRIGGER IF EXISTS notify_wtb_match_on_listing;
```

Then re-run `fresh_install.sql`.

---

## Database Maintenance

### Regular Cleanup

Set up a daily cron job to clean expired listings and notifications:

```sql
-- Enable event scheduler (add to my.cnf)
SET GLOBAL event_scheduler = ON;

-- Create daily cleanup event
CREATE EVENT IF NOT EXISTS daily_marketplace_cleanup
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    CALL cleanup_expired_listings();
    CALL cleanup_expired_wtb();
    CALL cleanup_old_notifications();
END;
```

### Manual Cleanup

```sql
CALL cleanup_expired_listings();    -- Mark expired listings
CALL cleanup_expired_wtb();         -- Mark expired WTB orders
CALL cleanup_old_notifications();   -- Delete old notifications (30+ days)
```

---

## Next Steps

After database installation:

1. **Configure Environment** - Edit `.env` file in the application root with your database credentials
2. **Install Quest Scripts** - Copy quest files to your EQEMU server
3. **Create NPC** - Spawn the Marketplace_Broker NPC in-game
4. **Test Installation** - Visit the web interface and create a test listing

See the main [INSTALL.md](../../INSTALL.md) for complete setup instructions.
