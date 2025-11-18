// API wrapper for EQEMU Marketplace
class MarketplaceAPI {
    constructor() {
        this.baseURL = CONFIG.API_BASE_URL;
        this.token = this.getToken();
    }

    // Helper method to get auth token from storage
    getToken() {
        return localStorage.getItem(CONFIG.STORAGE_KEYS.TOKEN);
    }

    // Helper method to set auth token
    setToken(token) {
        localStorage.setItem(CONFIG.STORAGE_KEYS.TOKEN, token);
        this.token = token;
    }

    // Helper method to clear auth token
    clearToken() {
        localStorage.removeItem(CONFIG.STORAGE_KEYS.TOKEN);
        localStorage.removeItem(CONFIG.STORAGE_KEYS.USER);
        localStorage.removeItem(CONFIG.STORAGE_KEYS.CHARACTERS);
        this.token = null;
    }

    // Generic fetch wrapper with error handling
    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;

        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
            }
        };

        // Add auth token if available
        if (this.token) {
            defaultOptions.headers['Authorization'] = `Bearer ${this.token}`;
        }

        const finalOptions = { ...defaultOptions, ...options };

        if (finalOptions.body && typeof finalOptions.body !== 'string') {
            finalOptions.body = JSON.stringify(finalOptions.body);
        }

        try {
            const response = await fetch(url, finalOptions);

            // Get response text first
            const responseText = await response.text();

            // Try to parse as JSON
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON Parse Error for:', url);
                console.error('Response Status:', response.status);
                console.error('Response Text:', responseText.substring(0, 500));
                throw new Error(`Server returned invalid JSON. Response: ${responseText.substring(0, 200)}`);
            }

            if (!response.ok) {
                throw new Error(data.message || data.error || 'Request failed');
            }

            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // Authentication
    async login(username, password) {
        const data = await this.request('/auth/login.php', {
            method: 'POST',
            body: { username, password }
        });
        
        if (data.token) {
            this.setToken(data.token);
            localStorage.setItem(CONFIG.STORAGE_KEYS.USER, JSON.stringify(data.user));
            localStorage.setItem(CONFIG.STORAGE_KEYS.CHARACTERS, JSON.stringify(data.characters));
        }
        
        return data;
    }

    async register(accountName, characterId, password, confirmPassword, email = null) {
        const data = await this.request('/auth/register.php', {
            method: 'POST',
            body: {
                account_name: accountName,
                character_id: characterId,
                password: password,
                confirm_password: confirmPassword,
                email: email
            }
        });

        return data;
    }

    async logout() {
        this.clearToken();
        return { success: true };
    }

    // Get current user info
    getCurrentUser() {
        const userStr = localStorage.getItem(CONFIG.STORAGE_KEYS.USER);
        return userStr ? JSON.parse(userStr) : null;
    }

    // Get user's characters
    getCharacters() {
        const charsStr = localStorage.getItem(CONFIG.STORAGE_KEYS.CHARACTERS);
        return charsStr ? JSON.parse(charsStr) : [];
    }

    // Refresh characters from server (for active account)
    async refreshCharacters() {
        try {
            const data = await this.request('/accounts/characters.php');
            if (data.success && data.characters) {
                localStorage.setItem(CONFIG.STORAGE_KEYS.CHARACTERS, JSON.stringify(data.characters));
                return data.characters;
            }
            return [];
        } catch (error) {
            console.error('Failed to refresh characters:', error);
            return this.getCharacters(); // Fall back to cached characters
        }
    }

    // Listings API
    async getListings(filters = {}) {
        const queryParams = new URLSearchParams();

        if (filters.search) queryParams.append('search', filters.search);
        if (filters.minPrice) queryParams.append('min_price', filters.minPrice);
        if (filters.maxPrice) queryParams.append('max_price', filters.maxPrice);
        if (filters.sortBy) queryParams.append('sort_by', filters.sortBy);
        if (filters.itemType) queryParams.append('item_type', filters.itemType);
        if (filters.itemClass) queryParams.append('item_class', filters.itemClass);

        // Advanced stat filters
        if (filters.minAC) queryParams.append('min_ac', filters.minAC);
        if (filters.minHP) queryParams.append('min_hp', filters.minHP);
        if (filters.minMana) queryParams.append('min_mana', filters.minMana);
        if (filters.minSTR) queryParams.append('min_str', filters.minSTR);
        if (filters.minDEX) queryParams.append('min_dex', filters.minDEX);
        if (filters.minSTA) queryParams.append('min_sta', filters.minSTA);
        if (filters.minAGI) queryParams.append('min_agi', filters.minAGI);
        if (filters.minINT) queryParams.append('min_int', filters.minINT);
        if (filters.minWIS) queryParams.append('min_wis', filters.minWIS);
        if (filters.minCHA) queryParams.append('min_cha', filters.minCHA);
        if (filters.minFR) queryParams.append('min_fr', filters.minFR);
        if (filters.minCR) queryParams.append('min_cr', filters.minCR);
        if (filters.minMR) queryParams.append('min_mr', filters.minMR);
        if (filters.minPR) queryParams.append('min_pr', filters.minPR);
        if (filters.minDR) queryParams.append('min_dr', filters.minDR);

        // Weapon stat filters
        if (filters.minDamage) queryParams.append('min_damage', filters.minDamage);
        if (filters.maxDelay) queryParams.append('max_delay', filters.maxDelay);

        // Pagination
        if (filters.page) queryParams.append('page', filters.page);
        if (filters.limit) queryParams.append('limit', filters.limit);

        const query = queryParams.toString();
        const endpoint = query ? `/listings/list.php?${query}` : '/listings/list.php';

        return await this.request(endpoint);
    }

    async getListingById(listingId) {
        return await this.request(`/listings/get.php?id=${listingId}`);
    }

