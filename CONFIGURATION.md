# Configuration Guide

The EQEMU Marketplace uses a centralized configuration system where **all settings are stored in the `.env` file** and loaded by different components.

## Quick Start

1. **Copy the example file:**
   ```bash
   cp .env_example .env
   ```

2. **Edit `.env` with your settings:**
   ```bash
   nano .env
   ```

3. **Required settings:**
   - Database credentials (DB_HOST, DB_NAME, DB_USER, DB_PASS)
   - JWT secret (generate with: `openssl rand -hex 32`)

4. **Optional settings:**
   - Adjust as needed for your server

## Configuration Files

### `.env` - Master Configuration (PRIMARY SOURCE OF TRUTH)

**Location:** `/path/to/eqemu-marketplace-solo/.env`

This is the **single source of truth** for all configuration. All other files read from this.

```bash
# Database
DB_HOST=localhost
DB_NAME=peq
DB_USER=your_user
DB_PASS=your_password

# Security
JWT_SECRET=your_generated_secret_key

# Item Icons
ICON_BASE_URL=https://yourserver.com/icons
DEFAULT_ICON=🎒

# Pagination
ITEMS_PER_PAGE=20

# Currency
COPPER_TO_PLATINUM=1000

# Alternate Currency (Optional)
USE_ALT_CURRENCY=false
ALT_CURRENCY_ITEM_ID=147623
ALT_CURRENCY_VALUE_PLATINUM=1000000
ALT_CURRENCY_NAME=Alt Currency

# Refresh
REFRESH_INTERVAL_SECONDS=30
```

### `public/api/config.php` - Backend Configuration Loader

**Location:** `/path/to/eqemu-marketplace-solo/public/api/config.php`

**Purpose:** Reads `.env` and exposes values to PHP backend

**How it works:**
- Automatically loads `.env` from parent directory
- Defines PHP constants (DB_HOST, JWT_SECRET, etc.)
- Provides `env()` helper function
- Serves configuration to frontend via API

**Example usage in PHP:**
```php
require_once '../config.php';

// All values come from .env
$useAltCurrency = USE_ALT_CURRENCY;  // From .env
$itemsPerPage = ITEMS_PER_PAGE;      // From .env
$iconUrl = ICON_BASE_URL;             // From .env
```

### `public/api/config/get.php` - Configuration API Endpoint

**Location:** `/path/to/eqemu-marketplace-solo/public/api/config/get.php`

**Purpose:** Provides configuration to frontend JavaScript

**Endpoint:** `GET /api/config/get.php`

**Response:**
```json
{
  "success": true,
  "config": {
    "icon_base_url": "https://yourserver.com/icons",
    "items_per_page": 20,
    "copper_to_platinum": 1000,
    "use_alt_currency": false,
    "refresh_interval_ms": 30000
  }
}
```

### `public/js/config.js` - Frontend Configuration

**Location:** `/path/to/eqemu-marketplace-solo/public/js/config.js`

**Purpose:** Loads configuration from API for frontend use

**How it works:**
- Fetches configuration from `/api/config/get.php` on page load
- Falls back to defaults if API fails
- Provides global `CONFIG` object for JavaScript

**Example usage in JavaScript:**
```javascript
// After config loads:
const iconUrl = CONFIG.ICON_BASE_URL;
const perPage = CONFIG.ITEMS_PER_PAGE;
```

### `install/quests/Marketplace_Broker.pl` - NPC Quest Configuration

**Location:** `/path/to/eqemu-marketplace-solo/install/quests/Marketplace_Broker.pl`

**Purpose:** NPC quest script configuration

**IMPORTANT:** EQEmu quest scripts cannot read `.env` files. You must **manually sync** these settings with your `.env` file:

```perl
# Lines 17-30 - Configuration variables
our $USE_ALT_CURRENCY = 0;              # Match: USE_ALT_CURRENCY in .env
our $ALT_CURRENCY_ITEM_ID = 147623;     # Match: ALT_CURRENCY_ITEM_ID in .env
our $ALT_CURRENCY_VALUE_PP = 1000000;   # Match: ALT_CURRENCY_VALUE_PLATINUM in .env
our $ALT_CURRENCY_NAME = 'Alt Currency';     # Match: ALT_CURRENCY_NAME in .env
our $DEBUG_MODE = 0;                    # Toggle debug output (1=on, 0=off)
```

**After changing:** Run `#reloadquest` in-game

