# Installation Files

This directory contains all the necessary SQL scripts and quest files needed to install and configure the EQEMU Marketplace system.

## Directory Structure

```
install/
├── sql/          # Database migration scripts
└── quests/       # EQEMU quest scripts
```

---

## SQL Files (`/sql/`)

The SQL migration scripts **must be run in numerical order** to properly set up the database schema.

### 01_initial_setup.sql
**Description**: Creates the core marketplace tables
**Tables Created**:
- `marketplace_users` - User authentication and registration
- `marketplace_listings` - Item listings (active, sold, cancelled, expired)
- `marketplace_transactions` - Purchase history and transaction log

**Views Created**:
- `vw_active_marketplace_listings` - Easy querying of active listings with item details

**Indexes Created**:
- Optimized indexes for listing queries
- Transaction history indexes
- Character and item lookups

**Run Command**:
```bash
mysql -u root -p peq < 01_initial_setup.sql
```

---

### 02_marketplace_users.sql
**Description**: Additional user management features
**Tables Modified**:
- Adds admin role support
- Adds user preferences

**Run Command**:
```bash
mysql -u root -p peq < 02_marketplace_users.sql
```

---

### 03_seller_earnings.sql
**Description**: Seller earnings tracking system
**Tables Created**:
- `marketplace_seller_earnings` - Tracks pending earnings for sellers
- Allows sellers to claim earnings per character

**Features**:
- Separate earnings tracking by character
- Claim individual or all earnings
- Transaction linkage for auditing

**Run Command**:
```bash
mysql -u root -p peq < 03_seller_earnings.sql
```

---

### 04_pending_payments.sql
**Description**: Payment processing system
**Tables Modified**:
- Adds payment status columns to `marketplace_transactions`
  - `payment_status` (enum: 'pending', 'paid', 'cancelled')
  - `payment_date`
  - `reserved_date`

**Features**:
- Pending payment queue (purchases reserved before payment)
- Payment status tracking
- Allows players to purchase online and pay at NPC

**Run Command**:
```bash
mysql -u root -p peq < 04_pending_payments.sql
```

---

### 05_wtb_watchlist_notifications.sql
**Description**: Want to Buy, Watchlist, and Notification systems
**Tables Created**:
- `marketplace_wtb` - Want to Buy listings
- `marketplace_wtb_fulfillments` - WTB fulfillment tracking
- `marketplace_watchlist` - User watchlist for items
- `marketplace_notifications` - Notification system

**Views Created**:
- `vw_active_wtb_listings` - Active WTB orders with buyer details

**Stored Procedures**:
- `cleanup_expired_wtb()` - Mark expired WTB orders
- `cleanup_old_notifications()` - Clean up old read notifications

**Triggers**:
- `notify_item_sold` - Notify seller when item sells
- `notify_wtb_fulfilled` - Notify buyer when WTB order fulfilled
- `check_watchlist_on_new_listing` - Notify watchlist matches
- `notify_wtb_match_on_listing` - Notify WTB poster of matching listings

**Run Command**:
```bash
mysql -u root -p peq < 05_wtb_watchlist_notifications.sql
```

---

### 06_linked_accounts.sql
**Description**: Multi-account support for marketplace users
**Tables Created**:
- `marketplace_linked_accounts` - Links multiple game accounts to one marketplace profile

**Features**:
- Link multiple EQEMU accounts to single marketplace login
- Select from all characters across all linked accounts
- Claim purchases/earnings per account

**Run Command**:
```bash
mysql -u root -p peq < 06_linked_accounts.sql
```

---

### 07_bigint_prices.sql
**Description**: Upgrade price columns to support very high prices
**Tables Modified**:
- Changes all price columns from INT UNSIGNED to BIGINT UNSIGNED
- Supports prices up to 18 quintillion copper (18 quadrillion platinum)

**Features**:
- Idempotent - safe to run multiple times
- Only modifies tables if they exist
- Optional for fresh installs (migrations 01-06 already use BIGINT)
- Required for upgrading existing installations

