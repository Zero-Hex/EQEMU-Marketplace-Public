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

## Quick Start

### Basic Installation

```bash
# 1. Clone the repository
git clone https://github.com/Zero-Hex/eqemu-marketplace-solo.git marketplace
cd marketplace

# 2. Configure environment
cp .env_example .env
nano .env  # Update database credentials and JWT secret

# 3. Import database
mysql -u root -p peq < install/sql/fresh_install.sql

# 4. Configure Apache to point to public/ directory
# See INSTALL.md for detailed Apache/XAMPP setup

# 5. Copy quest scripts to your EQEMU server
cp install/quests/*.pl /path/to/eqemu/server/quests/global/
```

**For detailed installation instructions**, including Apache virtual host configuration, Windows/XAMPP setup, production deployment, and troubleshooting, see **[INSTALL.md](INSTALL.md)**.

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

All configuration is centralized in the `.env` file:

```env
# Database
DB_HOST=localhost
DB_NAME=peq
DB_USER=your_mysql_username
DB_PASS=your_mysql_password

# Security (generate with: openssl rand -hex 32)
JWT_SECRET=your-secure-random-string

# Optional: Alternate Currency (defaults to platinum-only)
USE_ALT_CURRENCY=false
ALT_CURRENCY_NAME=Alt Currency
```

**For comprehensive configuration options**, including alternate currency setup, debug mode, pagination settings, and more, see **[CONFIGURATION.md](CONFIGURATION.md)** and **[INSTALL.md](INSTALL.md)**.

## Documentation

- **[INSTALL.md](INSTALL.md)** - Complete installation guide for Linux, Windows, and production
- **[CONFIGURATION.md](CONFIGURATION.md)** - Comprehensive configuration reference
- **[install/sql/README.md](install/sql/README.md)** - Database schema and installation guide

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
