// API wrapper for EQEMU Marketplace
class MarketplaceAPI {
    constructor() {
        this.baseURL = CONFIG?.API_BASE_URL || '/api';
        this.token = this.getToken();
        this._pendingRequests = new Map(); // For request deduplication
        this.defaultTimeout = 30000; // 30 seconds
        this.maxRetries = 3;
    }

    // Helper method to get auth token from storage
    getToken() {
        const TOKEN_KEY = CONFIG?.STORAGE_KEYS?.TOKEN || 'eqemu_token';
        return localStorage.getItem(TOKEN_KEY);
    }

    // Helper method to set auth token
    setToken(token) {
        const TOKEN_KEY = CONFIG?.STORAGE_KEYS?.TOKEN || 'eqemu_token';
        localStorage.setItem(TOKEN_KEY, token);
        this.token = token;
    }

    // Helper method to clear auth token
    clearToken() {
        // Use hardcoded keys as fallback in case CONFIG isn't loaded yet
        const TOKEN_KEY = CONFIG?.STORAGE_KEYS?.TOKEN || 'eqemu_token';
        const USER_KEY = CONFIG?.STORAGE_KEYS?.USER || 'eqemu_user';
        const CHARACTERS_KEY = CONFIG?.STORAGE_KEYS?.CHARACTERS || 'eqemu_characters';

        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
        localStorage.removeItem(CHARACTERS_KEY);
        this.token = null;

        // Also clear hardcoded keys as a safety measure
        localStorage.removeItem('eqemu_token');
        localStorage.removeItem('eqemu_user');
        localStorage.removeItem('eqemu_characters');
    }

    // Generic fetch wrapper with error handling and timeout
    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;