**Tables Updated**:
- `marketplace_listings.price_copper`
- `marketplace_transactions.price_copper`
- `marketplace_seller_earnings.amount_copper`
- `marketplace_wtb.price_per_unit_copper`
- `marketplace_wtb_fulfillments` price columns
- `marketplace_watchlist.max_price_copper`

**Run Command**:
```bash
mysql -u root -p peq < 07_bigint_prices.sql
```

**Note**: This migration is OPTIONAL for fresh installations as tables already have BIGINT columns.

---

## Quest Scripts (`/quests/`)

### Marketplace_Broker.pl
**Description**: Main NPC quest script for marketplace interactions

**Location**: Place in `quests/global/Marketplace_Broker.pl`

**Features**:
- Accepts items from players for listing
- Checks for NO TRADE items
- Detects matching WTB orders
- Offers WTB fulfillment options
- Processes marketplace listing creation
- Validates item tradability

**NPC Setup**:
```
#npcspawn Marketplace_Broker 1 0 0 0 0
#npcedit name Marketplace_Broker
#npcedit race 1
#npcedit class 41
#npcedit level 70
```

**Dependencies**:
- Perl JSON module: `cpan install JSON`

---

### global_player.pl
**Description**: Global player event handlers for marketplace

**Location**: Place in `quests/global/global_player.pl`

**Features**:
- Handles parcel notifications
- Marketplace notification triggers
- Player login events

**Note**: If you already have a `global_player.pl`, you'll need to merge the marketplace code into your existing file.

---

## Installation Order

Follow this exact order for installation:

### 1. Database Setup

**Fresh Installation:**
```bash
cd /path/to/eqemu-marketplace/install/sql

# Run migrations in order
mysql -u root -p peq < 01_initial_setup.sql
mysql -u root -p peq < 02_marketplace_users.sql
mysql -u root -p peq < 03_seller_earnings.sql
mysql -u root -p peq < 04_pending_payments.sql
mysql -u root -p peq < 05_wtb_watchlist_notifications.sql
mysql -u root -p peq < 06_linked_accounts.sql
mysql -u root -p peq < 07_bigint_prices.sql  # Optional but safe
```

**Clean Reinstall (drop existing tables first):**
```bash
cd /path/to/eqemu-marketplace/install/sql

# Drop all existing marketplace tables
mysql -u root -p peq < 00_drop_all.sql

# Then run migrations 01-06
for i in 01 02 03 04 05 06; do
    mysql -u root -p peq < ${i}_*.sql
done
```

**Or all at once:**
```bash
for file in 0*.sql; do
    echo "Running $file..."
    mysql -u root -p peq < "$file"
done
```

### 2. Quest Scripts
```bash
cd /path/to/eqemu-marketplace/install/quests

# Copy to EQEMU server
cp Marketplace_Broker.pl /path/to/eqemu/server/quests/global/
cp global_player.pl /path/to/eqemu/server/quests/global/

# Set permissions
chown eqemu:eqemu /path/to/eqemu/server/quests/global/Marketplace_Broker.pl
chown eqemu:eqemu /path/to/eqemu/server/quests/global/global_player.pl
```

### 3. Reload Quests
In-game command:
```
#reloadquest
```

---

## Verification

After installation, verify the setup:

### Check Database Tables
```sql
SHOW TABLES LIKE 'marketplace_%';

-- Expected output (9 tables):
-- marketplace_linked_accounts
-- marketplace_listings
-- marketplace_notifications
-- marketplace_seller_earnings
-- marketplace_transactions
-- marketplace_users
-- marketplace_watchlist
-- marketplace_wtb
-- marketplace_wtb_fulfillments

-- Note: marketplace_pending_payments is NOT a separate table
-- It's implemented as columns in marketplace_transactions
```

### Check Views
```sql
SHOW FULL TABLES WHERE Table_type = 'VIEW';

-- Expected views (2):
-- vw_active_marketplace_listings
-- vw_active_wtb_listings
```

### Check Quest Scripts
```bash
# Verify files exist
ls -l /path/to/eqemu/server/quests/global/Marketplace_Broker.pl
ls -l /path/to/eqemu/server/quests/global/global_player.pl

# Check Perl syntax
perl -c /path/to/eqemu/server/quests/global/Marketplace_Broker.pl
```

