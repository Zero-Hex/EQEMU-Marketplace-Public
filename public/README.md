# Public Directory

This directory contains all web-accessible files for the EQEMU Marketplace.

## Directory Structure

```
public/
├── index.html              # Main application entry point
├── .htaccess              # Apache configuration (SPA routing, CORS, security)
├── api/                   # Backend PHP API endpoints
│   ├── config.php        # Configuration (loads .env from parent directory)
│   ├── auth/             # Authentication endpoints
│   ├── listings/         # Marketplace listings API
│   ├── wtb/              # Want to Buy orders API
│   ├── earnings/         # Seller earnings API
│   ├── notifications/    # Notifications API
│   └── items/            # Item search API
├── css/                   # Stylesheets
│   ├── styles.css
│   └── enhanced-features.css
└── js/                    # JavaScript application code
    ├── config.js         # Frontend configuration
    ├── api.js            # API client wrapper
    ├── app.js            # Main application logic
    ├── app-enhanced.js   # Enhanced features (WTB, Watchlist, Notifications)
    ├── app-accounts.js   # Account management
    └── app-admin.js      # Admin panel
```

## Apache Virtual Host Configuration

Point your Apache DocumentRoot to this directory:

```apache
# First, ensure Apache listens on port 8080 in /etc/apache2/ports.conf
# Add: Listen 8080

<VirtualHost *:8080>
    DocumentRoot "/path/to/eqemu-marketplace/public"
    ServerName marketplace.local

    <Directory "/path/to/eqemu-marketplace/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## Security

The parent directory contains sensitive files that should **NOT** be web-accessible:
- `.env` - Environment configuration with database credentials
- `install/` - Quest files and SQL scripts
- Documentation files

Only files in this `public/` directory should be served by your web server.

## Configuration

1. Copy `.env_example` from the parent directory to `.env` in the parent directory
2. Update `.env` with your database credentials and settings
3. The API will automatically load configuration from `../.env`

## See Also

- [Main README](../README.md) - Project overview and features
- [Installation Guide](../INSTALL.md) - Detailed setup instructions