        // Create abort controller for timeout
        const controller = new AbortController();
        const timeout = options.timeout || this.defaultTimeout;
        const timeoutId = setTimeout(() => controller.abort(), timeout);

        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
            },
            signal: controller.signal
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
            clearTimeout(timeoutId);

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
            clearTimeout(timeoutId);

            if (error.name === 'AbortError') {
                throw new Error('Request timeout - please try again');
            }

            console.error('API Error:', error);
            throw error;
        }
    }

    // Request with automatic retry logic
    async requestWithRetry(endpoint, options = {}, maxRetries = this.maxRetries) {
        let lastError;

        for (let attempt = 0; attempt < maxRetries; attempt++) {
            try {
                return await this.request(endpoint, options);
            } catch (error) {
                lastError = error;

                // Don't retry on authentication errors or client errors (4xx)
                if (error.message.includes('Unauthorized') || error.message.includes('Bad Request')) {
                    throw error;
                }

                // If this was the last attempt, throw the error
                if (attempt === maxRetries - 1) {
                    throw error;
                }

                // Exponential backoff: 2^attempt seconds
                const delay = Math.pow(2, attempt) * 1000;
                console.log(`Request failed, retrying in ${delay}ms... (attempt ${attempt + 1}/${maxRetries})`);
                await new Promise(resolve => setTimeout(resolve, delay));
            }
        }

        throw lastError;
    }

    // Request deduplication - prevents multiple identical requests
    async requestOnce(endpoint, options = {}) {
        const method = options.method || 'GET';
        const key = `${method}:${endpoint}`;

        // If there's already a pending request with this key, return it
        if (this._pendingRequests.has(key)) {
            return this._pendingRequests.get(key);
        }

        // Create new request
        const promise = this.request(endpoint, options);
        this._pendingRequests.set(key, promise);

        try {
            const result = await promise;
            return result;
        } finally {
            // Clean up after request completes (success or failure)
            this._pendingRequests.delete(key);
        }
    }

    // Build URL with query parameters using modern URL API
    buildURL(path, params = {}) {
        const url = new URL(path, window.location.origin + this.baseURL);

        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                url.searchParams.append(key, value);
            }
        });

        return url.pathname + url.search;
    }

    // Authentication
    async login(username, password) {
        const data = await this.request('/auth/login.php', {
            method: 'POST',
            body: { username, password }
        });

        if (data.token) {
            this.setToken(data.token);
            const USER_KEY = CONFIG?.STORAGE_KEYS?.USER || 'eqemu_user';
            const CHARACTERS_KEY = CONFIG?.STORAGE_KEYS?.CHARACTERS || 'eqemu_characters';
            localStorage.setItem(USER_KEY, JSON.stringify(data.user));
            localStorage.setItem(CHARACTERS_KEY, JSON.stringify(data.characters));
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
        const USER_KEY = CONFIG?.STORAGE_KEYS?.USER || 'eqemu_user';
        const userStr = localStorage.getItem(USER_KEY);
        return userStr ? JSON.parse(userStr) : null;
    }

    // Get user's characters
    getCharacters() {
        const CHARACTERS_KEY = CONFIG?.STORAGE_KEYS?.CHARACTERS || 'eqemu_characters';
        const charsStr = localStorage.getItem(CHARACTERS_KEY);
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
        const params = {};

        if (filters.search) params.search = filters.search;
        if (filters.minPrice) params.min_price = filters.minPrice;
        if (filters.maxPrice) params.max_price = filters.maxPrice;
        if (filters.sortBy) params.sort_by = filters.sortBy;
        if (filters.itemType) params.item_type = filters.itemType;
        if (filters.itemClass) params.item_class = filters.itemClass;

        // Advanced stat filters
        if (filters.minAC) params.min_ac = filters.minAC;
        if (filters.minHP) params.min_hp = filters.minHP;
        if (filters.minMana) params.min_mana = filters.minMana;
        if (filters.minSTR) params.min_str = filters.minSTR;
        if (filters.minDEX) params.min_dex = filters.minDEX;
        if (filters.minSTA) params.min_sta = filters.minSTA;
        if (filters.minAGI) params.min_agi = filters.minAGI;
        if (filters.minINT) params.min_int = filters.minINT;
        if (filters.minWIS) params.min_wis = filters.minWIS;
        if (filters.minCHA) params.min_cha = filters.minCHA;
        if (filters.minFR) params.min_fr = filters.minFR;
        if (filters.minCR) params.min_cr = filters.minCR;
        if (filters.minMR) params.min_mr = filters.minMR;
        if (filters.minPR) params.min_pr = filters.minPR;
        if (filters.minDR) params.min_dr = filters.minDR;

        // Weapon stat filters
        if (filters.minDamage) params.min_damage = filters.minDamage;
        if (filters.maxDelay) params.max_delay = filters.maxDelay;

        // Pagination
        if (filters.page) params.page = filters.page;
        if (filters.limit) params.limit = filters.limit;

        const endpoint = this.buildURL('/listings/list.php', params);
        return await this.requestOnce(endpoint); // Use deduplication for GET requests
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

    const responseText = await response.text();

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

    const responseText = await response.text();

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
        const params = {};

        if (filters.search) params.search = filters.search;
        if (filters.minPrice) params.min_price = filters.minPrice;
        if (filters.maxPrice) params.max_price = filters.maxPrice;
        if (filters.sortBy) params.sort_by = filters.sortBy;
        if (filters.itemType) params.item_type = filters.itemType;
        if (filters.itemClass) params.item_class = filters.itemClass;
        if (filters.itemId) params.item_id = filters.itemId;

        const endpoint = this.buildURL('/wtb/list.php', params);
        return await this.requestOnce(endpoint); // Use deduplication for GET requests
    }

    async getMyWTBListings(characterId, status = 'active') {
        const params = { char_id: characterId };
        if (status) params.status = status;

        const endpoint = this.buildURL('/wtb/my-wtb.php', params);
        return await this.request(endpoint);
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
        const params = {
            char_id: characterId,
            page: page,
            per_page: perPage
        };
        if (unreadOnly) params.unread_only = '1';

        const endpoint = this.buildURL('/notifications/list.php', params);
        return await this.request(endpoint);
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
        const params = {
            search: query,
            limit: limit
        };

        const endpoint = this.buildURL('/items/search.php', params);
        return await this.request(endpoint);
    }
}

// Create a global instance
const api = new MarketplaceAPI();