---

## Troubleshooting

### SQL Import Errors

**Error**: `Table already exists`
- **Solution**: Tables are created with `IF NOT EXISTS`, this is safe to ignore

**Error**: `Foreign key constraint fails`
- **Cause**: Core EQEMU tables don't exist or have different names
- **Solution**: Verify your database is a proper PEQ schema

**Error**: `Unknown column in field list`
- **Cause**: Your EQEMU database schema is outdated
- **Solution**: Update your EQEMU server to latest PEQ database

### Quest Script Errors

**Error**: `Can't locate JSON.pm`
- **Solution**: Install Perl JSON module:
  ```bash
  cpan install JSON
  # or
  apt-get install libjson-perl
  ```

**Error**: NPC doesn't respond
- **Solution**:
  - Verify quest file is in correct location
  - Check file permissions
  - Run `#reloadquest` in-game
  - Check `eqemu_debug.log` for errors

---

## Database Schema Notes

### Character Currency Detection

The marketplace auto-detects your database schema:
- If `character_currency` table exists, uses it for platinum storage
- Otherwise, uses `character_data.platinum` column
- No configuration needed, works with both schemas

### Parcel System Compatibility

Auto-detects augment storage format:
- Supports `character_parcels.augment_1` through `augment_6` columns
- Also supports older `character_parcels.augments` text column
- Gracefully handles missing augment columns

### Item Icons

Item icons are referenced from the `items.icon` field. If you're missing icons:
- Update `ICON_BASE_URL` in `api/config.php`
- Point to your item icon server or CDN

---

## Migration from Older Versions

If upgrading from a previous marketplace installation:

1. **Backup first!**
   ```bash
   mysqldump -u root -p peq > backup_$(date +%Y%m%d).sql
   ```

2. **Check which migrations you need**
   ```sql
   SHOW TABLES LIKE 'marketplace_%';
   ```

3. **Run only missing migrations**
   - If you have `marketplace_listings` but not `marketplace_wtb`, start from `05_wtb_watchlist_notifications.sql`

4. **Never run migrations out of order!**

---

## Rollback Procedure

If you need to completely remove the marketplace:

### Drop All Tables
```sql
-- Or use the quick script:
-- mysql -u root -p peq < 00_drop_all.sql

DROP TABLE IF EXISTS marketplace_wtb_fulfillments;
DROP TABLE IF EXISTS marketplace_wtb;
DROP TABLE IF EXISTS marketplace_watchlist;
DROP TABLE IF EXISTS marketplace_seller_earnings;
DROP TABLE IF EXISTS marketplace_notifications;
DROP TABLE IF EXISTS marketplace_linked_accounts;
DROP TABLE IF EXISTS marketplace_transactions;
DROP TABLE IF EXISTS marketplace_listings;
DROP TABLE IF EXISTS marketplace_users;

-- Drop views
DROP VIEW IF EXISTS vw_active_marketplace_listings;
DROP VIEW IF EXISTS vw_active_wtb_listings;

-- Drop triggers
DROP TRIGGER IF EXISTS expire_old_listings;
DROP TRIGGER IF EXISTS notify_item_sold;
DROP TRIGGER IF EXISTS notify_wtb_fulfilled;
DROP TRIGGER IF EXISTS check_watchlist_on_new_listing;
DROP TRIGGER IF EXISTS notify_wtb_match_on_listing;

-- Drop procedures
DROP PROCEDURE IF EXISTS cleanup_expired_listings;
DROP PROCEDURE IF EXISTS cleanup_expired_wtb;
DROP PROCEDURE IF EXISTS cleanup_old_notifications;
```

### Remove Quest Files
```bash
rm /path/to/eqemu/server/quests/global/Marketplace_Broker.pl
rm /path/to/eqemu/server/quests/global/global_player.pl
```

---

## Support

For detailed installation instructions, see [../INSTALL.md](../INSTALL.md)

For feature documentation, see [../ENHANCED_FEATURES.md](../ENHANCED_FEATURES.md)

For project overview, see [../README.md](../README.md)

---

**Last Updated**: November 2025