**Debug Mode:** Set `$DEBUG_MODE = 1` to enable detailed troubleshooting output for item submissions, quantity calculations, WTB matching, and payment processing. Disable in production to avoid message spam.

## Configuration Flow

```
┌─────────────┐
│   .env      │  ← Single source of truth
│ (root dir)  │
└──────┬──────┘
       │
       ├──────────────────────────────────┐
       │                                  │
       ▼                                  ▼
┌──────────────┐                  ┌─────────────────┐
│ config.php   │                  │ Marketplace_    │
│ (Backend)    │                  │ Broker.pl       │
└──────┬───────┘                  │ (NPC Quest)     │
       │                          │                 │
       │                          │ ⚠️  Manual sync │
       ▼                          │    required!    │
┌──────────────┐                  └─────────────────┘
│ /api/config/ │
│ get.php      │
└──────┬───────┘
       │
       ▼
┌──────────────┐
│ config.js    │
│ (Frontend)   │
└──────────────┘
```

## Available Configuration Options

### Database Settings
| Variable | Description | Required | Example |
|----------|-------------|----------|---------|
| `DB_HOST` | MySQL host | ✅ Yes | `localhost` |
| `DB_NAME` | Database name | ✅ Yes | `peq` |
| `DB_USER` | MySQL username | ✅ Yes | `eqemu` |
| `DB_PASS` | MySQL password | ✅ Yes | `password123` |

### Security Settings
| Variable | Description | Required | Example |
|----------|-------------|----------|---------|
| `JWT_SECRET` | JWT signing key | ✅ Yes | Generate with `openssl rand -hex 32` |
| `JWT_EXPIRATION` | Token lifetime (seconds) | ❌ No | `86400` (24h) |
| `ALLOWED_ORIGIN` | CORS origin | ❌ No | `*` (dev) or domain (prod) |

### Frontend Settings
| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `ICON_BASE_URL` | Item icon server URL | ❌ No | `` (empty = emoji) |
| `DEFAULT_ICON` | Fallback icon | ❌ No | `🎒` |
| `ITEMS_PER_PAGE` | Pagination size | ❌ No | `20` |
| `COPPER_TO_PLATINUM` | Currency conversion | ❌ No | `1000` |
| `REFRESH_INTERVAL_SECONDS` | Auto-refresh interval | ❌ No | `30` |
| `ENABLE_ITEM_ICONS` | Show item icons | ❌ No | `true` |

### Alternate Currency Settings
| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `USE_ALT_CURRENCY` | Enable alternate currency | ❌ No | `false` |
| `ALT_CURRENCY_ITEM_ID` | Item ID for currency | ❌ No | `147623` |
| `ALT_CURRENCY_VALUE_PLATINUM` | Value in platinum | ❌ No | `1000000` |
| `ALT_CURRENCY_NAME` | Display name | ❌ No | `Alt Currency` |

### Application Settings
| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `SITE_URL` | Base site URL | ❌ No | `http://localhost/eqemu-marketplace` |
| `API_URL` | API base URL | ❌ No | `http://localhost/eqemu-marketplace/api` |
| `LISTING_EXPIRATION_DAYS` | Days until listing expires | ❌ No | `7` |
| `MAX_LISTINGS_PER_USER` | Max active listings | ❌ No | `50` |
| `MIN_LISTING_PRICE` | Minimum price (copper) | ❌ No | `1` |

### Quest Script Settings (Marketplace_Broker.pl)

These settings are configured directly in `install/quests/Marketplace_Broker.pl` (NOT in `.env`):

| Variable | Description | Required | Default |
|----------|-------------|----------|---------|
| `$DEBUG_MODE` | Enable debug output for troubleshooting | ❌ No | `0` (disabled) |
| `$USE_ALT_CURRENCY` | Must match .env USE_ALT_CURRENCY | ⚠️ Sync | `0` (false) |
| `$ALT_CURRENCY_ITEM_ID` | Must match .env ALT_CURRENCY_ITEM_ID | ⚠️ Sync | `147623` |
| `$ALT_CURRENCY_VALUE_PP` | Must match .env ALT_CURRENCY_VALUE_PLATINUM | ⚠️ Sync | `1000000` |
| `$ALT_CURRENCY_NAME` | Must match .env ALT_CURRENCY_NAME | ⚠️ Sync | `Alt Currency` |

**Note:** After changing quest script settings, run `#reloadquest` in-game.

