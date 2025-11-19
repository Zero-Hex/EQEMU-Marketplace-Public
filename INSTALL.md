# EQEMU Marketplace - Installation Guide

A comprehensive web-based marketplace system for EverQuest Emulator servers with in-game NPC integration.

## Requirements

- **EQEMU Server** running PEQ database
- **PHP >= 7.4** (PHP 8.0+ recommended)
- **MySQL >= 5.7** or **MariaDB >= 10.3**
- **Apache 2.4+** or **Nginx 1.18+** with mod_rewrite
- **Perl 5.10+** with JSON module
- **Composer** (optional, for future extensions)

## Installation

### Understanding the Public/Private Structure

**Key Concept:** This application uses a public/private directory separation for security.

```
/home/yourusername/marketplace/     ← Install HERE (application root)
├── .env                            ← PRIVATE: Database credentials (NOT web-accessible)
├── .env_example                    ← PRIVATE: Template
├── install/                        ← PRIVATE: Quest scripts, SQL files (NOT web-accessible)
├── README.md                       ← PRIVATE: Documentation (NOT web-accessible)
└── public/                         ← PUBLIC: Apache DocumentRoot points HERE
    ├── index.html                  ← Web entry point
    ├── .htaccess                   ← Apache configuration
    ├── api/                        ← PHP endpoints (executed, not browsed directly)
    │   └── config.php              ← Loads .env from parent directory
    ├── css/                        ← Stylesheets
    └── js/                         ← JavaScript
```

**Apache Configuration:**
- DocumentRoot: `/home/yourusername/marketplace/public` ✅
- NOT: `/home/yourusername/marketplace` ❌

**What's protected:**
- `.env` file (contains database passwords) - NOT accessible via web
- `install/` directory (quest scripts, SQL) - NOT accessible via web
- Only `public/` contents are served by Apache

---

### Installation on Linux

**Step 1: Clone the repository (OUTSIDE your web root)**

```bash
# Clone to a directory that is NOT web-accessible
# DO NOT clone directly to /var/www/html
cd /home/yourusername  # or /opt, or anywhere outside web root
git clone https://github.com/Zero-Hex/eqemu-marketplace-solo.git marketplace
cd marketplace

# Your directory structure should now be:
# /home/yourusername/marketplace/           <- Application root (NOT web-accessible)
#   ├── .env_example                        <- Configuration template
#   ├── install/                            <- Quest scripts and SQL files (NOT web-accessible)
#   ├── public/                             <- ONLY this folder is web-accessible
#   │   ├── index.html                      <- Entry point
#   │   ├── api/                            <- PHP endpoints (executed, not browsed)
#   │   ├── css/                            <- Stylesheets
#   │   └── js/                             <- JavaScript
#   └── README.md
```

**Step 2: Configure environment**

```bash
# Copy environment file (stays in application root, NOT in public/)
cp .env_example .env

# Edit configuration
nano .env
```

**Configure your `.env` file:**

```bash
# Database Configuration
DB_HOST=localhost
DB_NAME=peq
DB_USER=your_db_user
DB_PASS=your_db_password

# JWT Secret (generate with: openssl rand -hex 32)
JWT_SECRET=your-secure-random-string-change-this

# Application Settings
LISTING_EXPIRATION_DAYS=7
MAX_LISTINGS_PER_USER=50
MIN_LISTING_PRICE=1

# Alternate Currency (Optional - defaults to platinum-only)
USE_ALT_CURRENCY=false
ALT_CURRENCY_ITEM_ID=147623
ALT_CURRENCY_VALUE_PLATINUM=1000000
ALT_CURRENCY_NAME=Alt Currency
```

**Step 3: Import database schema**

```bash
# Fresh installation (run from application root)
mysql -u root -p peq < install/sql/fresh_install.sql

# Verify tables created
mysql -u root -p peq -e "SHOW TABLES LIKE 'marketplace_%';"
```

**Step 4: Install Perl dependencies**

```bash
# Debian/Ubuntu
sudo apt-get install libjson-perl

# CentOS/RHEL
sudo yum install perl-JSON

# Or via CPAN
sudo cpan install JSON
```

**Copy quest scripts from the install/ directory:**

```bash
# Copy from the application's install/quests directory to your EQEMU server
cp install/quests/Marketplace_Broker.pl /path/to/eqemu/server/quests/global/
cp install/quests/global_player.pl /path/to/eqemu/server/quests/global/

# If using alternate currency, edit Marketplace_Broker.pl on the EQEMU server
nano /path/to/eqemu/server/quests/global/Marketplace_Broker.pl
# Set: our $USE_ALT_CURRENCY = 1; (if enabled in .env)
```

