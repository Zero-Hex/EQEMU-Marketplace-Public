// Main Application Logic
class MarketplaceApp {
    constructor() {
        this.currentPage = 'marketplace';
        this.currentUser = null;
        this.currentFilters = {};
        this.refreshTimer = null;
        
        this.init();
    }

    init() {
        // Check if user is logged in
        this.currentUser = api.getCurrentUser();

        // Initialize UI
        this.updateUserInterface();
        this.attachEventListeners();

        // Initialize additional event listeners from extensions
        if (typeof this.initAccountsEventListeners === 'function') {
            this.initAccountsEventListeners();
        }

        // Load initial data
        if (this.currentUser) {
            this.loadMarketplace();
            this.startAutoRefresh();
        }
    }

    // Utility function to format numbers with commas
    formatNumber(number, decimals = 2) {
        if (number === null || number === undefined) return '0';
        const num = typeof number === 'string' ? parseFloat(number) : number;
        return num.toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    // Format copper to platinum with commas
    formatPrice(copperAmount, decimals = 2) {
        const platinum = copperAmount / CONFIG.COPPER_TO_PLATINUM;
        return this.formatNumber(platinum, decimals);
    }

    updateUserInterface() {
        const usernameEl = document.getElementById('current-user');
        const loginBtn = document.getElementById('login-btn');
        const logoutBtn = document.getElementById('logout-btn');

        // Update mobile more menu username
        const moreUsernameEl = document.getElementById('more-current-user');
        const moreLoginBtn = document.getElementById('login-btn-more');
        const moreLogoutBtn = document.getElementById('logout-btn-more');

        // Pages that require authentication
        const authRequiredPages = ['my-listings', 'my-purchases', 'my-earnings', 'watchlist', 'my-accounts'];

        if (this.currentUser) {
            usernameEl.textContent = this.currentUser.name;
            loginBtn.classList.add('hidden');
            logoutBtn.classList.remove('hidden');

            // Show all navigation links
            document.querySelectorAll('.nav-link').forEach(link => {
                const page = link.dataset.page;
                if (authRequiredPages.includes(page)) {
                    link.classList.remove('hidden');
                }
            });

            // Show auth-required items in mobile navigation
            document.querySelectorAll('.bottom-nav-item').forEach(item => {
                const page = item.dataset.page;
                if (authRequiredPages.includes(page)) {
                    item.classList.remove('hidden');
                }
            });

            document.querySelectorAll('.more-menu-item').forEach(item => {
                const page = item.dataset.page;
                if (authRequiredPages.includes(page)) {
                    item.classList.remove('hidden');
                }
            });

            // Update mobile more menu elements
            if (moreUsernameEl) moreUsernameEl.textContent = this.currentUser.name;
            if (moreLoginBtn) moreLoginBtn.classList.add('hidden');
            if (moreLogoutBtn) moreLogoutBtn.classList.remove('hidden');
        } else {
            usernameEl.textContent = 'Not Logged In';
            loginBtn.classList.remove('hidden');
            logoutBtn.classList.add('hidden');

            // Hide navigation links that require authentication
            document.querySelectorAll('.nav-link').forEach(link => {
                const page = link.dataset.page;
                if (authRequiredPages.includes(page)) {
                    link.classList.add('hidden');
                }
            });

            // Hide auth-required items in mobile navigation
            document.querySelectorAll('.bottom-nav-item').forEach(item => {
                const page = item.dataset.page;
                if (authRequiredPages.includes(page)) {
                    item.classList.add('hidden');
                }
            });

            document.querySelectorAll('.more-menu-item').forEach(item => {
                const page = item.dataset.page;
                if (authRequiredPages.includes(page)) {
                    item.classList.add('hidden');
                }
            });

            // Update mobile more menu elements
            if (moreUsernameEl) moreUsernameEl.textContent = 'Not Logged In';
            if (moreLoginBtn) moreLoginBtn.classList.remove('hidden');
            if (moreLogoutBtn) moreLogoutBtn.classList.add('hidden');
        }
    }

    attachEventListeners() {
        // Navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = e.target.dataset.page;
                this.navigateToPage(page);
            });
        });

        // Login/Logout
        document.getElementById('login-btn').addEventListener('click', () => {
            this.showLoginModal();
        });

        document.getElementById('logout-btn').addEventListener('click', () => {
            this.logout();
        });

        // Bottom Navigation
        document.querySelectorAll('.bottom-nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const page = e.target.closest('.bottom-nav-item').dataset.page;

                if (page === 'more') {
                    this.toggleMoreMenu();
                } else {
                    this.navigateToPage(page);
                }
            });
        });

        // More Menu
        const moreCloseBtn = document.getElementById('more-close-btn');
        if (moreCloseBtn) {
            moreCloseBtn.addEventListener('click', () => {
                this.closeMoreMenu();
            });
        }

        const moreOverlay = document.getElementById('mobile-more-overlay');
        if (moreOverlay) {
            moreOverlay.addEventListener('click', (e) => {
                if (e.target.id === 'mobile-more-overlay') {
                    this.closeMoreMenu();
                }
            });
        }

        // More Menu Items
        document.querySelectorAll('.more-menu-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const page = e.target.closest('.more-menu-item').dataset.page;
                this.navigateToPage(page);
                this.closeMoreMenu();
            });
        });

        // More Menu Login/Logout
        const moreLoginBtn = document.getElementById('login-btn-more');
        if (moreLoginBtn) {
            moreLoginBtn.addEventListener('click', () => {
                this.showLoginModal();
                this.closeMoreMenu();
            });
        }

        const moreLogoutBtn = document.getElementById('logout-btn-more');
        if (moreLogoutBtn) {
            moreLogoutBtn.addEventListener('click', () => {
                this.logout();
                this.closeMoreMenu();
            });
        }

        // Login form
        document.getElementById('login-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleLogin();
        });

        // Register form
        document.getElementById('register-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleRegister();
        });

        // Switch between login and register modals
        document.getElementById('show-register-link').addEventListener('click', (e) => {
            e.preventDefault();
            this.hideLoginModal();
            this.showRegisterModal();
        });

        document.getElementById('show-login-link').addEventListener('click', (e) => {
            e.preventDefault();
            this.hideRegisterModal();
            this.showLoginModal();
        });

        // Search
        document.getElementById('search-btn').addEventListener('click', () => {
            this.handleSearch();
        });

        document.getElementById('search-input').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.handleSearch();
            }
        });

        // Filters
        document.getElementById('clear-filters').addEventListener('click', () => {
            this.clearFilters();
        });

        document.getElementById('sort-by').addEventListener('change', () => {
            this.handleSearch();
        });

        document.getElementById('item-type').addEventListener('change', () => {
            this.handleSearch();
        });

        document.getElementById('item-class').addEventListener('change', () => {
            this.handleSearch();
        });

        // Claim earnings button
        document.getElementById('claim-earnings-btn').addEventListener('click', () => {
            this.handleClaimEarnings();
        });

        // Modal close buttons
        document.getElementById('close-login').addEventListener('click', () => {
            this.hideLoginModal();
        });

        document.getElementById('close-register').addEventListener('click', () => {
            this.hideRegisterModal();
        });

        document.getElementById('close-item-modal').addEventListener('click', () => {
            this.hideItemModal();
        });

        // Close modals on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            });
        });
    }

    navigateToPage(pageName) {
        // Update active nav link (desktop and bottom nav)
        document.querySelectorAll('.nav-link, .bottom-nav-item').forEach(link => {
            link.classList.remove('active');
            if (link.dataset.page === pageName) {
                link.classList.add('active');
            }
        });

        // Hide all pages
        document.querySelectorAll('.page-content').forEach(page => {
            page.classList.add('hidden');
        });

        // Show selected page
        const pageElement = document.getElementById(`${pageName}-page`);
        if (pageElement) {
            pageElement.classList.remove('hidden');
            this.currentPage = pageName;

            // Load page data
            this.loadPageData(pageName);
        }
    }

    toggleMoreMenu() {
        const overlay = document.getElementById('mobile-more-overlay');
        if (overlay) {
            overlay.classList.toggle('hidden');
        }
    }

    closeMoreMenu() {
        const overlay = document.getElementById('mobile-more-overlay');
        if (overlay) {
            overlay.classList.add('hidden');
        }
    }

    async loadPageData(pageName) {
        // Allow marketplace and want-to-buy pages without login
        const publicPages = ['marketplace', 'want-to-buy'];
        if (!this.currentUser && !publicPages.includes(pageName)) {
            this.showMessage('Please login to access this page', 'warning');
            this.navigateToPage('marketplace');
            return;
        }

        switch (pageName) {
            case 'marketplace':
                await this.loadMarketplace();
                break;
            case 'my-listings':
                await this.loadMyListings();
                break;
            case 'my-purchases':
                await this.loadMyPurchases();
                break;
            case 'my-earnings':
                await this.loadMyEarnings();
                break;
        }
    }

    async loadMarketplace() {
        const container = document.getElementById('listings-container');
        container.innerHTML = '<div class="loading">Loading listings...</div>';

        try {
            const data = await api.getListings(this.currentFilters);
            this.displayListings(data.listings || [], container);
        } catch (error) {
            container.innerHTML = '<div class="no-data">Failed to load listings. Please try again.</div>';
            this.showMessage(error.message, 'error');
        }
    }

    displayListings(listings, container) {
        if (!listings || listings.length === 0) {
            container.innerHTML = '<div class="no-data">No listings found.</div>';
            return;
        }

        container.innerHTML = '';
        
        listings.forEach(listing => {
            const card = this.createListingCard(listing);
            container.appendChild(card);
        });
    }

    createListingCard(listing) {
        const card = document.createElement('div');
        card.className = 'listing-card';
        card.dataset.listingId = listing.id;

        // Format price with commas
        const priceInPP = this.formatPrice(listing.price_copper);

        // Create augments display
        const augmentsHTML = this.createAugmentsDisplay(listing.augments || []);

        // Format date
        const listedDate = new Date(listing.listed_date).toLocaleDateString();

        card.innerHTML = `
            <div class="listing-header">
                <div class="item-icon">
                    ${listing.icon ? `<img src="${CONFIG.ICON_BASE_URL}${listing.icon}.png" alt="${listing.item_name}">` : CONFIG.DEFAULT_ICON}
                </div>
                <div class="listing-info">
                    <div class="item-name">${this.escapeHtml(listing.item_name)}</div>
                    <div class="seller-name">Seller: ${this.escapeHtml(listing.seller_name)}</div>
                </div>
            </div>
            <div class="listing-details">
                <div class="item-quantity">Qty: ${listing.quantity}</div>
                <div class="item-price">
                    ${priceInPP} <span class="currency">pp</span>
                </div>
            </div>
            ${augmentsHTML}
            <div class="listing-footer">
                <div class="listing-date">Listed: ${listedDate}</div>
                <div class="listing-actions">
                    <button class="btn btn-primary btn-small view-item-btn">View</button>
                    ${this.currentUser ? '<button class="btn btn-secondary btn-small buy-item-btn">Buy</button>' : ''}
                </div>
            </div>
        `;

        // Attach event listeners
        card.querySelector('.view-item-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            this.showItemDetails(listing);
        });

        const buyBtn = card.querySelector('.buy-item-btn');
        if (buyBtn) {
            buyBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.showPurchaseConfirm(listing);
            });
        }

        return card;
    }

    createAugmentsDisplay(augments) {
        if (!augments || augments.every(aug => aug === 0 || !aug)) {
            return '';
        }

        const slots = augments.map((aug, index) => {
            const filled = aug && aug !== 0;
            return `<div class="augment-slot ${filled ? 'filled' : ''}" title="${filled ? 'Augment ID: ' + aug : 'Empty slot'}">${filled ? '✦' : '○'}</div>`;
        }).join('');

        return `<div class="augments">${slots}</div>`;
    }