## Common Configuration Tasks

### Change Item Icon Server

1. **Edit `.env`:**
   ```bash
   ICON_BASE_URL=https://icons.eqitems.com
   ```

2. **Restart web server** (PHP will reload .env)

3. **Refresh browser** (frontend will fetch new config)

### Enable Alternate Currency

1. **Edit `.env`:**
   ```bash
   USE_ALT_CURRENCY=true
   ALT_CURRENCY_ITEM_ID=147623
   ALT_CURRENCY_VALUE_PLATINUM=1000000
   ALT_CURRENCY_NAME=Alt Currency
   ```

2. **Edit `install/quests/Marketplace_Broker.pl`:**
   ```perl
   our $USE_ALT_CURRENCY = 1;  # Match .env
   our $ALT_CURRENCY_ITEM_ID = 147623;
   our $ALT_CURRENCY_VALUE_PP = 1000000;
   our $ALT_CURRENCY_NAME = 'Alt Currency';
   ```

3. **Reload quest in-game:**
   ```
   #reloadquest
   ```

4. **Refresh marketplace website**

### Change Pagination Size

1. **Edit `.env`:**
   ```bash
   ITEMS_PER_PAGE=50
   ```

2. **Refresh browser** (frontend fetches new value automatically)

### Change Database Credentials

1. **Edit `.env`:**
   ```bash
   DB_HOST=newhost
   DB_USER=newuser
   DB_PASS=newpassword
   ```

2. **Restart web server** (Apache/Nginx must reload PHP)

### Enable Debug Mode (Troubleshooting)

Debug mode shows detailed output for quest script troubleshooting.

1. **Edit `install/quests/Marketplace_Broker.pl`:**
   ```perl
   our $DEBUG_MODE = 1;  # Enable debug output
   ```

2. **Copy to your EQEMU server:**
   ```bash
   cp install/quests/Marketplace_Broker.pl /path/to/eqemu/server/quests/global/
   ```

3. **Reload quest in-game:**
   ```
   #reloadquest
   ```

4. **Test item submission** - debug messages appear in yellow text

5. **When done troubleshooting, disable debug mode:**
   ```perl
   our $DEBUG_MODE = 0;  # Disable to prevent message spam
   ```

**What debug mode shows:**
- Item slot contents and charges
- Quantity calculations for stacks
- WTB order matching
- Payment calculations
- Database operations

## Troubleshooting

### Frontend not getting new config values

**Problem:** Changed `.env` but frontend still shows old values

**Solution:**
1. Hard refresh browser: `Ctrl+Shift+R` (Windows) or `Cmd+Shift+R` (Mac)
2. Clear browser cache
3. Check browser console for config load errors
4. Verify `/api/config/get.php` returns new values

### NPC using different settings than website

**Problem:** Alternate currency works on website but not in-game

**Solution:**
1. Check `Marketplace_Broker.pl` lines 17-27 match `.env`
2. Run `#reloadquest` in-game after changing quest file
3. Check quest file syntax: `perl -c Marketplace_Broker.pl`

### Config API not working

**Problem:** `/api/config/get.php` returns errors

**Solution:**
1. Check `.env` file exists and has correct values
2. Check PHP error log: `tail -f /var/log/php-errors.log`
3. Verify `config.php` can read `.env` (check file permissions)

### Changes not taking effect

**Problem:** Changed `.env` but nothing happened

**Solution:**
- **PHP changes:** Restart web server (Apache/Nginx)
- **JavaScript changes:** Hard refresh browser
- **Quest changes:** Run `#reloadquest` in-game

## Security Best Practices

1. **Never commit `.env` to git** (it's in `.gitignore`)
2. **Generate strong JWT_SECRET:** `openssl rand -hex 32`
3. **Set ALLOWED_ORIGIN in production:** Use your domain, not `*`
4. **Protect `.env` file permissions:** `chmod 600 .env`
5. **Keep `.env` outside web root** (currently in parent of `public/`)

## Summary

✅ **Single Source of Truth:** All config in `.env`
✅ **Backend:** Reads `.env` via `config.php`
✅ **Frontend:** Fetches from `/api/config/get.php`
⚠️ **NPC Quest:** Must manually sync with `.env`
✅ **No Hardcoded Values:** Everything configurable
✅ **Environment-Specific:** Different `.env` per environment

For more information, see [INSTALL.md](INSTALL.md) and [README.md](README.md).
