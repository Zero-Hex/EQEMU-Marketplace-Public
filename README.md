# EQEMU Marketplace - Solo Leveling Offline Bazaar

![PHP](https://img.shields.io/badge/php-%23777BB4.svg?style=for-the-badge&logo=php&logoColor=white)![MySQL](https://img.shields.io/badge/mysql-%2300f.svg?style=for-the-badge&logo=mysql&logoColor=white)![JavaScript](https://img.shields.io/badge/javascript-%23323330.svg?style=for-the-badge&logo=javascript&logoColor=%23F7DF1E)

A comprehensive web-based marketplace system for EverQuest Emulator servers, allowing players to buy and sell items through an intuitive web interface with in-game NPC integration.

## Features

- **Browse & Purchase** - View all available items with detailed stats, purchase directly through the web
- **List Items** - In-game NPC integration for easy listing creation
- **Want to Buy (WTB)** - Post what you're looking for, sellers can fulfill your orders
- **Watchlist & Notifications** - Track items and get notified when they're listed
- **Multi-Account Support** - Link multiple game accounts to one marketplace profile
- **Earnings Management** - Claim your sales earnings directly to your characters
- **Advanced Search** - Filter by item name, stats, price range, and more
- **Mobile Responsive** - Works great on desktop and mobile devices
- **Admin Panel** - Comprehensive admin tools for marketplace management

## Requirements

- **EQEMU Server** running PEQ database
- **PHP >= 7.4** (PHP 8.0+ recommended)
- **MySQL >= 5.7** or **MariaDB >= 10.3**
- **Apache 2.4+** or **Nginx 1.18+** with mod_rewrite
- **Perl 5.10+** with JSON module

## Installation

### Local Development Setup (Linux)

```bash
# Clone to a directory outside your web root
cd /home/yourusername
git clone https://github.com/Zero-Hex/eqemu-marketplace-solo.git marketplace
cd marketplace

# Configure environment
cp .env_example .env
nano .env  # Edit with your database credentials

# Import database
mysql -u root -p peq < install/sql/fresh_install.sql

# Configure Apache virtual host (point DocumentRoot to public/ subdirectory)
sudo nano /etc/apache2/sites-available/marketplace.conf
```

**Apache Virtual Host Configuration:**
```apache
<VirtualHost *:8080>
    ServerName marketplace.local
    DocumentRoot /home/yourusername/marketplace/public  # Point to public/ subdirectory

    <Directory /home/yourusername/marketplace/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

```bash
# Enable site and restart Apache
sudo a2enmod rewrite
sudo a2ensite marketplace.conf
sudo systemctl restart apache2

# Copy quest scripts to your EQEMU server
cp install/quests/*.pl /path/to/eqemu/server/quests/global/
```

Access at `http://marketplace.local:8080`

### Local Development Setup (Windows/XAMPP)

```cmd
REM Clone or extract to C:\Marketplace\
cd C:\Marketplace

REM Configure environment
copy .env_example .env
notepad .env

REM Import database
C:\xampp\mysql\bin\mysql -u root -p peq < install\sql\fresh_install.sql
```

**XAMPP Virtual Host Configuration:**

Edit `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:
```apache
<VirtualHost *:8080>
    ServerName marketplace.local
    DocumentRoot "C:/Marketplace/public"  # Point to public subdirectory

    <Directory "C:/Marketplace/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Restart Apache in XAMPP Control Panel, then access at `http://localhost:8080`

### Production Deployment

Always install outside your publicly accessible web directory. Point your Apache DocumentRoot to the `/public` subdirectory only.

```bash
# Install to /home/eqemu/marketplace/ (NOT in web root)
# Point DocumentRoot to /home/eqemu/marketplace/public

# Generate secure JWT secret
openssl rand -hex 32

# Update .env with production values
nano .env

# Set DEBUG_MODE=false
# Set ALLOWED_ORIGIN=https://yourdomain.com
# Use strong JWT_SECRET from above

# Set up SSL (recommended)
sudo certbot --apache -d marketplace.yourdomain.com
```

### Directory Structure

```
marketplace/                    # Application root (NOT web-accessible)
├── .env                       # Configuration (database passwords, JWT secret)
├── install/                   # Quest scripts and SQL files (NOT web-accessible)
│   ├── quests/               # Perl quest scripts for EQEMU
│   └── sql/                  # Database schema and migrations
└── public/                   # DocumentRoot points HERE (web-accessible only)
    ├── index.html           # Application entry point
    ├── api/                 # PHP backend endpoints
    ├── css/                 # Stylesheets
    └── js/                  # JavaScript application
```

**Security:** Only the `public/` directory is web-accessible. The `.env` file and `install/` scripts remain protected outside the web root.

## Configuration

### Environment Variables

All configuration is done via the `.env` file (never commit this to git):

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=peq
DB_USER=your_mysql_username
DB_PASS=your_mysql_password

# Security (generate with: openssl rand -hex 32)
JWT_SECRET=your-secure-random-string

# Alternate Currency (Optional - defaults to platinum-only)
USE_ALT_CURRENCY=false
ALT_CURRENCY_ITEM_ID=147623
ALT_CURRENCY_VALUE_PLATINUM=1000000
ALT_CURRENCY_NAME=Bitcoin
```

### Alternate Currency

By default, the marketplace uses **platinum-only** transactions. To enable alternate currency for high-value items, set `USE_ALT_CURRENCY=true` in `.env` and update the matching settings in `install/quests/Marketplace_Broker.pl`.

## Documentation

- **[INSTALL.md](INSTALL.md)** - Complete installation guide with troubleshooting
- **[install/sql/README.md](install/sql/README.md)** - Database migration guide

## Screenshots

The marketplace features a modern, dark-themed interface optimized for both desktop and mobile devices.

*(Screenshots coming soon)*

## Known Limitations

- Single server marketplace (does not support cross-server trading)
- Email notifications not supported (in-game only)
- Limited to EQEMU servers running PEQ database schema

## License

This project is intended for use with EverQuest Emulator servers. EverQuest is a registered trademark of Daybreak Game Company LLC.

Licensed under the MIT License.

## Credits

Developed for Solo Leveling Offline - An EverQuest Emulator Server
