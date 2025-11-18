# Solo Leveling Offline Bazaar - User Guide

Welcome to the Solo Leveling Offline Bazaar! This comprehensive guide will teach you everything you need to know about buying, selling, and trading items on our marketplace.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Selling Items](#selling-items)
3. [Buying Items](#buying-items)
4. [Want to Buy (WTB) Orders](#want-to-buy-wtb-orders)
5. [Watchlist & Notifications](#watchlist--notifications)
6. [Managing Your Account](#managing-your-account)
7. [Claiming Earnings](#claiming-earnings)
8. [FAQ & Troubleshooting](#faq--troubleshooting)

---

## Getting Started

### Creating an Account

1. Visit the marketplace website
2. Click **Register** in the top right
3. Enter your **account name** (your login name, not character name)
4. Choose a secure **password** for the marketplace (separate from your game password)
5. Optionally add your **email** for notifications
6. Click **Register** to create your account

**Important:** Your marketplace password is separate from your game password. Never share your password with anyone!

### Logging In

1. Click **Login** in the top right
2. Enter your **account name** and **marketplace password**
3. Click **Login**

You'll now see your account name in the top right corner.

---

## Selling Items

There are two ways to sell items: through **Want to Buy (WTB) orders** for instant payment, or by **listing on the marketplace** where items sell when someone buys them.

### Finding the Marketplace Broker NPC

The Marketplace Broker NPC is located in the Plane of Knowledge (or ask your server admin where to find them).

When you hail the broker, you'll see:
- **[list items]** - Sell items on the marketplace
- **[wtb]** - Learn about Want to Buy orders
- **[pending]** - Pay for items you purchased online
- **[cancel]** - Get your item back if you change your mind

### Listing Items on the Marketplace

**Step-by-Step:**

1. **Hand your item** to the Marketplace Broker NPC
   - The NPC will check if your item is tradable
   - **NO TRADE** and **NO RENT** items will be returned immediately

2. **Check for WTB orders** (automatic)
   - If someone has a WTB order for your item, you'll see their offers
   - You can fulfill a WTB order for **instant payment**
   - Or say **"list normally"** to skip WTB and list on marketplace

3. **Set your price**
   - Type: `price 100` (for 100 platinum)
   - Prices are in **platinum only** (no gold/silver/copper)
   - Be realistic with pricing - check what similar items sell for!

4. **Your item is now listed!**
   - Buyers can see it on the website immediately
   - You'll be notified when it sells
   - Payment is held until you claim it on the website

### Example Listing Flow

```
You say, 'Hail, Marketplace Broker'
Marketplace Broker says, 'Greetings, Adventurer! Simply hand me any tradable item to get started!'

[You hand the Broker a Mithril Breastplate]

Marketplace Broker says, 'Excellent! I'll list your Mithril Breastplate on the marketplace. What price would you like to set? For example, say 'price 100' for 100 platinum.'
Marketplace Broker says, 'Say 'cancel' if you change your mind and want your item back!'

You say, 'price 5000'

Marketplace Broker says, 'Perfect! Your Mithril Breastplate has been listed for 5000 platinum. Buyers can now purchase it through the marketplace website. You will receive your payment when it sells, which you can claim on the website.'
```

### Cancelling or Getting Your Item Back

**While with the NPC (before listing is complete):**
- Simply say **"cancel"** and the NPC will return your item immediately
- You have **5 minutes** to set a price before the NPC auto-returns your item

**After item is listed on the marketplace:**
1. Go to **My Listings** on the website
2. Find your listing
3. Click **Cancel** button
4. Your item will be returned via the **Parcel system**
5. Visit any "Parcels and General Supplies" merchant to retrieve it

---

## Buying Items

### Browsing the Marketplace

**On the Website:**

1. Click **Marketplace** in the navigation
2. Browse available items or use search/filters:
   - **Search by name** - Find specific items
   - **Filter by stats** - AC, HP, Mana, Resistances, Attributes
   - **Price range** - Set min/max prices
   - **Sort by** - Price (low to high, high to low), newest, oldest

3. Click on any item to view **full details**:
   - Complete item stats
   - Augment slots
   - Number of charges
   - Seller information
   - Listed date

### Purchasing Items

**Two Purchase Methods:**

#### Method 1: Buy Online, Pay In-Game (Recommended)

1. Click **Buy Now** on an item listing
2. Confirm your purchase on the website
3. The item is **reserved** for you (no one else can buy it)
4. Visit the **Marketplace Broker NPC** in-game
5. Say **"pending"** to see your pending purchases
6. Click the **[pay]** link next to the item
7. The NPC will take payment from your character
8. Item is sent to you via **parcel** instantly!

**Example:**
```
You say, 'pending'

Marketplace Broker says, 'You have 1 pending purchase totaling 5000 platinum:'
Marketplace Broker says, '[1] Mithril Breastplate - 5000 platinum [pay 123]'
Marketplace Broker says, 'Click the [pay] link next to each item to complete your purchase.'

You say, 'pay 123'

Marketplace Broker says, 'Payment received! Your Mithril Breastplate has been sent via parcel. Check any 'Parcels and General Supplies' merchant to collect it.'
```

#### Method 2: Direct Website Purchase (Must be Offline)

1. Click **Buy Now** on an item listing
2. Confirm your purchase
3. **You must be offline or logged out**
4. Payment is taken from your character's platinum automatically
5. Item is sent via **parcel** system
6. Log back in and collect from parcel merchant

**Note:** Method 1 is safer for online players as it prevents currency desync issues.

### High-Value Purchases (Over 1 Million Platinum)

For items priced over **1,000,000 platinum**, the marketplace uses **Bitcoin** currency (custom server currency):

- **1 Bitcoin = 1,000,000 platinum**
- The NPC will automatically use your Bitcoin first for large purchases
- Bitcoin can be stored in **inventory** or **alternate currency**
- Any excess Bitcoin will be refunded as platinum

**Payment Example for 2.5M platinum item:**
- Uses: 2 Bitcoin + 500,000 platinum
- Or: 3 Bitcoin with 500,000 platinum refunded

### Collecting Your Items

After purchase, items are sent via the **Parcel System**:

1. Find any merchant named **"Parcels and General Supplies"** (usually near banks)
2. Right-click the merchant
3. Click **Parcels** tab
4. Your purchased items will be there
5. Drag items to your inventory

---

## Want to Buy (WTB) Orders

WTB orders let you post what you're looking for, and sellers come to you!

### Creating a WTB Order

**On the Website:**

1. Click **WTB** in the navigation
2. Click **Create WTB Order** button
3. Fill out the form:
   - **Item Name** - Type the item you want (uses autocomplete)
   - **Quantity** - How many you need
   - **Price Per Unit** - How much you'll pay (in platinum)
   - **Notes** (optional) - Any special requirements
4. Click **Create Order**

Your WTB order is now active!

### How WTB Orders Work

**For Buyers:**
- Your order appears on the WTB page for all sellers to see
- When a seller fulfills your order, you get a **notification**
- Payment is taken from your character automatically
- Item is sent via **parcel** system

**For Sellers:**
- When you hand an item to the Marketplace Broker, it checks for WTB orders
- If someone wants your item, you see their offers
- You can choose the best offer and get **paid instantly!**
- This is faster than waiting for a marketplace listing to sell

**Example WTB Fulfillment:**

```
[You hand a Mithril Breastplate to the Broker]

Marketplace Broker says, 'Excellent news! I found buyers looking for Mithril Breastplate!'
Marketplace Broker says, '[1] Shadowknight wants 1 for 6000 pp each. Say 'fulfill 1' to accept.'
Marketplace Broker says, '[2] Paladin wants 1 for 5500 pp each. Say 'fulfill 2' to accept.'
Marketplace Broker says, 'Or say 'list normally' to list it on the marketplace instead.'

You say, 'fulfill 1'

Marketplace Broker says, 'Excellent! I've fulfilled the order for Shadowknight!'
Marketplace Broker says, 'You've been paid 6000 platinum. The item has been sent to Shadowknight via parcel.'
```

### Managing Your WTB Orders

**On the Website:**

1. Go to **My WTB** to see your active orders
2. You can:
   - View how much has been fulfilled
   - Cancel unfulfilled orders
   - See order history

**Order Status:**
- **Active** - Still looking for items
- **Partially Fulfilled** - Some items received, still need more
- **Fulfilled** - Order complete!
- **Cancelled** - Order was cancelled

---

## Watchlist & Notifications

Never miss an item you want! The Watchlist feature alerts you when items become available.

### Adding Items to Your Watchlist

**On the Website:**

1. Click **Watchlist** in the navigation
2. Click **Add to Watchlist** button
3. Fill out the watch criteria:
   - **Item Name** - Search for specific item
   - **Max Price** - Only notify if price is below this
   - **Min Stats** (optional) - Minimum AC, HP, Mana requirements
   - **Notes** - Reminder for yourself
4. Click **Add to Watchlist**

### Notification Types

You'll receive notifications for:

- 🔔 **Watchlist Match** - An item on your watchlist was listed
- 💰 **Item Sold** - Your marketplace listing sold
- ✅ **WTB Fulfilled** - Your Want to Buy order was fulfilled
- 🛍️ **WTB Match** - Someone listed an item you want to buy
- ⏰ **Listing Expired** - Your listing expired without selling

### Checking Notifications

**On the Website:**

1. Look for the **bell icon** 🔔 in the top navigation
2. Red badge shows number of unread notifications
3. Click the bell to view all notifications
4. Click **Mark All as Read** to clear the badge

**Example Notifications:**

```
🔔 A watched item is now available: Mithril Breastplate for 4500pp
💰 Your Fungi Tunic sold for 15000pp!
✅ Your Want to Buy order for Spider Silk has been fulfilled!
```

---

## Managing Your Account

### Linking Multiple Game Accounts

If you have multiple game accounts, you can link them to your marketplace profile:

1. Click **Accounts** in the navigation
2. Click **Link New Account** button
3. Enter the **account name** and select a **validation character**
4. System verifies you own the account
5. All your characters from all accounts are now accessible!

**Benefits:**
- Buy items for any of your characters
- Sell from any character
- Claim earnings to any character

### Switching Active Account

If you have multiple accounts linked:

1. Go to **Accounts** page
2. See list of all your linked accounts
3. Click **Switch** next to the account you want to use
4. All marketplace actions now use that account

---

## Claiming Earnings

When your items sell, the payment is held as "earnings" until you claim it.

### Viewing Your Earnings

**On the Website:**

1. Click **Earnings** in the navigation
2. See:
   - Total unclaimed earnings
   - List of all sold items
   - Date each item sold
   - Amount from each sale

### Claiming Earnings to a Character

You can claim earnings to any of your characters:

**Method 1: Claim to Account**
1. Click **Claim All to Account** button
2. Platinum is added to your account's main character
3. Visit a banker or go in-game to see updated balance

**Method 2: Claim to Specific Character**
1. Select a character from the dropdown
2. Click **Claim to [Character Name]**
3. Platinum is added to that specific character
4. Visit a banker or go in-game to see updated balance

**For High-Value Earnings (Over 1 Million Platinum):**
- Earnings over 1M platinum are converted to **Bitcoin**
- Bitcoin is sent via **parcel** system
- Visit "Parcels and General Supplies" merchant to collect
- Any remaining platinum (under 1M) is added to your character

**Example:**
```
Total Earnings: 2,500,000 platinum

When you claim:
- Receive: 2 Bitcoin via parcel
- Receive: 500,000 platinum to character
```

---

## FAQ & Troubleshooting

### General Questions

**Q: Can I sell NO TRADE items?**
A: No, the Marketplace Broker will return NO TRADE and NO RENT items immediately.

**Q: How long do listings stay active?**
A: Listings stay active indefinitely until sold or cancelled. You can cancel anytime on the website.

**Q: Can I change the price of my listing?**
A: Not directly. You need to cancel the listing (item returned via parcel), then relist at new price.

**Q: What happens if I log out with a pending item?**
A: If you don't set a price within 5 minutes, the NPC automatically returns your item.

**Q: Can I buy my own listings?**
A: No, the system prevents you from buying your own items.

**Q: Do I need to be offline to buy items?**
A: No! Use the "Buy Online, Pay In-Game" method. Buy on website, then pay the NPC in-game.

### Payment & Currency

**Q: What currency does the marketplace use?**
A: Prices are in **platinum only**. For purchases over 1M platinum, **Bitcoin** is used (1 Bitcoin = 1,000,000pp).

**Q: Where does my payment go when I sell?**
A: Payment goes to "Earnings" which you claim on the website. This lets you collect from any character.

**Q: Can I get a refund on a purchase?**
A: No, all sales are final. Make sure you want the item before purchasing!

**Q: How do I pay for online purchases?**
A: Visit the Marketplace Broker NPC and say "pending" to see your purchases. Click [pay] to complete.

### Items & Parcels

**Q: How do I get my purchased items?**
A: Items are sent via the parcel system. Visit any "Parcels and General Supplies" merchant.

**Q: Do augments transfer with the item?**
A: Yes! Items keep all their augments, charges, and modifications.

**Q: What if my item doesn't arrive?**
A: Check the parcel merchant first. If still missing, contact your server admin.

**Q: Can I cancel a purchase after paying?**
A: No, purchases are final once payment is made.

### WTB Orders

**Q: Do I need funds available to create a WTB order?**
A: Yes! When someone fulfills your order, payment is taken automatically. Make sure you have enough platinum/Bitcoin.

**Q: What happens if I don't have enough funds when someone fulfills my WTB?**
A: The order is cancelled, and the seller is notified. Keep funds available!

**Q: Can I partially fulfill a WTB order?**
A: Yes! If someone wants 10 items and you only have 5, you can sell 5. The order stays active for the remaining 5.

**Q: How long do WTB orders last?**
A: WTB orders stay active until fulfilled or you cancel them manually.

### Watchlist & Notifications

**Q: How often does the watchlist check for items?**
A: Watchlist checks automatically whenever a new item is listed. Notifications are instant!

**Q: Can I have multiple watchlist entries?**
A: Yes! Add as many items as you want to watch.

**Q: Do I get email notifications?**
A: Currently only in-marketplace notifications. Email notifications are planned for the future.

**Q: Can I customize notification preferences?**
A: Not yet, but this feature is coming soon!

### Technical Issues

**Q: I can't log in!**
A: Make sure you're using your **account name** (login name) not character name. Passwords are case-sensitive.

**Q: The website shows an error when I try to buy.**
A: Try refreshing the page. If the item is already sold, it may have just been purchased by someone else.

**Q: My earnings aren't showing up.**
A: Earnings can take a minute to process. Refresh the page. If still missing, contact admin.

**Q: The NPC won't accept my item.**
A: Check if your item is NO TRADE or NO RENT. Also ensure the item is fully in your inventory (not equipped).

**Q: I said 'cancel' but didn't get my item back.**
A: Check your inventory first. If still missing, the item may have already been listed. Check My Listings on website.

---

## Tips for Success

### For Sellers

✅ **Price competitively** - Check similar items before pricing
✅ **Use WTB orders** - They sell faster and you get paid instantly
✅ **Claim earnings regularly** - Don't let earnings pile up
✅ **Cancel old listings** - If an item isn't selling, reprice it
✅ **Be descriptive** - Let buyers know what they're getting

### For Buyers

✅ **Use Watchlist** - Never miss the items you want
✅ **Create WTB orders** - Let sellers come to you
✅ **Check stats carefully** - Make sure the item fits your needs
✅ **Keep funds available** - Especially for WTB orders
✅ **Pay promptly** - Don't let pending purchases expire

### For Everyone

✅ **Be patient** - Good deals take time
✅ **Stay active** - Check notifications regularly
✅ **Communicate** - Use notes in WTB orders to specify needs
✅ **Report issues** - Help improve the marketplace for everyone
✅ **Have fun!** - The marketplace makes trading easy and safe

---

## Getting Help

**Need assistance?**

- Check this user guide first
- Visit the marketplace website help section
- Contact your server administrator
- Ask in server Discord or forums

**Report bugs or issues:**
- Describe what happened
- Include your account name (NOT password!)
- Note what page you were on
- Screenshot if possible

---

## Version History

- **v1.0** (November 2025) - Initial release
  - Basic marketplace functionality
  - WTB system
  - Watchlist & Notifications
  - Multi-account support
  - Bitcoin integration for high-value transactions

---

**Thank you for using the Solo Leveling Offline Bazaar!**

Happy trading, and may you find the best deals! 🎮✨