async showItemDetails(listing, isPurchasable = true) {
    const modal = document.getElementById('item-modal');
    const title = document.getElementById('item-modal-title');
    const body = document.getElementById('item-modal-body');

    title.textContent = listing.item_name;
    body.innerHTML = '<div class="loading">Loading item details...</div>';

    modal.classList.remove('hidden');

    try {
        const itemData = await api.getItemDetails(listing.item_id);

        const priceInPP = listing.price_copper ? this.formatPrice(listing.price_copper) : '0.00';
        
        // Build comprehensive stats display
        let statsHTML = '';
        
        // Combat Stats
        if (itemData.ac || itemData.damage || itemData.delay) {
            statsHTML += '<div class="stat-section"><h4>Combat Stats</h4>';
            if (itemData.ac) statsHTML += `<div class="stat-row"><span class="stat-label">AC:</span><span class="stat-value">${itemData.ac}</span></div>`;
            if (itemData.damage && itemData.delay) {
                statsHTML += `<div class="stat-row"><span class="stat-label">Damage:</span><span class="stat-value">${itemData.damage}</span></div>`;
                statsHTML += `<div class="stat-row"><span class="stat-label">Delay:</span><span class="stat-value">${itemData.delay}</span></div>`;
            }
            statsHTML += '</div>';
        }
        
        // Primary Stats
        const primaryStats = [
            { key: 'hp', label: 'HP' },
            { key: 'mana', label: 'Mana' },
            { key: 'endur', label: 'Endurance' }
        ];
        const hasStats = primaryStats.some(stat => itemData[stat.key] && itemData[stat.key] > 0);
        if (hasStats) {
            statsHTML += '<div class="stat-section"><h4>Primary Stats</h4>';
            primaryStats.forEach(stat => {
                if (itemData[stat.key] && itemData[stat.key] > 0) {
                    statsHTML += `<div class="stat-row"><span class="stat-label">${stat.label}:</span><span class="stat-value">+${itemData[stat.key]}</span></div>`;
                }
            });
            statsHTML += '</div>';
        }
        
        // Attributes
        const attributes = [
            { key: 'astr', label: 'STR' },
            { key: 'asta', label: 'STA' },
            { key: 'aagi', label: 'AGI' },
            { key: 'adex', label: 'DEX' },
            { key: 'awis', label: 'WIS' },
            { key: 'aint', label: 'INT' },
            { key: 'acha', label: 'CHA' }
        ];
        const hasAttributes = attributes.some(attr => itemData[attr.key] && itemData[attr.key] > 0);
        if (hasAttributes) {
            statsHTML += '<div class="stat-section"><h4>Attributes</h4>';
            attributes.forEach(attr => {
                if (itemData[attr.key] && itemData[attr.key] > 0) {
                    statsHTML += `<div class="stat-row"><span class="stat-label">${attr.label}:</span><span class="stat-value">+${itemData[attr.key]}</span></div>`;
                }
            });
            statsHTML += '</div>';
        }
        
        // Resistances
        const resistances = [
            { key: 'cr', label: 'Cold' },
            { key: 'dr', label: 'Disease' },
            { key: 'fr', label: 'Fire' },
            { key: 'mr', label: 'Magic' },
            { key: 'pr', label: 'Poison' }
        ];
        const hasResists = resistances.some(resist => itemData[resist.key] && itemData[resist.key] > 0);
        if (hasResists) {
            statsHTML += '<div class="stat-section"><h4>Resistances</h4>';
            resistances.forEach(resist => {
                if (itemData[resist.key] && itemData[resist.key] > 0) {
                    statsHTML += `<div class="stat-row"><span class="stat-label">${resist.label}:</span><span class="stat-value">+${itemData[resist.key]}</span></div>`;
                }
            });
            statsHTML += '</div>';
        }
        
        body.innerHTML = `
            <div class="item-details-container">
                ${listing.icon ? `
                <div class="item-icon-large">
                    <img src="${CONFIG.ICON_BASE_URL}${listing.icon}.png" alt="${listing.item_name}">
                </div>
                ` : ''}
                
                <div class="item-stats">
                    <div class="stat-section">
                        <h4>${isPurchasable ? 'Listing Information' : 'Item Information'}</h4>
                        <div class="stat-row">
                            <span class="stat-label">Item ID:</span>
                            <span class="stat-value">${listing.item_id}</span>
                        </div>
                        ${listing.quantity ? `
                        <div class="stat-row">
                            <span class="stat-label">Quantity:</span>
                            <span class="stat-value">${listing.quantity}</span>
                        </div>
                        ` : ''}
                        ${isPurchasable && listing.price_copper ? `
                        <div class="stat-row">
                            <span class="stat-label">Price:</span>
                            <span class="stat-value">${priceInPP} pp</span>
                        </div>
                        ` : ''}
                        ${listing.seller_name ? `
                        <div class="stat-row">
                            <span class="stat-label">Seller:</span>
                            <span class="stat-value">${this.escapeHtml(listing.seller_name)}</span>
                        </div>
                        ` : ''}
                        ${listing.charges ? `
                        <div class="stat-row">
                            <span class="stat-label">Charges:</span>
                            <span class="stat-value">${listing.charges}</span>
                        </div>
                        ` : ''}
                    </div>
                    
                    ${statsHTML}
                    
                    ${itemData.weight || itemData.size || itemData.itemtype ? `
                    <div class="stat-section">
                        <h4>Item Properties</h4>
                        ${itemData.weight ? `<div class="stat-row"><span class="stat-label">Weight:</span><span class="stat-value">${itemData.weight}</span></div>` : ''}
                        ${itemData.size ? `<div class="stat-row"><span class="stat-label">Size:</span><span class="stat-value">${itemData.size}</span></div>` : ''}
                        ${itemData.itemtype ? `<div class="stat-row"><span class="stat-label">Type:</span><span class="stat-value">${itemData.itemtype}</span></div>` : ''}
                    </div>
                    ` : ''}
                    
                    ${itemData.description ? `
                    <div class="stat-section">
                        <h4>Description</h4>
                        <p class="item-description">${this.escapeHtml(itemData.description)}</p>
                    </div>
                    ` : ''}
                </div>
                
                <div class="item-actions-bar">
                    <a href="http://65.49.60.92:8000/items/${listing.item_id}"
                       target="_blank"
                       class="btn btn-secondary">
                        🔍 View in Item Database
                    </a>
                    ${this.currentUser && isPurchasable ? `
                    <button class="btn btn-primary" onclick="app.showPurchaseConfirm(${JSON.stringify(listing).replace(/"/g, '&quot;')})">
                        💰 Purchase Item
                    </button>
                    ` : ''}
                </div>
            </div>
        `;
    } catch (error) {
        body.innerHTML = '<div class="no-data">Failed to load item details.</div>';
    }
}

    showPurchaseConfirm(listing) {
        const modal = document.getElementById('item-modal');
        const title = document.getElementById('item-modal-title');
        const body = document.getElementById('item-modal-body');

        title.textContent = 'Confirm Purchase';

        const characters = api.getCharacters();
        const priceInPP = this.formatPrice(listing.price_copper);

        body.innerHTML = `
            <div class="confirm-purchase">
                <p class="warning">⚠️ This will deduct ${priceInPP} platinum from your selected character's currency.</p>
                <p>Item: <strong>${this.escapeHtml(listing.item_name)}</strong></p>
                <p>Quantity: <strong>${listing.quantity}</strong></p>
                <p>The item will be sent via the parcel system and can be retrieved from any "Parcels and General Supplies" merchant in-game.</p>
                
                <div class="form-group">
                    <label for="purchase-character">Select Character:</label>
                    <select id="purchase-character" class="form-control">
                        <option value="">Choose character...</option>
                        ${characters.map(char => `<option value="${char.id}">${this.escapeHtml(char.name)}${char.account_name ? ' (' + this.escapeHtml(char.account_name) + ')' : ''}</option>`).join('')}
                    </select>
                </div>

                <div class="confirm-actions">
                    <button class="btn btn-primary" onclick="app.completePurchase(${listing.id})">Confirm Purchase</button>
                    <button class="btn btn-secondary" onclick="app.hideItemModal()">Cancel</button>
                </div>
            </div>
        `;

        modal.classList.remove('hidden');
    }

    async completePurchase(listingId) {
        const characterSelect = document.getElementById('purchase-character');
        const characterId = characterSelect.value;

        if (!characterId) {
            this.showMessage('Please select a character', 'warning');
            return;
        }

        try {
            const result = await api.purchaseItem(listingId, characterId);
            
            this.hideItemModal();
            this.showMessage(result.message || 'Purchase successful! Check your parcels in-game.', 'success');
            
            // Refresh the marketplace
            this.loadMarketplace();
        } catch (error) {
            this.showMessage(error.message, 'error');
        }
    }

    async loadMyListings() {
        const container = document.getElementById('my-listings-container');
        container.innerHTML = '<div class="loading">Loading your listings...</div>';

        try {
            const data = await api.getMyListings();
            
            if (!data.listings || data.listings.length === 0) {
                container.innerHTML = '<div class="no-data">You have no active listings.</div>';
                return;
            }

            container.innerHTML = '';
            
            data.listings.forEach(listing => {
                const card = this.createMyListingCard(listing);
                container.appendChild(card);
            });
        } catch (error) {
            container.innerHTML = '<div class="no-data">Failed to load your listings.</div>';
            this.showMessage(error.message, 'error');
        }
    }

    createMyListingCard(listing) {
        const card = this.createListingCard(listing);
        
        // Add cancel button
        const actions = card.querySelector('.listing-actions');
        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'btn btn-danger btn-small';
        cancelBtn.textContent = 'Cancel';
        cancelBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.cancelListing(listing.id);
        });
        actions.appendChild(cancelBtn);

        return card;
    }

    async cancelListing(listingId) {
        if (!confirm('Are you sure you want to cancel this listing? The item will be returned to your inventory.')) {
            return;
        }

        try {
            const result = await api.cancelListing(listingId);
            this.showMessage(result.message || 'Listing cancelled successfully', 'success');
            this.loadMyListings();
        } catch (error) {
            this.showMessage(error.message, 'error');
        }
    }

    async loadMyPurchases() {
        const container = document.getElementById('purchases-container');
        container.innerHTML = '<div class="loading">Loading purchase history...</div>';

        try {
            const data = await api.getMyPurchases();
            
            if (!data.purchases || data.purchases.length === 0) {
                container.innerHTML = '<div class="no-data">You have not made any purchases yet.</div>';
                return;
            }

            container.innerHTML = '';
            
            data.purchases.forEach(purchase => {
                const item = this.createPurchaseItem(purchase);
                container.appendChild(item);
            });
        } catch (error) {
            container.innerHTML = '<div class="no-data">Failed to load purchase history.</div>';
            this.showMessage(error.message, 'error');
        }
    }

    createPurchaseItem(purchase) {
        const item = document.createElement('div');
        item.className = 'purchase-item';

        const priceInPP = this.formatPrice(purchase.price_copper);
        const purchaseDate = new Date(purchase.transaction_date).toLocaleString();

        item.innerHTML = `
            <div class="purchase-info">
                <div class="item-icon">
                    ${purchase.icon ? `<img src="${CONFIG.ICON_BASE_URL}${purchase.icon}.png" alt="${purchase.item_name}">` : CONFIG.DEFAULT_ICON}
                </div>
                <div class="purchase-details">
                    <h3>${this.escapeHtml(purchase.item_name)}</h3>
                    <div class="purchase-meta">
                        Purchased: ${purchaseDate}<br>
                        Seller: ${this.escapeHtml(purchase.seller_name)}<br>
                        Buyer: ${this.escapeHtml(purchase.buyer_name)}
                    </div>
                </div>
            </div>
            <div class="purchase-price">${priceInPP} pp</div>
        `;

        return item;
    }

    async loadMyEarnings() {
        const container = document.getElementById('earnings-container');
        const totalElement = document.getElementById('total-earnings-pp');
        const claimBtn = document.getElementById('claim-earnings-btn');

        container.innerHTML = '<div class="loading">Loading earnings...</div>';

        try {
            const data = await api.getEarnings();

            // Update total earnings display
            totalElement.textContent = data.total_unclaimed_platinum.toLocaleString();

            // Enable/disable claim button
            if (data.total_unclaimed_platinum > 0) {
                claimBtn.disabled = false;
            } else {
                claimBtn.disabled = true;
            }

            // Display earnings list
            if (!data.earnings || data.earnings.length === 0) {
                container.innerHTML = '<div class="no-data">You have no pending earnings.</div>';
                return;
            }

            // Group earnings by character
            const earningsByChar = {};
            data.earnings.forEach(earning => {
                const charId = earning.character_id;
                if (!earningsByChar[charId]) {
                    earningsByChar[charId] = {
                        character_name: earning.character_name,
                        character_id: charId,
                        total_platinum: 0,
                        earnings: []
                    };
                }
                earningsByChar[charId].total_platinum += earning.amount_platinum;
                earningsByChar[charId].earnings.push(earning);
            });

            container.innerHTML = '';

            // Display character sections
            Object.values(earningsByChar).forEach(charData => {
                const charSection = this.createCharacterEarningsSection(charData);
                container.appendChild(charSection);
            });
        } catch (error) {
            container.innerHTML = '<div class="no-data">Failed to load earnings.</div>';
            this.showMessage(error.message, 'error');
        }
    }

    createCharacterEarningsSection(charData) {
        const section = document.createElement('div');
        section.className = 'character-earnings-section';
        section.style.cssText = `
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        `;

        const header = document.createElement('div');
        header.style.cssText = `
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        `;

        header.innerHTML = `
            <div>
                <h3 style="margin: 0; color: var(--primary-color);">${this.escapeHtml(charData.character_name)}</h3>
                <p style="margin: 0.5rem 0 0 0; color: var(--text-secondary);">Total: ${charData.total_platinum.toLocaleString()} pp</p>
            </div>
            <button class="btn btn-primary claim-character-btn" data-character-id="${charData.character_id}">
                Claim ${charData.total_platinum.toLocaleString()} pp
            </button>
        `;

        section.appendChild(header);

        // Add earnings list for this character
        const earningsList = document.createElement('div');
        charData.earnings.forEach(earning => {
            const item = this.createEarningItem(earning);
            earningsList.appendChild(item);
        });
        section.appendChild(earningsList);

        // Add click handler to claim button
        const claimBtn = header.querySelector('.claim-character-btn');
        claimBtn.addEventListener('click', () => {
            this.handleClaimCharacterEarnings(charData.character_id);
        });

        return section;
    }

    createEarningItem(earning) {
        const item = document.createElement('div');
        item.className = 'earning-item';

        const earnedDate = new Date(earning.earned_date).toLocaleString();

        item.innerHTML = `
            <div class="earning-info">
                <div class="earning-details">
                    <h3>${this.escapeHtml(earning.item_name || 'Item Sold')}</h3>
                    <div class="earning-meta">
                        Earned: ${earnedDate}<br>
                        Character: ${this.escapeHtml(earning.character_name)}<br>
                        ${earning.buyer_name ? `Buyer: ${this.escapeHtml(earning.buyer_name)}<br>` : ''}
                        ${earning.quantity ? `Quantity: ${earning.quantity}` : ''}
                    </div>
                </div>
            </div>
            <div class="earning-amount">${earning.amount_platinum.toLocaleString()} pp</div>
        `;

        return item;
    }

    async handleClaimEarnings() {
        const claimBtn = document.getElementById('claim-earnings-btn');

        // Disable button to prevent double-clicks
        claimBtn.disabled = true;
        claimBtn.textContent = 'Claiming...';

        try {
            const result = await api.claimEarnings();
            this.showMessage(result.message || 'Earnings claimed successfully!', 'success');

            // Reload earnings page to update the display
            await this.loadMyEarnings();
        } catch (error) {
            this.showMessage(error.message, 'error');
            claimBtn.disabled = false;
            claimBtn.textContent = 'Claim All Earnings';
        }
    }

    async handleClaimCharacterEarnings(characterId) {
        try {
            const result = await api.claimCharacterEarnings(characterId);
            this.showMessage(result.message || 'Earnings claimed successfully!', 'success');

            // Reload earnings page to update the display
            await this.loadMyEarnings();
        } catch (error) {
            this.showMessage(error.message, 'error');
        }
    }

    handleSearch() {
        const search = document.getElementById('search-input').value;
        const minPrice = document.getElementById('price-min').value;
        const maxPrice = document.getElementById('price-max').value;
        const sortBy = document.getElementById('sort-by').value;
        const itemType = document.getElementById('item-type').value;
        const itemClass = document.getElementById('item-class').value;

        this.currentFilters = {
            search: search || null,
            minPrice: minPrice ? parseInt(minPrice) * CONFIG.COPPER_TO_PLATINUM : null,
            maxPrice: maxPrice ? parseInt(maxPrice) * CONFIG.COPPER_TO_PLATINUM : null,
            sortBy: sortBy || null,
            itemType: itemType || null,
            itemClass: itemClass || null
        };

        this.loadMarketplace();
    }

    clearFilters() {
        document.getElementById('search-input').value = '';
        document.getElementById('price-min').value = '';
        document.getElementById('price-max').value = '';
        document.getElementById('sort-by').value = 'newest';
        document.getElementById('item-type').value = '';
        document.getElementById('item-class').value = '';

        this.currentFilters = {};
        this.loadMarketplace();
    }

    showLoginModal() {
        document.getElementById('login-modal').classList.remove('hidden');
    }

    hideLoginModal() {
        document.getElementById('login-modal').classList.add('hidden');
    }

    showRegisterModal() {
        document.getElementById('register-modal').classList.remove('hidden');
    }

    hideRegisterModal() {
        document.getElementById('register-modal').classList.add('hidden');
    }

    hideItemModal() {
        document.getElementById('item-modal').classList.add('hidden');
    }

    async handleLogin() {
        const username = document.getElementById('login-username').value;
        const password = document.getElementById('login-password').value;

        if (!username || !password) {
            this.showMessage('Please enter username and password', 'warning');
            return;
        }

        try {
            const result = await api.login(username, password);

            this.currentUser = result.user;
            this.updateUserInterface();
            this.hideLoginModal();
            this.showMessage('Login successful!', 'success');

            // Reload current page
            this.loadPageData(this.currentPage);
            this.startAutoRefresh();
        } catch (error) {
            this.showMessage(error.message, 'error');
        }
    }

    async handleRegister() {
        const accountName = document.getElementById('register-account-name').value.trim();
        const characterId = parseInt(document.getElementById('register-character-id').value);
        const password = document.getElementById('register-password').value;
        const confirmPassword = document.getElementById('register-confirm-password').value;
        const email = document.getElementById('register-email').value.trim() || null;

        // Validation
        if (!accountName || !characterId || !password || !confirmPassword) {
            this.showMessage('Please fill in all required fields', 'warning');
            return;
        }

        if (password !== confirmPassword) {
            this.showMessage('Passwords do not match', 'error');
            return;
        }

        if (password.length < 6) {
            this.showMessage('Password must be at least 6 characters long', 'warning');
            return;
        }

        try {
            const result = await api.register(accountName, characterId, password, confirmPassword, email);

            this.hideRegisterModal();
            this.showMessage(result.message || 'Registration successful! You can now log in.', 'success');

            // Clear the registration form
            document.getElementById('register-form').reset();

            // Show login modal after a short delay
            setTimeout(() => {
                this.showLoginModal();
            }, 1500);
        } catch (error) {
            this.showMessage(error.message, 'error');
        }
    }

    logout() {
        api.logout();
        this.currentUser = null;
        this.updateUserInterface();
        this.stopAutoRefresh();
        this.navigateToPage('marketplace');
        this.showMessage('Logged out successfully', 'success');
    }

    startAutoRefresh() {
        this.stopAutoRefresh();
        this.refreshTimer = setInterval(() => {
            if (this.currentPage === 'marketplace') {
                this.loadMarketplace();
            }
        }, CONFIG.REFRESH_INTERVAL);
    }

    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }

    showMessage(message, type = 'info') {
        // Create a simple toast notification
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 1rem 1.5rem;
            background: ${type === 'error' ? 'var(--danger-color)' : type === 'warning' ? 'var(--warning-color)' : type === 'success' ? 'var(--success-color)' : 'var(--primary-color)'};
            color: white;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 9999;
            animation: slideIn 0.3s ease;
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new MarketplaceApp();
});

// Add CSS animations for toasts
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
