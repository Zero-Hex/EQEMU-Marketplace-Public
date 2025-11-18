# Enhanced Marketplace Features

This document describes the new features added to the Solo Leveling Offline Bazaar.

## New Features Implemented

### 1. Want to Buy (WTB) System

**What it is:** Players can create "Want to Buy" listings specifying items they want, quantity, and price they're willing to pay.

**How it works:**
- **Browse WTB Orders:** View all active WTB listings from other players
- **Create WTB Order:** Specify item, quantity, price per unit, and expiration
- **Fulfill Orders:** Sellers can browse WTB listings and fulfill them in-game via the NPC
- **Notifications:** Buyers get notified when their WTB order is fulfilled

**Database Tables:**
- `marketplace_wtb` - Stores WTB listings
- `marketplace_wtb_fulfillments` - Tracks fulfillment transactions

**API Endpoints:**
- `GET /api/wtb/list.php` - List active WTB orders
- `GET /api/wtb/my-wtb.php?char_id=X` - Get user's WTB orders
- `POST /api/wtb/create.php` - Create new WTB order
- `POST /api/wtb/cancel.php` - Cancel a WTB order

**UI Changes:**
- New "Want to Buy" tab in main navigation
- Browse all WTB orders
- Manage your own WTB orders
- Create WTB modal dialog

---

### 2. Item Stat Filtering

**What it is:** Advanced filtering system that allows searching items by their stats.

**Filters Added:**
- **Basic Stats:** AC, HP, Mana
- **Attributes:** STR, DEX, STA, AGI, INT, WIS, CHA
- **Resistances:** Fire, Cold, Magic, Poison, Disease

**How to use:**
1. Click "Advanced Filters" button
2. Enter minimum values for desired stats
3. Apply filters to see matching items

**API Changes:**
- Updated `GET /api/listings/list.php` to accept stat filter parameters:
  - `min_ac`, `min_hp`, `min_mana`
  - `min_str`, `min_dex`, `min_sta`, `min_agi`, `min_int`, `min_wis`, `min_cha`
  - `min_fr`, `min_cr`, `min_mr`, `min_pr`, `min_dr`

**UI Changes:**
- Collapsible "Advanced Filters" section
- Organized by category (Item Stats, Attributes, Resistances)

---

### 3. Watchlist & Notifications

**What it is:** Save item searches and get automatically notified when matching items are listed.

**Features:**
- **Add to Watchlist:** Save specific items or search criteria
- **Smart Matching:** Set max price, minimum stats requirements
- **Auto-Notifications:** Get notified when items matching your criteria are listed
- **Multiple Notification Types:**
  - `watchlist_match` - An item on your watchlist was listed
  - `wtb_fulfilled` - Your WTB order was fulfilled
  - `item_sold` - Your listing sold
  - `listing_expired` - Your listing expired
  - `wtb_match` - Someone listed an item you want to buy

**Database Tables:**
- `marketplace_watchlist` - Stores watchlist items
- `marketplace_notifications` - Stores notifications

**API Endpoints:**
- `GET /api/watchlist/my-watchlist.php?char_id=X` - Get watchlist
- `POST /api/watchlist/add.php` - Add to watchlist
- `POST /api/watchlist/remove.php` - Remove from watchlist
- `GET /api/notifications/list.php?char_id=X` - Get notifications
- `POST /api/notifications/mark-read.php` - Mark notifications as read

**UI Changes:**
- New "Watchlist" tab in main navigation
- Notification bell icon in header with unread count badge
- Notifications modal for viewing all notifications
- Add to Watchlist modal dialog

**Automatic Triggers:**
- When a new listing is created, the system checks all active watchlists
- Matching users receive a notification instantly
- Also checks against active WTB orders and notifies if there's a match

---

## Database Migration

**IMPORTANT:** Run the migration script to add new tables:

```bash
mysql -u your_user -p your_database < api/database_migration_wtb_watchlist.sql
```

This creates:
- WTB tables
- Watchlist tables
- Notifications tables
- Automatic triggers for notifications
- Views for easier querying
- Stored procedures for cleanup

---

## NPC Quest Integration (TODO)

The NPC quest file needs to be updated to support WTB fulfillment:

**File:** `eqemu-server/quests/global/Marketplace_Broker.pl`

**New functionality needed:**
1. Detect when a player hands an item that matches an active WTB
2. Show available WTB orders for that item
3. Allow seller to choose which WTB to fulfill
4. Process payment from buyer to seller
5. Send item to buyer via parcel
6. Update WTB quantity_fulfilled
7. Mark WTB as "fulfilled" when complete