// Temporary debugging helper - add this to your api.js methods

// REPLACE your existing fetch calls with this pattern to see what's being returned:

async createListing(listingData) {
    const response = await fetch(`${this.baseURL}/listings/create.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${this.getToken()}`
        },
        body: JSON.stringify(listingData)
    });

    // DEBUG: Log the response
    const responseText = await response.text();
    console.log('RAW RESPONSE:', responseText);
    console.log('Response Status:', response.status);
    
    try {
        const data = JSON.parse(responseText);
        if (!data.success) {
            throw new Error(data.error || 'Failed to create listing');
        }
        return data;
    } catch (parseError) {
        console.error('JSON Parse Error:', parseError);
        console.error('Response was:', responseText);
        throw new Error('Server returned invalid response: ' + responseText.substring(0, 200));
    }
}

async purchaseItem(listingId, characterId) {
    const response = await fetch(`${this.baseURL}/listings/purchase.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${this.getToken()}`
        },
        body: JSON.stringify({
            listing_id: listingId,
            character_id: characterId
        })
    });

    // DEBUG: Log the response
    const responseText = await response.text();
    console.log('RAW RESPONSE:', responseText);
    console.log('Response Status:', response.status);
    
    try {
        const data = JSON.parse(responseText);
        if (!data.success) {
            throw new Error(data.error || 'Failed to purchase item');
        }
        return data;
    } catch (parseError) {
        console.error('JSON Parse Error:', parseError);
        console.error('Response was:', responseText);
        throw new Error('Server returned invalid response: ' + responseText.substring(0, 200));
    }
}

    async cancelListing(listingId) {
        return await this.request('/listings/cancel.php', {
            method: 'POST',
            body: { listing_id: listingId }
        });
    }

    async getMyListings() {
        return await this.request('/listings/my-listings.php');
    }


    async getMyPurchases() {
        return await this.request('/purchases/history.php');
    }

    // Item API (to get item details from EQEMU database)
    async getItemDetails(itemId) {
        return await this.request(`/items/get.php?id=${itemId}`);
    }

    // Character API
    async getCharacterInventory(characterId, slotId = null) {
        const endpoint = slotId 
            ? `/character/inventory.php?char_id=${characterId}&slot_id=${slotId}`
            : `/character/inventory.php?char_id=${characterId}`;
        return await this.request(endpoint);
    }

    async getCharacterDetails(characterId) {
        return await this.request(`/character/get.php?id=${characterId}`);
    }

    // Earnings API
    async getEarnings() {
        return await this.request('/earnings/get.php');
    }

    async claimEarnings() {
        return await this.request('/earnings/claim.php', {
            method: 'POST',
            body: {}
        });
    }

    async claimCharacterEarnings(characterId) {
        return await this.request('/earnings/claim-character.php', {
            method: 'POST',
            body: { character_id: characterId }
        });
    }

    // WTB (Want to Buy) API
    async getWTBListings(filters = {}) {
        const queryParams = new URLSearchParams();

        if (filters.search) queryParams.append('search', filters.search);
        if (filters.minPrice) queryParams.append('min_price', filters.minPrice);
        if (filters.maxPrice) queryParams.append('max_price', filters.maxPrice);
        if (filters.sortBy) queryParams.append('sort_by', filters.sortBy);
        if (filters.itemType) queryParams.append('item_type', filters.itemType);
        if (filters.itemClass) queryParams.append('item_class', filters.itemClass);
        if (filters.itemId) queryParams.append('item_id', filters.itemId);

        const query = queryParams.toString();
        const endpoint = query ? `/wtb/list.php?${query}` : '/wtb/list.php';

        return await this.request(endpoint);
    }

    async getMyWTBListings(characterId, status = 'active') {
        const queryParams = new URLSearchParams();
        queryParams.append('char_id', characterId);
        if (status) queryParams.append('status', status);

        return await this.request(`/wtb/my-wtb.php?${queryParams.toString()}`);
    }

    async createWTBListing(wtbData) {
        return await this.request('/wtb/create.php', {
            method: 'POST',
            body: wtbData
        });
    }

    async cancelWTBListing(wtbId, characterId) {
        return await this.request('/wtb/cancel.php', {
            method: 'POST',
            body: { wtb_id: wtbId, char_id: characterId }
        });
    }

    // Watchlist API
    async getWatchlist(characterId) {
        return await this.request(`/watchlist/my-watchlist.php?char_id=${characterId}`);
    }

    async addToWatchlist(watchlistData) {
        return await this.request('/watchlist/add.php', {
            method: 'POST',
            body: watchlistData
        });
    }

    async removeFromWatchlist(watchlistId, characterId) {
        return await this.request('/watchlist/remove.php', {
            method: 'POST',
            body: { watchlist_id: watchlistId, char_id: characterId }
        });
    }

    // Notifications API
    async getNotifications(characterId, unreadOnly = false, page = 1, perPage = 20) {
        const queryParams = new URLSearchParams();
        queryParams.append('char_id', characterId);
        if (unreadOnly) queryParams.append('unread_only', '1');
        queryParams.append('page', page);
        queryParams.append('per_page', perPage);

        return await this.request(`/notifications/list.php?${queryParams.toString()}`);
    }

    async markNotificationRead(characterId, notificationId = null) {
        const body = { char_id: characterId };
        if (notificationId) body.notification_id = notificationId;

        return await this.request('/notifications/mark-read.php', {
            method: 'POST',
            body: body
        });
    }

    async markAllNotificationsRead(characterId) {
        return await this.markNotificationRead(characterId, null);
    }

    async deleteNotification(characterId, notificationId = null) {
        const body = { char_id: characterId };
        if (notificationId) body.notification_id = notificationId;

        return await this.request('/notifications/delete.php', {
            method: 'POST',
            body: body
        });
    }

    async deleteAllNotifications(characterId) {
        return await this.deleteNotification(characterId, null);
    }

    // Item Search API (for autocomplete)
    async searchItems(query, limit = 10) {
        const queryParams = new URLSearchParams();
        queryParams.append('search', query);
        queryParams.append('limit', limit);

        return await this.request(`/items/search.php?${queryParams.toString()}`);
    }
}

// Create a global instance
const api = new MarketplaceAPI();
