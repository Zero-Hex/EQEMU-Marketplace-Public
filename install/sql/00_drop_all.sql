-- Drop All Marketplace Tables, Views, Triggers, and Procedures
-- WARNING: This will DELETE ALL marketplace data!
-- Use this script if you want to completely remove the marketplace
-- or perform a clean reinstall.

-- ============================================================================
-- Drop Tables (order matters due to dependencies)
-- ============================================================================

DROP TABLE IF EXISTS marketplace_wtb_pending_payments;
DROP TABLE IF EXISTS marketplace_wtb_fulfillments;
DROP TABLE IF EXISTS marketplace_wtb;
DROP TABLE IF EXISTS marketplace_watchlist;
DROP TABLE IF EXISTS marketplace_seller_earnings;
DROP TABLE IF EXISTS marketplace_notifications;
DROP TABLE IF EXISTS marketplace_linked_accounts;
DROP TABLE IF EXISTS marketplace_transactions;
DROP TABLE IF EXISTS marketplace_listings;
DROP TABLE IF EXISTS marketplace_users;

-- ============================================================================
-- Drop Views
-- ============================================================================

DROP VIEW IF EXISTS vw_active_wtb_listings;
DROP VIEW IF EXISTS vw_active_marketplace_listings;

-- ============================================================================
-- Drop Triggers
-- ============================================================================

DROP TRIGGER IF EXISTS expire_old_listings;
DROP TRIGGER IF EXISTS notify_item_sold;
DROP TRIGGER IF EXISTS notify_wtb_fulfilled;
DROP TRIGGER IF EXISTS check_watchlist_on_new_listing;
DROP TRIGGER IF EXISTS notify_wtb_match_on_listing;

-- ============================================================================
-- Drop Stored Procedures
-- ============================================================================

DROP PROCEDURE IF EXISTS cleanup_expired_listings;
DROP PROCEDURE IF EXISTS cleanup_expired_wtb;
DROP PROCEDURE IF EXISTS cleanup_old_notifications;

-- ============================================================================
-- Cleanup Complete
-- ============================================================================

SELECT 'All marketplace tables, views, triggers, and procedures have been dropped.' AS Status;
SELECT 'You can now run fresh_install.sql for a fresh install.' AS NextSteps;
