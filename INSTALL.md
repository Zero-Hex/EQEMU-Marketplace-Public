# EQEMU Marketplace Installation Guide

Complete step-by-step installation instructions for the EQEMU Marketplace system.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [System Requirements](#system-requirements)
3. [Installation Steps](#installation-steps)
4. [Database Setup](#database-setup)
5. [Web Server Configuration](#web-server-configuration)
6. [EQEMU Server Integration](#eqemu-server-integration)
7. [Configuration](#configuration)
8. [Testing](#testing)
9. [Troubleshooting](#troubleshooting)
10. [Upgrading](#upgrading)

---

## Prerequisites

Before installing the marketplace, ensure you have:

### Required Software
- **EQEMU Server** - Running and operational with PEQ database
- **MySQL 5.7+** or **MariaDB 10.3+**
- **PHP 7.4+** (PHP 8.0+ recommended)
  - Required extensions: `mysqli`, `pdo_mysql`, `json`, `mbstring`
- **Web Server** - Apache 2.4+ or Nginx 1.18+
- **Perl 5.10+** (for quest scripts)
  - Required modules: `JSON`, `DBI`, `DBD::mysql`

### Server Access
- SSH access to your EQEMU server
- MySQL root or admin access
- Web server configuration access
- EQEMU server directory access

### Knowledge Requirements
- Basic Linux command line skills
- MySQL database management
- Web server configuration
- Basic PHP configuration

---

## System Requirements

### Minimum Requirements
- **CPU**: 2 cores
- **RAM**: 2GB (shared with EQEMU server)
- **Disk Space**: 100MB for marketplace files
- **Database**: Existing EQEMU PEQ database

### Recommended Requirements
- **CPU**: 4+ cores
- **RAM**: 4GB+
- **Disk Space**: 500MB+ (for logs and future growth)
- **Database**: Dedicated MySQL instance with proper tuning

---

## Installation Steps

### Step 1: Download/Clone the Repository

```bash
# Navigate to your web root directory
cd /var/www/html

# Clone the repository (or download and extract)
git clone https://github.com/your-repo/eqemu-marketplace.git
cd eqemu-marketplace

# Set proper permissions
chown -R www-data:www-data /var/www/html/eqemu-marketplace
chmod -R 755 /var/www/html/eqemu-marketplace
```

### Step 2: Verify File Structure

Ensure the following structure exists:

```
eqemu-marketplace/
├── api/
├── css/
├── js/
├── install/
│   ├── sql/
│   │   ├── 00_drop_all.sql (optional - clean uninstall)
│   │   ├── fresh_install.sql (⭐ USE THIS for new installations)
│   │   ├── upgrade_bigint_prices.sql (for upgrading existing installations)
│   │   ├── 01-06_*.sql (legacy migrations - use only for partial upgrades)
│   │   └── README.md (detailed SQL documentation)
│   └── quests/
│       ├── Marketplace_Broker.pl
│       └── global_player.pl
├── index.html
└── README.md
```

---

## Database Setup

### Step 1: Backup Your Database

**CRITICAL**: Always backup before making database changes!

```bash
# Backup your EQEMU database
mysqldump -u root -p peq > peq_backup_$(date +%Y%m%d).sql
```

### Step 2: Install Database Tables

**For Fresh Installations** (Recommended - Simple One-Step Install):

```bash
cd /var/www/html/eqemu-marketplace/install/sql
mysql -u root -p peq < fresh_install.sql
```

**That's it!** The `fresh_install.sql` script creates:
- ✅ All 10 marketplace tables
- ✅ 2 views (for easy querying)
- ✅ 5 triggers (for automatic notifications)
- ✅ 3 stored procedures (for cleanup tasks)

**For Clean Reinstall** (if you have existing tables):

```bash
cd /var/www/html/eqemu-marketplace/install/sql

# WARNING: This deletes ALL marketplace data!
mysql -u root -p peq < 00_drop_all.sql

# Then run fresh install
mysql -u root -p peq < fresh_install.sql
```

**For Upgrading Existing Installations:**

If you already have marketplace tables from an older version:

```bash
cd /var/www/html/eqemu-marketplace/install/sql

# Option A: Upgrade price columns from INT to BIGINT (if needed)
mysql -u root -p peq < upgrade_bigint_prices.sql

# Option B: Run individual migrations for missing tables
# See install/sql/README.md for detailed migration guide
```

**Advanced: Custom Migration Path**

For partial installations or complex upgrade scenarios, see the detailed guide:
```bash
cat install/sql/README.md
```

### Step 3: Verify Database Tables

Connect to MySQL and verify the tables were created:

```bash
mysql -u root -p peq
```

```sql
-- Check if marketplace tables exist
SHOW TABLES LIKE 'marketplace_%';
SHOW TABLES LIKE 'wtb_%';

-- Expected output (10 tables total):
-- marketplace_linked_accounts
-- marketplace_listings
-- marketplace_notifications
-- marketplace_seller_earnings
-- marketplace_transactions
-- marketplace_users
-- marketplace_watchlist
-- marketplace_wtb
-- marketplace_wtb_fulfillments
-- wtb_pending_payments

-- Note: marketplace_pending_payments is NOT a separate table
-- It's implemented as columns in marketplace_transactions:
--   - payment_status (enum: 'pending', 'paid', 'cancelled')
--   - payment_date
--   - reserved_date

-- Note: Price columns use BIGINT UNSIGNED (migration 07)
-- This supports prices up to 18 quintillion copper
-- (10 trillion copper = 10 billion platinum)

-- Verify a key table structure
DESCRIBE marketplace_listings;

-- Check the pending payment columns exist
DESCRIBE marketplace_transactions;

-- Exit MySQL
EXIT;
```

---

## Web Server Configuration

### Apache Configuration

#### Option 1: Virtual Host (Recommended)

Create a new virtual host configuration:

```bash
sudo nano /etc/apache2/sites-available/marketplace.conf
```

Add the following configuration:

```apache
<VirtualHost *:80>
    ServerName marketplace.yourserver.com
    DocumentRoot /var/www/html/eqemu-marketplace

    <Directory /var/www/html/eqemu-marketplace>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <Directory /var/www/html/eqemu-marketplace/api>
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>

    # Enable .htaccess
    <FilesMatch "\.htaccess$">
        Require all denied
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/marketplace_error.log
    CustomLog ${APACHE_LOG_DIR}/marketplace_access.log combined
</VirtualHost>
```

Enable the site and restart Apache:

```bash
sudo a2ensite marketplace.conf
sudo systemctl restart apache2
```

#### Option 2: Subdirectory

If using a subdirectory (e.g., `http://yourserver.com/marketplace`), the included `.htaccess` file should work automatically.

Verify `.htaccess` exists:

```bash
ls -la /var/www/html/eqemu-marketplace/.htaccess
```

### Nginx Configuration

Create a new server block:

```bash
sudo nano /etc/nginx/sites-available/marketplace
```

Add the following configuration:

```nginx
server {
    listen 80;
    server_name marketplace.yourserver.com;
    root /var/www/html/eqemu-marketplace;
    index index.html;

    # Disable directory listing
    autoindex off;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /api {
        try_files $uri $uri/ =404;

        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
        }
    }

    location ~ /\.htaccess {
        deny all;
    }

    location ~ /\.git {
        deny all;
    }

    error_log /var/log/nginx/marketplace_error.log;
    access_log /var/log/nginx/marketplace_access.log;
}
```

Enable the site and restart Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/marketplace /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

---

## EQEMU Server Integration

### Step 1: Install Perl Dependencies

```bash
# Install JSON module for Perl
sudo cpan install JSON

# Or use distribution package manager
# Debian/Ubuntu:
sudo apt-get install libjson-perl

# CentOS/RHEL:
sudo yum install perl-JSON
```

### Step 2: Copy Quest Scripts

```bash
# Copy quest scripts to your EQEMU quests directory
# Adjust path to match your EQEMU installation
cd /var/www/html/eqemu-marketplace/install/quests

cp Marketplace_Broker.pl /path/to/eqemu/server/quests/global/
cp global_player.pl /path/to/eqemu/server/quests/global/

# Set proper ownership
chown eqemu:eqemu /path/to/eqemu/server/quests/global/Marketplace_Broker.pl
chown eqemu:eqemu /path/to/eqemu/server/quests/global/global_player.pl
```

### Step 3: Create the Marketplace Broker NPC

Option 1: Using GM commands in-game:

```
#npcspawn Marketplace_Broker 1 0 0 0 0
#npcedit name Marketplace_Broker
#npcedit lastname (leave blank)
#npcedit race 1 (Human)
#npcedit class 41 (Merchant)
#npcedit level 70
#npcedit size 6
```

Option 2: Insert directly into database:

```sql
INSERT INTO npc_types (
    name, lastname, level, race, class, hp, mana,
    gender, size, runspeed, walkspeed, merchant_id,
    bodytype, loottable_id, npc_spells_id, alt_currency_id
) VALUES (
    'Marketplace_Broker', '', 70, 1, 41, 5000, 5000,
    2, 6, 1.25, 0.7, 0,
    1, 0, 0, 0
);
```

Then spawn the NPC in your desired zone.

### Step 4: Reload Quests

In-game, run the command:

```
#reloadquest
```

Or restart your EQEMU server.

---

## Configuration

### Step 1: Configure Database Connection

Edit `api/config.php`:

```bash
nano /var/www/html/eqemu-marketplace/api/config.php
```

Update the following settings:

```php
<?php
// Database Configuration
define('DB_HOST', 'localhost');      // Your MySQL host
define('DB_NAME', 'peq');            // Your EQEMU database name
define('DB_USER', 'eqemu_user');     // MySQL user with access
define('DB_PASS', 'your_password');  // MySQL password

// JWT Secret - CHANGE THIS TO A RANDOM STRING!
define('JWT_SECRET', 'your-secure-random-secret-key-change-this');

// Application Settings
define('LISTING_EXPIRATION_DAYS', 7);    // How long listings stay active
define('MAX_LISTINGS_PER_USER', 50);     // Max active listings per user
define('MIN_LISTING_PRICE', 1);          // Minimum price in copper
define('COPPER_TO_PLATINUM', 1000);      // Conversion rate

// Item Icon Configuration
define('ICON_BASE_URL', 'http://65.49.60.92:8000/icons/');  // Your icon URL

// CORS Configuration (for API security)
define('ALLOWED_ORIGIN', '*');  // Change to your domain in production
?>
```

**IMPORTANT**: Generate a secure JWT secret:

```bash
# Generate a random 64-character string
openssl rand -hex 32
```

### Step 2: Configure Alternate Currency (Optional)

The marketplace supports an optional alternate currency system for high-value transactions. **By default, the system uses platinum-only** which is recommended for most servers.

**Default Behavior (Platinum-Only):**
- All transactions use platinum currency
- No alternate currency required
- Simple setup with no additional items needed

**If you want to enable alternate currency:**

1. Edit `api/config.php` and set:

```php
// Enable alternate currency system
define('USE_ALT_CURRENCY', true);  // Change from false to true

// Configure your alternate currency
define('ALT_CURRENCY_ITEM_ID', 147623);          // Your custom currency item ID
define('ALT_CURRENCY_VALUE_PLATINUM', 1000000);  // 1 currency = X platinum
define('ALT_CURRENCY_NAME', 'Bitcoin');          // Display name
```

2. Edit `install/quests/Marketplace_Broker.pl` and set:

```perl
# Alternate Currency Configuration
our $USE_ALT_CURRENCY = 1;  # Change from 0 to 1

# Configure your alternate currency
our $ALT_CURRENCY_ITEM_ID = 147623;          # Your custom currency item ID
our $ALT_CURRENCY_VALUE_PP = 1000000;        # 1 currency = X platinum
our $ALT_CURRENCY_NAME = 'Bitcoin';          # Display name
```

**Creating the Alternate Currency Item:**

If enabling alternate currency, you need to create the item in your database:

```sql
INSERT INTO items (id, name, lore, stackable, stacksize, charges, itemtype)
VALUES (
    147623,                    -- Must match ALT_CURRENCY_ITEM_ID
    'Bitcoin',                 -- Item name
    'High-value marketplace currency',
    1,                         -- Stackable
    1000,                      -- Stack size
    1,                         -- Charges (each charge = 1 currency)
    54                         -- Item type (alternate currency)
);
```

**Important Notes:**
- Both `api/config.php` and `Marketplace_Broker.pl` must have matching settings
- The item ID must exist in your items table
- The item should be stackable and use charges
- Not recommended unless you need transactions over 1 million platinum

**For most servers:** Leave USE_ALT_CURRENCY set to false (default)

---

### Step 3: Test Database Connection

Create a test file to verify database connectivity:

```bash
nano /var/www/html/eqemu-marketplace/test_db.php
```

```php
<?php
require_once 'api/config.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    echo "Database connection successful!<br>";

    // Test query
    $result = $conn->query("SELECT COUNT(*) as count FROM marketplace_users");
    $row = $result->fetch_assoc();
    echo "Marketplace users table exists with {$row['count']} users.<br>";

} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage();
}
?>
```

Access in browser: `http://yourserver.com/marketplace/test_db.php`

**Delete this file after testing!**

### Step 4: Set File Permissions

```bash
# Ensure proper permissions
cd /var/www/html/eqemu-marketplace
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Make API files executable by web server
chmod 755 api
chown -R www-data:www-data .
```

---

## Testing

### 1. Test Web Access

Open your browser and navigate to:
```
http://yourserver.com/marketplace
```

You should see the marketplace homepage.

### 2. Test Registration

1. Click "Register" button
2. Enter your EQEMU account name
3. Create a marketplace password
4. You should be logged in successfully

### 3. Test Listing Flow

**In-game:**
1. Find the Marketplace_Broker NPC
2. Hand the NPC a tradable item
3. NPC should respond with listing options

**On website:**
1. Navigate to "My Listings"
2. Your item should appear (after setting a price in-game)

### 4. Test Purchase Flow

**As a different character:**
1. Browse marketplace
2. Find an item you can afford
3. Click "Purchase Item"
4. Select your character
5. Confirm purchase

**In-game:**
1. Find a Parcel Merchant NPC
2. Say "parcel"
3. Your purchased item should be in the parcel

### 5. Test WTB System

1. Click "Want to Buy" tab
2. Click "Create WTB Order"
3. Search for an item
4. Set quantity and price
5. Submit
6. Order should appear in "My WTB Orders"

---

## Troubleshooting

### Common Issues

#### 1. "Database connection failed"

**Cause**: Incorrect database credentials

**Solution**:
- Verify `api/config.php` settings
- Test MySQL connection: `mysql -u username -p database_name`
- Check MySQL user permissions: `GRANT ALL ON peq.* TO 'user'@'localhost';`

#### 2. "JWT token invalid" errors

**Cause**: JWT_SECRET not properly configured

**Solution**:
- Ensure JWT_SECRET is set in `api/config.php`
- Clear browser cookies and re-login
- Verify JWT_SECRET is the same string everywhere

#### 3. Quest script not working

**Cause**: Perl modules not installed or script errors

**Solution**:
```bash
# Test Perl JSON module
perl -MJSON -e 'print "JSON module OK\n"'

# Check quest log for errors
tail -f /path/to/eqemu/logs/eqemu_debug.log
```

#### 4. Items not appearing in parcel

**Cause**: Parcel system compatibility issue

**Solution**:
- Verify your EQEMU database has `character_parcels` table
- Check if table has `augment_1` through `augment_6` columns OR `augments` column
- The code auto-detects which schema you have

#### 5. "403 Forbidden" when accessing API

**Cause**: Apache/Nginx configuration or permissions

**Solution**:
```bash
# Check file permissions
ls -la /var/www/html/eqemu-marketplace/api/

# Verify .htaccess is present and readable
cat /var/www/html/eqemu-marketplace/.htaccess

# Check Apache error log
tail -f /var/log/apache2/marketplace_error.log
```

#### 6. Cannot register - "Account not found"

**Cause**: Account doesn't exist in EQEMU database

**Solution**:
- Create a game account first using EQEMU account creation
- Verify account exists: `SELECT * FROM account WHERE name = 'youraccountname';`

### Debug Mode

Enable debug mode in `api/config.php`:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

**Remember to disable in production!**

---

## Upgrading

### From a Previous Version

1. **Backup everything:**
```bash
# Backup database
mysqldump -u root -p peq > peq_backup_$(date +%Y%m%d).sql

# Backup files
tar -czf marketplace_backup_$(date +%Y%m%d).tar.gz /var/www/html/eqemu-marketplace
```

2. **Pull latest code:**
```bash
cd /var/www/html/eqemu-marketplace
git pull origin main
```

3. **Run any new migrations:**
```bash
cd install/sql
# Check for new migration files with higher numbers
# Run them in order
mysql -u root -p peq < 07_new_migration.sql  # If exists
```

4. **Update quest scripts if changed:**
```bash
cp install/quests/*.pl /path/to/eqemu/server/quests/global/
```

5. **Clear browser cache and test**

---

## Security Recommendations

### Production Deployment

1. **Use HTTPS**: Configure SSL/TLS certificate
```bash
# Let's Encrypt example
sudo certbot --apache -d marketplace.yourserver.com
```

2. **Restrict API CORS**:
```php
// In api/config.php
define('ALLOWED_ORIGIN', 'https://marketplace.yourserver.com');
```

3. **Strong JWT Secret**:
```bash
# Generate secure secret
openssl rand -base64 48
```

4. **Firewall Rules**:
```bash
# Only allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
```

5. **Regular Backups**:
```bash
# Add to crontab
0 2 * * * mysqldump -u root -p peq > /backups/peq_$(date +\%Y\%m\%d).sql
```

6. **Disable directory listing** (already configured in .htaccess)

7. **Hide PHP version**:
```bash
# In php.ini
expose_php = Off
```

---

## Performance Optimization

### MySQL Optimization

Add indexes for better performance (already included in SQL scripts):

```sql
-- Verify indexes exist
SHOW INDEXES FROM marketplace_listings;
SHOW INDEXES FROM marketplace_transactions;
```

### PHP Optimization

Enable PHP OPcache in `php.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

### Apache Optimization

Enable compression:

```bash
sudo a2enmod deflate
sudo a2enmod expires
sudo systemctl restart apache2
```

---

## Support

If you encounter issues not covered in this guide:

1. Check the [README.md](README.md) for feature documentation
2. Review [ENHANCED_FEATURES.md](ENHANCED_FEATURES.md) for detailed feature info
3. Check EQEMU server logs: `eqemu/logs/eqemu_debug.log`
4. Check web server logs: `/var/log/apache2/marketplace_error.log`
5. Check MySQL error log: `/var/log/mysql/error.log`

---

## Maintenance

### Regular Tasks

**Weekly:**
- Check error logs for issues
- Verify backups are running
- Monitor disk space

**Monthly:**
- Review and archive old transactions
- Clean up expired listings
- Update software (PHP, MySQL, EQEMU)

**As Needed:**
- Clean up old notifications:
```sql
DELETE FROM marketplace_notifications
WHERE created_date < DATE_SUB(NOW(), INTERVAL 30 DAY) AND is_read = 1;
```

---

## Uninstallation

If you need to remove the marketplace:

```bash
# 1. Remove quest scripts
rm /path/to/eqemu/server/quests/global/Marketplace_Broker.pl
rm /path/to/eqemu/server/quests/global/global_player.pl

# 2. Drop database tables
mysql -u root -p peq
```

```sql
-- List all marketplace tables
SHOW TABLES LIKE 'marketplace_%';

-- Drop all marketplace tables (WARNING: This deletes all data!)
-- Note: marketplace_pending_payments and marketplace_wtb_matches don't exist as separate tables
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
DROP VIEW IF EXISTS vw_active_wtb_listings;

EXIT;
```

```bash
# 3. Remove web files
sudo rm -rf /var/www/html/eqemu-marketplace

# 4. Remove Apache/Nginx configuration
sudo rm /etc/apache2/sites-available/marketplace.conf
sudo a2dissite marketplace.conf
sudo systemctl restart apache2
```

---

**Installation complete! Your EQEMU Marketplace should now be operational.**

For questions or issues, refer to the main [README.md](README.md) documentation.
