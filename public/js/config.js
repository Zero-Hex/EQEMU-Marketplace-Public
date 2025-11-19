// Configuration file for the EQEMU Marketplace
// This loads configuration from the server API on startup
// All values are now stored in .env and served via /api/config/get.php

// Default configuration (fallback if API fails)
const DEFAULT_CONFIG = {
    API_BASE_URL: '/api',
    STORAGE_KEYS: {
        USER: 'eqemu_user',
        TOKEN: 'eqemu_token',
        CHARACTERS: 'eqemu_characters'
    },
    ITEMS_PER_PAGE: 20,
    COPPER_TO_PLATINUM: 1000,
    REFRESH_INTERVAL: 30000,
    ICON_BASE_URL: '',
    DEFAULT_ICON: '🎒',
    INVENTORY_SLOTS: {
        23: 'General Slot 1',
        24: 'General Slot 2',
        25: 'General Slot 3',
        26: 'General Slot 4',
        27: 'General Slot 5',
        28: 'General Slot 6',
        29: 'General Slot 7',
        30: 'General Slot 8'
    }
};

// Global CONFIG object - will be populated from API
let CONFIG = { ...DEFAULT_CONFIG };

// Load configuration from server
async function loadConfig() {
    try {
        const response = await fetch('/api/config/get.php');
        const data = await response.json();

        if (data.success && data.config) {
            // Map API config to frontend CONFIG object
            CONFIG = {
                API_BASE_URL: data.config.api_base_url,
                STORAGE_KEYS: data.config.storage_keys,
                ITEMS_PER_PAGE: data.config.items_per_page,
                COPPER_TO_PLATINUM: data.config.copper_to_platinum,
                REFRESH_INTERVAL: data.config.refresh_interval_ms,
                ICON_BASE_URL: data.config.icon_base_url,
                DEFAULT_ICON: data.config.default_icon,
                ENABLE_ITEM_ICONS: data.config.enable_item_icons,
                USE_ALT_CURRENCY: data.config.use_alt_currency,
                ALT_CURRENCY: data.config.alt_currency,
                INVENTORY_SLOTS: data.config.inventory_slots
            };

            return CONFIG;
        } else {
            console.warn('[Config] Failed to load from server, using defaults');
            return CONFIG;
        }
    } catch (error) {
        console.error('[Config] Error loading configuration:', error);
        console.warn('[Config] Using default configuration');
        return CONFIG;
    }
}

// Initialize config on page load
if (typeof window !== 'undefined') {
    // Load config when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => loadConfig());
    } else {
        // DOM already loaded
        loadConfig();
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CONFIG;
}