---

## Frontend JavaScript (TODO)

The following JavaScript files need to be updated/created:

**Files to update:**
- `js/api.js` - Add new API methods for WTB, watchlist, notifications
- `js/app.js` - Add event handlers for new features

**New functionality needed:**
1. **WTB Management:**
   - Load and display WTB listings
   - Create/cancel WTB orders
   - Item search autocomplete for WTB creation

2. **Watchlist Management:**
   - Load and display watchlist
   - Add/remove watchlist items
   - Item search autocomplete for watchlist

3. **Notifications:**
   - Poll for new notifications
   - Display notification badge count
   - Show notifications in modal
   - Mark as read functionality

4. **Advanced Filters:**
   - Toggle advanced filters visibility
   - Pass stat filters to API
   - Clear all filters including advanced

5. **Tab Switching:**
   - Handle new WTB tab
   - Handle new Watchlist tab
   - Tab switching within WTB page (Browse vs My Orders)

---

## Testing Checklist

### WTB System
- [ ] Create a WTB order
- [ ] View all WTB orders
- [ ] View my WTB orders
- [ ] Cancel a WTB order
- [ ] Fulfill a WTB order (in-game via NPC)
- [ ] Receive notification when fulfilled

### Item Stat Filtering
- [ ] Filter by AC
- [ ] Filter by HP/Mana
- [ ] Filter by attributes (STR, DEX, etc.)
- [ ] Filter by resistances
- [ ] Combine multiple stat filters
- [ ] Clear filters works with advanced filters

### Watchlist
- [ ] Add item to watchlist
- [ ] Add item with price/stat requirements
- [ ] View watchlist
- [ ] Remove from watchlist
- [ ] Receive notification when watched item is listed

### Notifications
- [ ] Receive watchlist match notification
- [ ] Receive WTB fulfilled notification
- [ ] Receive item sold notification
- [ ] View all notifications
- [ ] Mark single notification as read
- [ ] Mark all notifications as read
- [ ] Notification badge count updates

---

## Known Limitations & Future Enhancements

### Current Limitations:
1. No email/external notifications (in-game only)

### Future Enhancements:
1. **Auction Mode** - Time-limited bidding on items
2. **Bundle Listings** - Sell sets of items together
3. **Trade System** - Item-for-item trades (not just platinum)
4. **Reputation System** - Buyer/seller ratings
5. **Price History** - Charts showing item price trends over time
6. **Mobile App** - Native mobile interface
7. **Analytics Dashboard** - View your selling statistics

---

## API Documentation

### WTB Endpoints

#### GET /api/wtb/list.php
List active WTB orders

**Parameters:**
- `search` - Filter by item name
- `item_type` - Filter by item type
- `item_class` - Filter by class (bitmask)
- `item_id` - Filter by specific item
- `min_price` / `max_price` - Price range (copper)
- `sort_by` - Sort order (newest, oldest, price-low, price-high, quantity)

**Response:**
```json
{
  "success": true,
  "wtb_listings": [...],
  "count": 10
}
```

#### POST /api/wtb/create.php
Create new WTB order

**Body:**
```json
{
  "char_id": 123,
  "item_id": 456,
  "quantity_wanted": 5,
  "price_per_unit": 100.50,
  "expires_days": 30,
  "notes": "Optional notes"
}
```

### Watchlist Endpoints

#### GET /api/watchlist/my-watchlist.php
Get user's watchlist

**Parameters:**
- `char_id` - Character ID (required)

#### POST /api/watchlist/add.php
Add item to watchlist

**Body:**
```json
{
  "char_id": 123,
  "item_id": 456,
  "max_price": 1000,
  "min_ac": 50,
  "min_hp": 100,
  "notes": "Optional"
}
```

### Notifications Endpoints

#### GET /api/notifications/list.php
Get notifications

**Parameters:**
- `char_id` - Character ID (required)
- `unread_only` - Only unread notifications (optional, 0/1)

**Response:**
```json
{
  "success": true,
  "notifications": [...],
  "count": 5,
  "unread_count": 2
}
```

#### POST /api/notifications/mark-read.php
Mark notifications as read

**Body:**
```json
{
  "char_id": 123,
  "notification_id": 456  // Optional, omit to mark all as read
}
```

---

## Configuration

No additional configuration required. The system uses the existing database connection and authentication.

---

## Support

For issues or questions, please refer to the main project documentation or contact the development team.