**Step 5: Configure Apache to listen on port 8080**

**IMPORTANT:** You must modify TWO Apache configuration files.

**File 1: ports.conf** (to enable port 8080)

Edit: `/etc/apache2/ports.conf`

```bash
sudo nano /etc/apache2/ports.conf
```

Add this line after the existing `Listen 80`:
```apache
Listen 80
Listen 8080    # <--- Add this line to enable port 8080
```

**File 2: Virtual Host Configuration** (to configure the marketplace)

Create: `/etc/apache2/sites-available/marketplace.conf`

```bash
sudo nano /etc/apache2/sites-available/marketplace.conf
```

```apache
<VirtualHost *:8080>
    ServerName marketplace.local

    # CRITICAL: Point to the public/ subdirectory
    DocumentRoot /home/yourusername/marketplace/public

    <Directory /home/yourusername/marketplace/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/marketplace_error.log
    CustomLog ${APACHE_LOG_DIR}/marketplace_access.log combined
</VirtualHost>
```

**Note:** Use the full path to your installation directory (e.g., `/home/yourusername/marketplace/public`).

**What this accomplishes:**
- ✅ Only `public/` directory is web-accessible
- ✅ `.env` file is NOT web-accessible (it's in parent directory)
- ✅ `install/` scripts are NOT web-accessible (outside public/)
- ✅ `public/api/config.php` loads `.env` from parent directory securely

**Alternative: Using Symlink for Enhanced Security (Recommended)**

Instead of pointing DocumentRoot directly to your installation, you can create a symlink in your web root. This provides an extra layer of separation.

```bash
# Create a symlink from /var/www/html/marketplace to your public directory
sudo ln -s /home/yourusername/marketplace/public /var/www/html/marketplace
```

Then update your virtual host configuration:
```apache
<VirtualHost *:8080>
    ServerName marketplace.local
    DocumentRoot /var/www/html/marketplace  # Points to the symlink

    <Directory /var/www/html/marketplace>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Benefits of symlink approach:**
- Application files stored outside web root entirely (`/home/yourusername/marketplace/`)
- Only the symlink exists in web root (`/var/www/html/marketplace` → `public/`)
- Easy to update: replace symlink target without touching web root
- Standard practice for Laravel and other modern frameworks

**Enable the site and restart Apache:**

After modifying both configuration files:

```bash
# Enable mod_rewrite (if not already enabled)
sudo a2enmod rewrite

# Enable the marketplace site
sudo a2ensite marketplace.conf

# Restart Apache to apply changes
sudo systemctl restart apache2
```

**Step 6: Set permissions**

```bash
# Set proper ownership (adjust path to your installation)
sudo chown -R www-data:www-data /home/yourusername/marketplace

# Set proper permissions
cd /home/yourusername/marketplace
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
```

**Step 7: Create the Marketplace Broker NPC in-game**

```
#npcspawn Marketplace_Broker 1 0 0 0 0
#npcedit name Marketplace_Broker
#npcedit race 1
#npcedit class 41
#npcedit level 70
#npcedit size 6
#reloadquest
```

**Access the marketplace:**

Open your browser and navigate to `http://marketplace.local:8080`

---

### Installation on Windows (XAMPP)

**Step 1: Download and extract to C:\Marketplace\**

```cmd
REM Download the repository or clone with git
REM Extract/clone to C:\Marketplace\

REM Your directory structure should be:
REM C:\Marketplace\                    <- Application root
REM   ├── .env_example                 <- Configuration template
REM   ├── install\                     <- Quest scripts and SQL files
REM   ├── public\                      <- XAMPP DocumentRoot points HERE
REM   │   ├── index.html
REM   │   ├── api\
REM   │   ├── css\
REM   │   └── js\
REM   └── README.md
```

**Step 2: Configure environment**

```cmd
cd C:\Marketplace
copy .env_example .env
notepad .env
```

Edit your `.env` file with your XAMPP MySQL credentials:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=peq
DB_USER=root
DB_PASS=your_xampp_mysql_password

# JWT Secret (generate with: openssl rand -hex 32 or use online generator)
JWT_SECRET=paste-secure-random-string-here

# Alternate Currency (Optional - defaults to platinum-only)
USE_ALT_CURRENCY=false
ALT_CURRENCY_ITEM_ID=147623
ALT_CURRENCY_VALUE_PLATINUM=1000000
ALT_CURRENCY_NAME=Alt Currency
```

**Step 3: Import database schema**

Open XAMPP Shell or Command Prompt:

```cmd
cd C:\Marketplace
C:\xampp\mysql\bin\mysql -u root -p peq < install\sql\fresh_install.sql

REM Verify tables created
C:\xampp\mysql\bin\mysql -u root -p peq -e "SHOW TABLES LIKE 'marketplace_%';"
```

**Step 4: Configure XAMPP to listen on port 8080**

**IMPORTANT:** You must modify TWO Apache configuration files.

**File 1: httpd.conf** (to enable port 8080)

Edit: `C:\xampp\apache\conf\httpd.conf`

Find the line that says `Listen 80` (around line 50-60) and add below it:
```apache
Listen 80
Listen 8080    # <--- Add this line to enable port 8080
```

**File 2: httpd-vhosts.conf** (to configure the virtual host)

Edit: `C:\xampp\apache\conf\extra\httpd-vhosts.conf`

Add at the end of the file:

```apache
<VirtualHost *:8080>
    ServerName marketplace.local

    # CRITICAL: Point to the public subdirectory (use forward slashes)
    DocumentRoot "C:/Marketplace/public"

    <Directory "C:/Marketplace/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "C:/xampp/apache/logs/marketplace_error.log"
    CustomLog "C:/xampp/apache/logs/marketplace_access.log" combined
</VirtualHost>
```

**Note:** Use forward slashes `/` in Apache config even on Windows.

**What this accomplishes:**
- ✅ Only `public\` directory is web-accessible
- ✅ `.env` file is NOT web-accessible (it's in C:\Marketplace\, not in public\)
- ✅ `install\` scripts are NOT web-accessible (outside public\)
- ✅ `public\api\config.php` loads `.env` from parent directory securely

**Alternative: Using Symlink for Enhanced Security (Recommended)**

Instead of pointing DocumentRoot directly to `C:/Marketplace/public`, you can create a symbolic link in your XAMPP htdocs folder. This provides an extra layer of separation.

**IMPORTANT:** Run Command Prompt as Administrator for this.

```cmd
REM Open Command Prompt as Administrator
REM Create a symbolic link (directory junction) in htdocs pointing to your public folder
mklink /D C:\xampp\htdocs\marketplace C:\Marketplace\public
```

Then update your virtual host configuration to use the symlink:
```apache
<VirtualHost *:8080>
    ServerName marketplace.local
    DocumentRoot "C:/xampp/htdocs/marketplace"  # Points to the symlink

    <Directory "C:/xampp/htdocs/marketplace">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog "C:/xampp/apache/logs/marketplace_error.log"
    CustomLog "C:/xampp/apache/logs/marketplace_access.log" combined
</VirtualHost>
```

**Benefits of symlink approach:**
- Application files stored outside web root entirely (`C:\Marketplace\`)
- Only the symlink exists in htdocs (`C:\xampp\htdocs\marketplace` → `C:\Marketplace\public`)
- Easy to update: replace symlink target without touching htdocs
- Standard practice for Laravel and other modern frameworks

**To remove a symlink later (if needed):**
```cmd
rmdir C:\xampp\htdocs\marketplace
```
Note: This removes the link only, not the actual files.

**Step 5: Restart Apache in XAMPP Control Panel**

After modifying both configuration files:

1. Open XAMPP Control Panel
2. Stop Apache if running
3. Start Apache
4. Check for any error messages in the XAMPP logs

**Step 6: Create the Marketplace Broker NPC in-game**

In-game commands (same for all platforms):

```
#npcspawn Marketplace_Broker 1 0 0 0 0
#npcedit name Marketplace_Broker
#npcedit race 1
#npcedit class 41
#npcedit level 70
#npcedit size 6
#reloadquest
```

**Step 7: Copy quest scripts to your EQEMU server**

```cmd
REM Copy from C:\Marketplace\install\quests\ to your EQEMU server's quests\global\ directory
REM If EQEMU is on the same Windows machine:
copy C:\Marketplace\install\quests\Marketplace_Broker.pl C:\eqemu\quests\global\
copy C:\Marketplace\install\quests\global_player.pl C:\eqemu\quests\global\

REM If using alternate currency, edit Marketplace_Broker.pl:
REM Set: our $USE_ALT_CURRENCY = 1; (if enabled in .env)
```

**Access the marketplace:**

Open your browser and navigate to `http://localhost:8080`

**Windows Troubleshooting:**

- **Port 8080 already in use:** Choose a different port (e.g., 8081) in both httpd.conf and httpd-vhosts.conf
- **403 Forbidden:** Check that DocumentRoot points to `C:/Marketplace/public` (with forward slashes)
- **Database connection failed:** Verify XAMPP MySQL is running and credentials in `.env` are correct
- **Can't find .env:** Make sure it's at `C:\Marketplace\.env` (not in public\ folder)

---

### Production Deployment

**Security hardening:**

```bash
# Generate strong JWT secret
openssl rand -hex 32

# Update .env with production values
nano .env
```

```bash
# Production .env settings
DEBUG_MODE=false
LOG_ERRORS=true
ALLOWED_ORIGIN=https://yourdomain.com
```

**Configure SSL (recommended):**

```bash
# Install Let's Encrypt
sudo certbot --apache -d marketplace.yourdomain.com
```

**Update CORS in `.env`:**

```bash
ALLOWED_ORIGIN=https://marketplace.yourdomain.com
```

**Optimize PHP (optional):**

```bash
sudo nano /etc/php/8.1/apache2/php.ini
```

```ini
# Enable OPcache
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60

# Hide PHP version
expose_php=Off
```

**Set up backups:**

```bash
# Add to crontab
sudo crontab -e
```

```bash
# Daily database backup at 2 AM
0 2 * * * mysqldump -u root -p'yourpassword' peq > /backups/peq_$(date +\%Y\%m\%d).sql
```

**Directory structure (production):**

For production environments, use a symlink approach (recommended):

**Step 1: Install application outside web root**
```bash
# Clone to a secure location outside web root
cd /home/eqemu
git clone https://github.com/Zero-Hex/eqemu-marketplace-solo.git marketplace
cd marketplace
cp .env_example .env
nano .env  # Configure production settings
```

**Step 2: Create symlink in web root**
```bash
# Create symbolic link from web root to public directory
sudo ln -s /home/eqemu/marketplace/public /var/www/html/marketplace
```

**Result:**
```
/home/eqemu/marketplace/              # Application root (NOT web-accessible)
├── .env                              # Production credentials
├── install/                          # Quest scripts
└── public/                           # Application files

/var/www/html/marketplace -> /home/eqemu/marketplace/public  # Symlink (web-accessible)
```

**Step 3: Configure virtual host with symlink**
```apache
<VirtualHost *:443>
    ServerName marketplace.yourdomain.com
    DocumentRoot /var/www/html/marketplace  # Points to symlink

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/marketplace.yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/marketplace.yourdomain.com/privkey.pem

    <Directory /var/www/html/marketplace>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/marketplace_error.log
    CustomLog ${APACHE_LOG_DIR}/marketplace_access.log combined
</VirtualHost>

# Redirect HTTP to HTTPS
<VirtualHost *:80>
    ServerName marketplace.yourdomain.com
    Redirect permanent / https://marketplace.yourdomain.com/
</VirtualHost>
```

---

## Configuration

### Alternate Currency (Optional)

By default, the marketplace uses **platinum-only** transactions. To enable a custom alternate currency for high-value transactions:

**1. Create the currency item in your database:**

```sql
INSERT INTO items (id, name, lore, stackable, stacksize, charges, itemtype)
VALUES (
    147623,                              -- Item ID
    'Alt Currency',                    -- Currency name
    'High-value marketplace currency',
    1,                                   -- Stackable
    1000,                                -- Stack size
    1,                                   -- Charges
    54                                   -- Item type (alternate currency)
);
```

**2. Enable in `.env`:**

```bash
USE_ALT_CURRENCY=true
ALT_CURRENCY_ITEM_ID=147623
ALT_CURRENCY_VALUE_PLATINUM=1000000
ALT_CURRENCY_NAME=Alt Currency
```

**3. Enable in `install/quests/Marketplace_Broker.pl`:**

```perl
our $USE_ALT_CURRENCY = 1;
our $ALT_CURRENCY_ITEM_ID = 147623;
our $ALT_CURRENCY_VALUE_PP = 1000000;
our $ALT_CURRENCY_NAME = 'Alt Currency';
```

**Important:** Both `.env` and `Marketplace_Broker.pl` must have matching settings.

---

### Debug Mode (Quest Script Troubleshooting)

The `Marketplace_Broker.pl` quest script includes a toggleable debug mode for troubleshooting item submission and WTB fulfillment issues.

**Enable debug mode in `install/quests/Marketplace_Broker.pl`:**

```perl
# Line 30 - Toggle debug output
our $DEBUG_MODE = 1;  # Set to 1 to enable, 0 to disable (default)
```

**What debug mode shows:**
- Item slot analysis (which slots contain items)
- Stack size/charges detection
- Quantity calculations for stackable items
- Database query results
- WTB order matching logic
- Payment calculations (platinum and alternate currency)
- Parcel creation details

**When to use debug mode:**
- Items not listing correctly
- Incorrect quantities being listed
- WTB orders not matching
- Payment calculation issues
- General troubleshooting of NPC interactions

**Example debug output:**
```
[DEBUG] ========== ITEMCOUNT HASH ==========
[DEBUG] itemcount{1001} = 1
[DEBUG] ====================================
[DEBUG] Slot 1: Item 1001, charges: 20, quantity: 20
[DEBUG] Total quantity for item 1001: 20
```

**After changing debug settings:**
```
#reloadquest
```

**Note:** Debug messages appear in yellow text (color 15) in the player's chat window. Disable debug mode in production to avoid message spam.

---

## Upgrading

### From a Previous Version

```bash
# Backup database first!
mysqldump -u root -p peq > peq_backup_$(date +%Y%m%d).sql

# Pull latest code
cd /path/to/eqemu-marketplace-solo
git pull origin main

# Update quest scripts
cp install/quests/*.pl /path/to/eqemu/server/quests/global/

# Reload quests in-game
#reloadquest

# Clear browser cache and test
```

**Note:** If database schema changes are needed, they will be documented in the release notes.

---

## Testing

### Quick Test Checklist

1. **Web Access**: Visit `http://marketplace.local:8080` - homepage loads
2. **Registration**: Register with your EQEMU account name
3. **Listing**: Hand item to Marketplace_Broker NPC in-game
4. **Browse**: View listings on website
5. **Purchase**: Buy an item, check parcel merchant in-game
6. **WTB**: Create a Want to Buy order
7. **Notifications**: Verify notification bell shows alerts

---

## Troubleshooting

### Common Issues

**Database connection failed:**
```bash
# Test MySQL connection
mysql -u username -p peq

# Grant permissions
mysql -u root -p
GRANT ALL ON peq.* TO 'username'@'localhost' IDENTIFIED BY 'password';
FLUSH PRIVILEGES;
```

**Quest script not working:**
```bash
# Test Perl JSON module
perl -MJSON -e 'print "JSON module OK\n"'

# Check quest log
tail -f /path/to/eqemu/logs/eqemu_debug.log
```

**Items not listing or incorrect quantities:**
```perl
# Enable debug mode in install/quests/Marketplace_Broker.pl
our $DEBUG_MODE = 1;  # Line 30

# Copy to server and reload
cp install/quests/Marketplace_Broker.pl /path/to/eqemu/server/quests/global/
# In-game: #reloadquest

# Debug messages will appear in yellow text showing:
# - Item slot analysis
# - Stack/charge detection
# - Quantity calculations
# - Payment processing
```
See "Debug Mode" section above for full details.

**403 Forbidden errors:**
```bash
# Check permissions
ls -la /path/to/eqemu-marketplace-solo/public/

# Check Apache error log
tail -f /var/log/apache2/marketplace_error.log
```

**JWT token errors:**
- Ensure `JWT_SECRET` is set in `.env`
- Clear browser cookies and re-login
- Verify `.env` file is in the application root (parent of `public/`)

---

## Documentation

- **[README.md](README.md)** - Project overview and features
- **[CONFIGURATION.md](CONFIGURATION.md)** - Comprehensive configuration reference
- **[install/sql/README.md](install/sql/README.md)** - Database schema and installation guide

---

## Support

For issues or questions:

1. Review EQEMU logs: `/path/to/eqemu/logs/eqemu_debug.log`
2. Check Apache logs: `/var/log/apache2/marketplace_error.log`
3. Check MySQL logs: `/var/log/mysql/error.log`

---

## License

This project is intended for use with EverQuest Emulator servers. EverQuest is a registered trademark of Daybreak Game Company LLC.

---

**Version**: 1.0.0
**Last Updated**: November 2025
