# EQEMU Marketplace - Solo Leveling Offline Bazaar

A comprehensive web-based marketplace system for EverQuest Emulator (EQEMU) servers, allowing players to buy and sell items through an intuitive web interface with in-game NPC integration.

## Features

### Core Marketplace
- **Browse Listings** - View all available items for sale with detailed stats and pricing
- **Advanced Search** - Filter by item name, type, class, price range, and detailed item statistics
- **List Items** - In-game NPC integration for easy listing creation
- **Purchase Items** - Buy items directly through the web interface, delivered via in-game parcel system
- **Transaction History** - Track all your purchases and sales
- **Earnings Management** - Claim your earnings directly to your characters in-game

### Want to Buy (WTB) System
- **Create WTB Orders** - Post what items you're looking for with your offering price
- **Browse WTB Orders** - See what other players want to buy
- **WTB Fulfillment** - Sell directly to buyers through WTB orders
- **Auto-Matching** - Automatic notifications when someone lists an item you're looking for

### Watchlist & Notifications
- **Watchlist** - Save items you're interested in with price and stat requirements
- **Smart Notifications** - Get notified when:
  - An item on your watchlist is listed
  - Your WTB order is fulfilled
  - Your listing sells
  - Your listing expires
  - Someone lists an item matching your WTB order
- **Real-time Alerts** - Notification bell with unread count badge

### Enhanced Features
- **Multi-Account Support** - Link multiple game accounts to one marketplace profile
- **Character Selection** - Choose which character to use for purchases
- **Item Statistics** - View complete item stats including AC, HP, Mana, Resistances, and Attributes
- **Stat-Based Filtering** - Filter items by minimum stat requirements
- **Mobile Responsive** - iOS-style bottom navigation for mobile devices
- **Admin Panel** - Manage all listings, WTB orders, and users

## Technology Stack

- **Frontend**: Vanilla JavaScript, HTML5, CSS3
- **Backend**: PHP 7.4+, MySQL 5.7+
- **Authentication**: JWT (JSON Web Tokens)
- **In-Game Integration**: Perl quest scripts for EQEMU
- **Database**: MySQL with the EQEMU PEQ database

## Screenshots

The marketplace features a modern, dark-themed interface optimized for both desktop and mobile devices.

## Quick Start

For detailed installation instructions, see [INSTALL.md](INSTALL.md)

### Prerequisites
- EQEMU server (running PEQ database)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache or Nginx web server
- Perl with JSON module (for quest scripts)

### Basic Installation
1. Clone this repository to your web server
2. Import SQL files from `/install/sql/` in order
3. Copy quest scripts from `/install/quests/` to your EQEMU server
4. Configure database connection in `api/config.php`
5. Access the marketplace through your web browser

## Documentation

- **[INSTALL.md](INSTALL.md)** - Complete installation guide
- **[CODE_REVIEW.md](CODE_REVIEW.md)** - Code optimization and bug fixes
- **[ENHANCED_FEATURES.md](ENHANCED_FEATURES.md)** - Detailed feature documentation

## Project Structure

```
eqemu-marketplace/
├── api/                    # PHP backend API endpoints
│   ├── auth/              # Authentication endpoints
│   ├── listings/          # Marketplace listing endpoints
│   ├── wtb/               # Want to Buy endpoints
│   ├── watchlist/         # Watchlist endpoints
│   ├── notifications/     # Notification endpoints
│   ├── accounts/          # Account management
│   ├── config.php         # Database configuration
│   └── database.php       # Database helper class
├── css/                   # Stylesheets
│   ├── styles.css        # Main stylesheet
│   └── enhanced-features.css  # Additional features styling
├── js/                    # JavaScript files
│   ├── app.js            # Main application logic
│   ├── app-enhanced.js   # Enhanced features
│   └── api.js            # API client
├── install/              # Installation files
│   ├── sql/              # Database migration files
│   └── quests/           # EQEMU quest scripts
├── index.html            # Main application page
├── .env_example          # Environment configuration template
└── README.md             # This file
```

## API Endpoints

### Authentication
- `POST /api/auth/register.php` - Register new marketplace account
- `POST /api/auth/login.php` - Login to marketplace
- `POST /api/auth/verify.php` - Verify JWT token

### Listings
- `GET /api/listings/list.php` - Get all active listings
- `GET /api/listings/my-listings.php` - Get user's listings
- `POST /api/listings/create.php` - Create new listing (NPC integration)
- `POST /api/listings/purchase.php` - Purchase an item
- `POST /api/listings/cancel.php` - Cancel a listing

