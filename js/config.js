// Configuration file for the EQEMU Marketplace
const CONFIG = {
    // API Base URL - Update this to your XAMPP server location
    // For local XAMPP: http://localhost/eqemu-marketplace/api
    API_BASE_URL: 'http://65.49.60.92/eqemu-marketplace/api',
    
    // Session storage keys
    STORAGE_KEYS: {
        USER: 'eqemu_user',
        TOKEN: 'eqemu_token',
        CHARACTERS: 'eqemu_characters'
    },
    
    // Pagination
    ITEMS_PER_PAGE: 20,
    
    // Currency conversion
    COPPER_TO_PLATINUM: 1000,
    
    // Refresh intervals (in milliseconds)
    REFRESH_INTERVAL: 30000, // 30 seconds
    
    // Item icon base URL (if you have EQ item icons hosted)
    ICON_BASE_URL: 'http://65.49.60.92:8000/img/icons/',
    
    // Default icon for items without images
    DEFAULT_ICON: '🎒',
    
    // Inventory slots mapping
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

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CONFIG;
}
