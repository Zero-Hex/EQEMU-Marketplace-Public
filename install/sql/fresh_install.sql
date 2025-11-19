-- ============================================================================
-- EQEMU Marketplace - Complete Fresh Installation
-- ============================================================================
-- This script creates all necessary tables, views, triggers, and procedures
-- for a fresh EQEMU Marketplace installation.
--
-- Compatible with PEQ database schema (MySQL 5.7+ / MariaDB 10.3+)
--
-- IMPORTANT: For fresh installations only!
-- If you're upgrading an existing installation, use the individual migration files.
--
-- Tables Created:
--   1. marketplace_users - User authentication
--   2. marketplace_listings - Item listings
--   3. marketplace_transactions - Purchase history
--   4. marketplace_seller_earnings - Pending seller payments
--   5. marketplace_wtb - Want to Buy orders
--   6. marketplace_wtb_fulfillments - WTB transaction history
--   7. marketplace_watchlist - User item watchlists
--   8. marketplace_notifications - Notification system
--   9. marketplace_linked_accounts - Multi-account support
--  10. marketplace_wtb_pending_payments - WTB payment queue for online players
-- ============================================================================

-- ============================================================================
-- 1. MARKETPLACE USERS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS marketplace_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL UNIQUE,
    account_name VARCHAR(30) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    registered_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT 1,
    active_account_id INT UNSIGNED DEFAULT NULL,

    INDEX idx_account_id (account_id),
    INDEX idx_account_name (account_name),
    INDEX idx_active_account (active_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 2. MARKETPLACE LISTINGS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS marketplace_listings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seller_char_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    quantity INT DEFAULT 1,
    price_copper BIGINT UNSIGNED NOT NULL,
    augment_1 INT UNSIGNED DEFAULT 0,
    augment_2 INT UNSIGNED DEFAULT 0,
    augment_3 INT UNSIGNED DEFAULT 0,
    augment_4 INT UNSIGNED DEFAULT 0,
    augment_5 INT UNSIGNED DEFAULT 0,
    augment_6 INT UNSIGNED DEFAULT 0,
    charges INT DEFAULT 0,
    custom_data TEXT,
    listed_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_date TIMESTAMP NULL,
    status ENUM('active', 'sold', 'cancelled', 'expired') DEFAULT 'active',
    buyer_char_id INT UNSIGNED NULL,
    purchased_date TIMESTAMP NULL,

    INDEX idx_status (status),
    INDEX idx_item (item_id),
    INDEX idx_seller (seller_char_id),
    INDEX idx_buyer (buyer_char_id),
    INDEX idx_listing_active (status, listed_date DESC),
    INDEX idx_item_seller_status (item_id, seller_char_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 3. MARKETPLACE TRANSACTIONS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS marketplace_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id INT UNSIGNED NOT NULL,
    seller_char_id INT UNSIGNED NOT NULL,
    buyer_char_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    quantity INT DEFAULT 1,
    price_copper BIGINT UNSIGNED NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_status ENUM('pending', 'paid', 'cancelled') DEFAULT 'paid',
    payment_date TIMESTAMP NULL,
    reserved_date TIMESTAMP NULL,

    INDEX idx_seller (seller_char_id),
    INDEX idx_buyer (buyer_char_id),
    INDEX idx_date (transaction_date),
    INDEX idx_listing (listing_id),
    INDEX idx_transaction_buyer_date (buyer_char_id, transaction_date DESC),
    INDEX idx_transaction_seller_date (seller_char_id, transaction_date DESC),
    INDEX idx_buyer_payment_status (buyer_char_id, payment_status),
    INDEX idx_payment_status_date (payment_status, reserved_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 4. MARKETPLACE SELLER EARNINGS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS marketplace_seller_earnings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seller_char_id INT UNSIGNED NOT NULL,
    amount_copper BIGINT NOT NULL,
    source_listing_id INT UNSIGNED NULL,
    earned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    claimed BOOLEAN DEFAULT FALSE,
    claimed_date TIMESTAMP NULL,
    notes VARCHAR(255) NULL,

    INDEX idx_seller_claimed (seller_char_id, claimed),
    INDEX idx_earned_date (earned_date),
    INDEX idx_seller_char (seller_char_id),
    INDEX idx_source_listing (source_listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 5. MARKETPLACE WTB (WANT TO BUY) TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS marketplace_wtb (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buyer_char_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    quantity_wanted INT UNSIGNED NOT NULL DEFAULT 1,
    quantity_fulfilled INT UNSIGNED NOT NULL DEFAULT 0,
    price_per_unit_copper BIGINT UNSIGNED NOT NULL,
    notes TEXT NULL,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_date TIMESTAMP NULL,
    status ENUM('active', 'fulfilled', 'expired', 'cancelled') DEFAULT 'active',

    INDEX idx_status (status),
    INDEX idx_item (item_id),
    INDEX idx_buyer (buyer_char_id),
    INDEX idx_active_item (status, item_id),
    INDEX idx_wtb_active_item (status, item_id, created_date DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 6. MARKETPLACE WTB FULFILLMENTS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS marketplace_wtb_fulfillments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wtb_id INT UNSIGNED NOT NULL,
    seller_char_id INT UNSIGNED NOT NULL,
    buyer_char_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    price_per_unit_copper BIGINT UNSIGNED NOT NULL,
    total_price_copper BIGINT UNSIGNED NOT NULL,
    augment_1 INT UNSIGNED DEFAULT 0,
    augment_2 INT UNSIGNED DEFAULT 0,
    augment_3 INT UNSIGNED DEFAULT 0,
    augment_4 INT UNSIGNED DEFAULT 0,
    augment_5 INT UNSIGNED DEFAULT 0,
    augment_6 INT UNSIGNED DEFAULT 0,
    charges INT UNSIGNED DEFAULT 0,
    fulfillment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',

    INDEX idx_wtb (wtb_id),
    INDEX idx_seller (seller_char_id),
    INDEX idx_buyer (buyer_char_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 7. MARKETPLACE WATCHLIST TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS marketplace_watchlist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    char_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NULL,
    item_name_search VARCHAR(255) NULL,
    max_price_copper BIGINT UNSIGNED NULL,
    min_ac INT UNSIGNED NULL,
    min_hp INT UNSIGNED NULL,
    min_mana INT UNSIGNED NULL,
    notes TEXT NULL,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT 1,

    INDEX idx_char (char_id),
    INDEX idx_item (item_id),
    INDEX idx_active (is_active),
    INDEX idx_watchlist_active_item (is_active, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 8. MARKETPLACE NOTIFICATIONS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS marketplace_notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    char_id INT UNSIGNED NOT NULL,
    notification_type ENUM('watchlist_match', 'wtb_fulfilled', 'item_sold', 'listing_expired', 'wtb_match') NOT NULL,
    message TEXT NOT NULL,
    related_listing_id INT UNSIGNED NULL,
    related_wtb_id INT UNSIGNED NULL,
    related_item_id INT UNSIGNED NULL,
    is_read BOOLEAN DEFAULT 0,
    created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_char_unread (char_id, is_read),
    INDEX idx_created (created_date),
    INDEX idx_type (notification_type),
    INDEX idx_notifications_char_unread_date (char_id, is_read, created_date DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 9. MARKETPLACE LINKED ACCOUNTS TABLE
-- ============================================================================
CREATE TABLE IF NOT EXISTS marketplace_linked_accounts (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    marketplace_user_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    account_name VARCHAR(30) NOT NULL,
    validation_character_id INT UNSIGNED NOT NULL,
    linked_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY unique_account (account_id),
    INDEX idx_marketplace_user (marketplace_user_id),
    INDEX idx_account (account_id),
    INDEX idx_validation_char (validation_character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 10. WTB PENDING PAYMENTS TABLE
-- ============================================================================
-- Stores payment requests for online WTB buyers to be processed by global controller
CREATE TABLE IF NOT EXISTS marketplace_wtb_pending_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_char_id INT NOT NULL,
    bitcoin_amount INT DEFAULT 0,
    platinum_copper INT NOT NULL,
    wtb_order_id INT NOT NULL,
    seller_name VARCHAR(64),
    item_id INT NOT NULL,
    item_name VARCHAR(255),
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP NULL,

    INDEX idx_buyer_pending (buyer_char_id, processed),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- VIEWS
-- ============================================================================

-- View: Active Marketplace Listings
CREATE OR REPLACE VIEW vw_active_marketplace_listings AS
SELECT
    ml.id,
    ml.seller_char_id,
    cd.name as seller_name,
    ml.item_id,
    i.name as item_name,
    i.icon,
    ml.quantity,
    ml.price_copper,
    ml.augment_1,
    ml.augment_2,
    ml.augment_3,
    ml.augment_4,
    ml.augment_5,
    ml.augment_6,
    ml.charges,
    ml.listed_date,
    ml.expires_date
FROM marketplace_listings ml
JOIN character_data cd ON ml.seller_char_id = cd.id
JOIN items i ON ml.item_id = i.id
WHERE ml.status = 'active'
ORDER BY ml.listed_date DESC;

-- View: Active WTB Listings
CREATE OR REPLACE VIEW vw_active_wtb_listings AS
SELECT
    w.id,
    w.buyer_char_id,
    cd.name as buyer_name,
    w.item_id,
    i.name as item_name,
    i.icon,
    w.quantity_wanted,
    w.quantity_fulfilled,
    (w.quantity_wanted - w.quantity_fulfilled) as quantity_remaining,
    w.price_per_unit_copper,
    w.notes,
    w.created_date,
    w.expires_date
FROM marketplace_wtb w
JOIN character_data cd ON w.buyer_char_id = cd.id
JOIN items i ON w.item_id = i.id
WHERE w.status = 'active'
AND w.quantity_fulfilled < w.quantity_wanted
ORDER BY w.created_date DESC;

-- ============================================================================
-- TRIGGERS
-- ============================================================================

DELIMITER $$

-- Trigger: Automatically expire old listings
CREATE TRIGGER IF NOT EXISTS expire_old_listings
BEFORE UPDATE ON marketplace_listings
FOR EACH ROW
BEGIN
    IF NEW.status = 'active' AND NEW.expires_date IS NOT NULL AND NEW.expires_date < NOW() THEN
        SET NEW.status = 'expired';
    END IF;
END$$

-- Trigger: Notify seller when item sells
CREATE TRIGGER IF NOT EXISTS notify_item_sold
AFTER UPDATE ON marketplace_listings
FOR EACH ROW
BEGIN
    IF OLD.status = 'active' AND NEW.status = 'sold' THEN
        INSERT INTO marketplace_notifications (
            char_id,
            notification_type,
            message,
            related_listing_id,
            related_item_id
        ) VALUES (
            NEW.seller_char_id,
            'item_sold',
            CONCAT('Your ', (SELECT name FROM items WHERE id = NEW.item_id), ' sold for ', FLOOR(NEW.price_copper / 1000), 'pp!'),
            NEW.id,
            NEW.item_id
        );
    END IF;
END$$

-- Trigger: Notify when WTB is fulfilled
CREATE TRIGGER IF NOT EXISTS notify_wtb_fulfilled
AFTER UPDATE ON marketplace_wtb
FOR EACH ROW
BEGIN
    IF OLD.quantity_fulfilled < OLD.quantity_wanted AND NEW.quantity_fulfilled >= NEW.quantity_wanted THEN
        INSERT INTO marketplace_notifications (
            char_id,
            notification_type,
            message,
            related_wtb_id,
            related_item_id
        ) VALUES (
            NEW.buyer_char_id,
            'wtb_fulfilled',
            CONCAT('Your Want to Buy order for ', (SELECT name FROM items WHERE id = NEW.item_id), ' has been fulfilled!'),
            NEW.id,
            NEW.item_id
        );
    END IF;
END$$

-- Trigger: Check watchlist when new listing is created
CREATE TRIGGER IF NOT EXISTS check_watchlist_on_new_listing
AFTER INSERT ON marketplace_listings
FOR EACH ROW
BEGIN
    -- Notify users who have this item on their watchlist
    INSERT INTO marketplace_notifications (char_id, notification_type, message, related_listing_id, related_item_id)
    SELECT
        w.char_id,
        'watchlist_match',
        CONCAT('A watched item is now available: ', i.name, ' for ', FLOOR(NEW.price_copper / 1000), 'pp'),
        NEW.id,
        NEW.item_id
    FROM marketplace_watchlist w
    JOIN items i ON w.item_id = i.id
    WHERE w.is_active = 1
    AND w.item_id = NEW.item_id
    AND (w.max_price_copper IS NULL OR NEW.price_copper <= w.max_price_copper)
    AND (w.min_ac IS NULL OR i.ac >= w.min_ac)
    AND (w.min_hp IS NULL OR i.hp >= w.min_hp)
    AND (w.min_mana IS NULL OR i.mana >= w.min_mana)
    AND w.char_id != NEW.seller_char_id;
END$$

-- Trigger: Notify sellers when their item matches an active WTB
CREATE TRIGGER IF NOT EXISTS notify_wtb_match_on_listing
AFTER INSERT ON marketplace_listings
FOR EACH ROW
BEGIN
    -- Notify the WTB poster that someone listed their wanted item
    INSERT INTO marketplace_notifications (char_id, notification_type, message, related_listing_id, related_wtb_id, related_item_id)
    SELECT
        w.buyer_char_id,
        'wtb_match',
        CONCAT('Someone listed an item you want to buy: ', i.name, ' for ', FLOOR(NEW.price_copper / 1000), 'pp'),
        NEW.id,
        w.id,
        NEW.item_id
    FROM marketplace_wtb w
    JOIN items i ON w.item_id = i.id
    WHERE w.status = 'active'
    AND w.item_id = NEW.item_id
    AND w.quantity_fulfilled < w.quantity_wanted
    AND NEW.price_copper <= w.price_per_unit_copper
    AND w.buyer_char_id != NEW.seller_char_id;
END$$

DELIMITER ;

-- ============================================================================
-- STORED PROCEDURES
-- ============================================================================

DELIMITER $$

-- Procedure: Cleanup expired listings
CREATE PROCEDURE IF NOT EXISTS cleanup_expired_listings()
BEGIN
    -- Mark expired listings
    UPDATE marketplace_listings
    SET status = 'expired'
    WHERE status = 'active'
    AND expires_date IS NOT NULL
    AND expires_date < NOW();
END$$

-- Procedure: Cleanup expired WTB listings
CREATE PROCEDURE IF NOT EXISTS cleanup_expired_wtb()
BEGIN
    -- Mark expired WTB listings
    UPDATE marketplace_wtb
    SET status = 'expired'
    WHERE status = 'active'
    AND expires_date IS NOT NULL
    AND expires_date < NOW();

    -- Notify users of expired WTB listings
    INSERT INTO marketplace_notifications (char_id, notification_type, message, related_wtb_id, related_item_id)
    SELECT
        w.buyer_char_id,
        'listing_expired',
        CONCAT('Your Want to Buy order for ', i.name, ' has expired'),
        w.id,
        w.item_id
    FROM marketplace_wtb w
    JOIN items i ON w.item_id = i.id
    WHERE w.status = 'expired'
    AND w.buyer_char_id NOT IN (
        SELECT char_id FROM marketplace_notifications
        WHERE related_wtb_id = w.id
        AND notification_type = 'listing_expired'
    );
END$$

-- Procedure: Clean old notifications (older than 30 days)
CREATE PROCEDURE IF NOT EXISTS cleanup_old_notifications()
BEGIN
    DELETE FROM marketplace_notifications
    WHERE created_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND is_read = 1;
END$$

DELIMITER ;

-- ============================================================================
-- INSTALLATION COMPLETE
-- ============================================================================
SELECT '✓ Fresh installation complete!' AS Status;
SELECT 'All tables, views, triggers, and stored procedures created successfully.' AS Message;
SELECT 'Next steps: Configure api/config.php and install quest scripts.' AS NextSteps;