### Want to Buy
- `GET /api/wtb/list.php` - Get all active WTB orders
- `GET /api/wtb/my-wtb.php` - Get user's WTB orders
- `POST /api/wtb/create.php` - Create WTB order
- `POST /api/wtb/cancel.php` - Cancel WTB order

### Watchlist
- `GET /api/watchlist/my-watchlist.php` - Get user's watchlist
- `POST /api/watchlist/add.php` - Add item to watchlist
- `POST /api/watchlist/remove.php` - Remove from watchlist

### Notifications
- `GET /api/notifications/list.php` - Get notifications
- `POST /api/notifications/mark-read.php` - Mark as read

## Configuration

Edit `api/config.php` to configure database connection and other settings:

```php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'peq');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');

// JWT Secret (change this!)
define('JWT_SECRET', 'your-secure-random-string');

// Application Settings
define('LISTING_EXPIRATION_DAYS', 7);
define('MAX_LISTINGS_PER_USER', 50);
```

## In-Game Integration

### Marketplace Broker NPC

The marketplace uses an NPC called "Marketplace_Broker" that players interact with to:
- List items for sale
- Fulfill WTB orders
- Claim purchased items (via parcel system)
- Claim earnings

### Quest Script Location
Place the quest scripts in your EQEMU server's quest directory:
```
eqemu-server/quests/global/Marketplace_Broker.pl
eqemu-server/quests/global/global_player.pl
```

## Database Schema

The marketplace adds the following tables to your EQEMU database:

- `marketplace_users` - User accounts and authentication
- `marketplace_listings` - Active and historical item listings
- `marketplace_transactions` - Purchase history
- `marketplace_wtb` - Want to Buy orders
- `marketplace_wtb_fulfillments` - WTB fulfillment tracking
- `marketplace_watchlist` - User watchlist items
- `marketplace_notifications` - Notification system
- `marketplace_linked_accounts` - Multi-account support
- `marketplace_seller_earnings` - Pending earnings tracking
- `marketplace_pending_payments` - Payment processing queue

## Security Features

- **JWT Authentication** - Secure token-based authentication
- **SQL Injection Prevention** - All queries use prepared statements
- **Character Ownership Verification** - Prevents unauthorized access
- **Transaction Locking** - Prevents race conditions
- **Self-Purchase Prevention** - Can't buy your own items
- **NO TRADE Detection** - Prevents listing of non-tradable items

## Browser Support

- Chrome/Edge (recommended)
- Firefox
- Safari
- Mobile browsers (iOS Safari, Chrome Mobile)

## Performance

- **Schema Caching** - Database schema queries cached per request
- **Optimized Indexes** - Proper indexing on all critical columns
- **Transaction Efficiency** - Atomic operations with proper locking
- **Mobile Optimized** - Responsive design with lazy loading

## Contributing

This is a private server project. For bug reports or feature requests, contact the server administrator.

## Known Limitations

- Email notifications not supported (in-game only)
- Limited to EQEMU servers running PEQ database schema

## Documentation

- **[INSTALL.md](INSTALL.md)** - Complete installation guide with step-by-step instructions
- **[ENHANCED_FEATURES.md](ENHANCED_FEATURES.md)** - WTB, Watchlist, and Notifications documentation
- **[CODE_REVIEW.md](CODE_REVIEW.md)** - Previous code review and bug fixes (2025-11-14)
- **[CODE_OPTIMIZATION_REPORT.md](CODE_OPTIMIZATION_REPORT.md)** - Comprehensive optimization analysis (2025-11-17)
- **[install/sql/README.md](install/sql/README.md)** - Database installation and migration guide

## Future Enhancements

- Auction mode with time-limited bidding
- Bundle listings (sell item sets together)
- Item-for-item trade system
- Reputation/rating system
- Price history charts and analytics
- Native mobile app
- Email notification support

## License

This project is intended for use with EverQuest Emulator servers. EverQuest is a registered trademark of Daybreak Game Company LLC.

## Support

For installation help or bug reports, refer to:
- [INSTALL.md](INSTALL.md) for installation issues
- [ENHANCED_FEATURES.md](ENHANCED_FEATURES.md) for feature documentation
- Server administrator for in-game integration issues

## Credits

Developed for Solo Leveling Offline - An EverQuest Emulator Server

---

**Version**: 1.0.0
**Last Updated**: November 2025
