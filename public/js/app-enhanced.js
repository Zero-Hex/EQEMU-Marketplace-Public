/**
 * Enhanced features for Marketplace App
 * This file extends the MarketplaceApp with WTB, Watchlist, and Notifications functionality
 */

// Extend the existing MarketplaceApp class
if (typeof MarketplaceApp !== 'undefined') {

    // Store original init method
    const originalInit = MarketplaceApp.prototype.init;
    const originalAttachEventListeners = MarketplaceApp.prototype.attachEventListeners;
    const originalNavigateToPage = MarketplaceApp.prototype.navigateToPage;
    const originalUpdateUserInterface = MarketplaceApp.prototype.updateUserInterface;

    // Enhance init method
    MarketplaceApp.prototype.init = function() {
        // Call original init
        originalInit.call(this);

        // Initialize new features
        this.notificationTimer = null;
        this.notificationsPage = 1;
        this.notificationsPagination = null;
        this.allNotifications = [];
        this.attachEnhancedEventListeners();

        // Initialize pagination, view toggle, and quick view
        this.initPagination();
        this.initViewToggle();
        this.initQuickViewModal();

        if (this.currentUser) {
            this.loadNotifications();
            this.startNotificationPolling();
        }
    };

    // Enhance updateUserInterface
    MarketplaceApp.prototype.updateUserInterface = function() {
        // Call original
        originalUpdateUserInterface.call(this);

        // Show/hide notifications button based on login status
        const notificationBtn = document.getElementById('notifications-btn');
        const notificationBtnMore = document.getElementById('notifications-btn-more');

        if (notificationBtn) {
            if (this.currentUser) {
                notificationBtn.classList.remove('hidden');
            } else {
                notificationBtn.classList.add('hidden');
            }
        }

        // Also update more menu notifications button
        if (notificationBtnMore) {
            if (this.currentUser) {
                notificationBtnMore.classList.remove('hidden');
            } else {
                notificationBtnMore.classList.add('hidden');
            }
        }
    };

    // Enhance attachEventListeners with new feature listeners
    MarketplaceApp.prototype.attachEnhancedEventListeners = function() {
        // Search Section Toggle
        const toggleSearchBtn = document.getElementById('toggle-search-section');
        if (toggleSearchBtn) {
            toggleSearchBtn.addEventListener('click', () => {
                this.toggleSearchSection();
            });
        }

        // Advanced Filters Toggle
        const toggleAdvancedBtn = document.getElementById('toggle-advanced-filters');
        if (toggleAdvancedBtn) {
            toggleAdvancedBtn.addEventListener('click', () => {
                this.toggleAdvancedFilters();
            });
        }

        // Notifications
        const notifBtn = document.getElementById('notifications-btn');
        if (notifBtn) {
            notifBtn.addEventListener('click', () => {
                this.showNotificationsModal();
            });
        }

        // More menu notifications button
        const notifBtnMore = document.getElementById('notifications-btn-more');
        if (notifBtnMore) {
            notifBtnMore.addEventListener('click', () => {
                this.showNotificationsModal();
                // Close more menu when opening notifications
                if (typeof this.closeMoreMenu === 'function') {
                    this.closeMoreMenu();
                }
            });
        }

        const closeNotif = document.getElementById('close-notifications');
        if (closeNotif) {
            closeNotif.addEventListener('click', () => {
                this.hideNotificationsModal();
            });
        }

        const markAllRead = document.getElementById('mark-all-read');
        if (markAllRead) {
            markAllRead.addEventListener('click', () => {
                this.markAllNotificationsRead();
            });
        }

        const clearAllNotifications = document.getElementById('clear-all-notifications');
        if (clearAllNotifications) {
            clearAllNotifications.addEventListener('click', () => {
                this.clearAllNotifications();
            });
        }

        const loadMoreNotifications = document.getElementById('load-more-notifications');
        if (loadMoreNotifications) {
            loadMoreNotifications.addEventListener('click', () => {
                this.loadMoreNotifications();
            });
        }

        // WTB Tab Switching
        const wtbTabs = document.querySelectorAll('.wtb-tabs .tab-btn');
        wtbTabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                const tabName = e.target.dataset.tab;
                this.switchWTBTab(tabName);
            });
        });

        // Create WTB Modal
        const createWTBBtn = document.getElementById('create-wtb-btn');
        if (createWTBBtn) {
            createWTBBtn.addEventListener('click', () => {
                this.showCreateWTBModal();
            });
        }

        const closeCreateWTB = document.getElementById('close-create-wtb');
        if (closeCreateWTB) {
            closeCreateWTB.addEventListener('click', () => {
                this.hideCreateWTBModal();
            });
        }

        const cancelWTB = document.getElementById('cancel-wtb');
        if (cancelWTB) {
            cancelWTB.addEventListener('click', () => {
                this.hideCreateWTBModal();
            });
        }

        const createWTBForm = document.getElementById('create-wtb-form');
        if (createWTBForm) {
            createWTBForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleCreateWTB();
            });
        }

        // Item search autocomplete for WTB
        const wtbItemSearch = document.getElementById('wtb-item-search');
        if (wtbItemSearch) {
            wtbItemSearch.addEventListener('input', (e) => {
                this.handleItemSearch(e.target.value, 'wtb');
            });
        }

        // Add to Watchlist Modal
        const addWatchlistBtn = document.getElementById('add-to-watchlist-btn');
        if (addWatchlistBtn) {
            addWatchlistBtn.addEventListener('click', () => {
                this.showAddWatchlistModal();
            });
        }

        const closeAddWatchlist = document.getElementById('close-add-watchlist');
        if (closeAddWatchlist) {
            closeAddWatchlist.addEventListener('click', () => {
                this.hideAddWatchlistModal();
            });
        }

        const cancelWatchlist = document.getElementById('cancel-watchlist');
        if (cancelWatchlist) {
            cancelWatchlist.addEventListener('click', () => {
                this.hideAddWatchlistModal();
            });
        }

        const addWatchlistForm = document.getElementById('add-watchlist-form');
        if (addWatchlistForm) {
            addWatchlistForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleAddToWatchlist();
            });
        }

        // Item search autocomplete for Watchlist
        const watchlistItemSearch = document.getElementById('watchlist-item-search');
        if (watchlistItemSearch) {
            watchlistItemSearch.addEventListener('input', (e) => {
                this.handleItemSearch(e.target.value, 'watchlist');
            });
        }

        // Modal close on outside click
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.classList.add('hidden');
            }
        });
    };

    // Enhance navigateToPage to handle new pages
    MarketplaceApp.prototype.navigateToPage = function(pageName) {
        // Call original for standard pages
        if (['marketplace', 'my-listings', 'my-purchases', 'my-earnings', 'admin'].includes(pageName)) {
            originalNavigateToPage.call(this, pageName);
        }

        // Handle new pages
        if (pageName === 'want-to-buy') {
            this.loadWTBPage();
        } else if (pageName === 'watchlist') {
            this.loadWatchlistPage();
        } else if (pageName === 'my-accounts') {
            this.loadMyAccountsPage();
        }

        // Update navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
            if (link.dataset.page === pageName) {
                link.classList.add('active');
            }
        });

        // Show/hide pages
        document.querySelectorAll('.page-content').forEach(page => {
            page.classList.add('hidden');
        });

        const pageMap = {
            'marketplace': 'marketplace-page',
            'want-to-buy': 'want-to-buy-page',
            'my-listings': 'my-listings-page',
            'my-purchases': 'my-purchases-page',
            'my-earnings': 'my-earnings-page',
            'watchlist': 'watchlist-page',
            'my-accounts': 'my-accounts-page',
            'admin': 'admin-page'
        };

        const pageId = pageMap[pageName];
        if (pageId) {
            const pageEl = document.getElementById(pageId);
            if (pageEl) {
                pageEl.classList.remove('hidden');
            }
        }

        this.currentPage = pageName;
    };

    // ===================================================================
    // Search Section Toggle
    // ===================================================================

    MarketplaceApp.prototype.toggleSearchSection = function() {
        const searchContent = document.getElementById('search-section-content');
        const toggleBtn = document.getElementById('toggle-search-section');

        if (searchContent && toggleBtn) {
            searchContent.classList.toggle('hidden');

            const toggleIcon = toggleBtn.querySelector('.toggle-icon');
            if (searchContent.classList.contains('hidden')) {
                toggleIcon.textContent = '▼';
                toggleBtn.innerHTML = '<span class="toggle-icon">▼</span> Show Search & Filters';
            } else {
                toggleIcon.textContent = '▲';
                toggleBtn.innerHTML = '<span class="toggle-icon">▲</span> Hide Search & Filters';
            }
        }
    };

    // ===================================================================
    // Advanced Filters
    // ===================================================================

    MarketplaceApp.prototype.toggleAdvancedFilters = function() {
        const advancedFilters = document.getElementById('advanced-filters');
        if (advancedFilters) {
            advancedFilters.classList.toggle('hidden');

            const toggleBtn = document.getElementById('toggle-advanced-filters');
            if (advancedFilters.classList.contains('hidden')) {
                toggleBtn.textContent = 'Advanced Filters';
            } else {
                toggleBtn.textContent = 'Hide Advanced';
            }
        }
    };

    MarketplaceApp.prototype.getAdvancedFilters = function() {
        const filters = {};

        const fields = {
            'min-ac': 'minAC',
            'min-hp': 'minHP',
            'min-mana': 'minMana',
            'min-damage': 'minDamage',
            'max-delay': 'maxDelay',
            'min-str': 'minSTR',
            'min-dex': 'minDEX',
            'min-sta': 'minSTA',
            'min-agi': 'minAGI',
            'min-int': 'minINT',
            'min-wis': 'minWIS',
            'min-cha': 'minCHA',
            'min-fr': 'minFR',
            'min-cr': 'minCR',
            'min-mr': 'minMR',
            'min-pr': 'minPR',
            'min-dr': 'minDR'
        };

        for (const [fieldId, filterKey] of Object.entries(fields)) {
            const el = document.getElementById(fieldId);
            if (el && el.value) {
                filters[filterKey] = parseInt(el.value);
            }
        }

        return filters;
    };

    // Override handleSearch to include advanced filters
    const originalHandleSearch = MarketplaceApp.prototype.handleSearch;
    MarketplaceApp.prototype.handleSearch = function() {
        if (originalHandleSearch) {
            originalHandleSearch.call(this);
        }

        // Add advanced filters to current filters
        const advancedFilters = this.getAdvancedFilters();
        this.currentFilters = { ...this.currentFilters, ...advancedFilters };

        // Reload with new filters
        if (this.currentPage === 'marketplace') {
            this.loadMarketplace();
        }
    };

    // Override clearFilters to include advanced filters
    const originalClearFilters = MarketplaceApp.prototype.clearFilters;
    MarketplaceApp.prototype.clearFilters = function() {
        if (originalClearFilters) {
            originalClearFilters.call(this);
        }

        // Clear advanced filter inputs
        const fields = ['min-ac', 'min-hp', 'min-mana', 'min-damage', 'max-delay',
                       'min-str', 'min-dex', 'min-sta', 'min-agi', 'min-int', 'min-wis',
                       'min-cha', 'min-fr', 'min-cr', 'min-mr', 'min-pr', 'min-dr'];

        fields.forEach(fieldId => {
            const el = document.getElementById(fieldId);
            if (el) el.value = '';
        });
    };

    // ===================================================================
    // Notifications
    // ===================================================================

    MarketplaceApp.prototype.loadNotifications = async function() {
        try {
            const characters = api.getCharacters();
            if (!characters || characters.length === 0) return;

            const charId = characters[0].id;
            const data = await api.getNotifications(charId, false);

            if (data.success) {
                this.updateNotificationBadge(data.unread_count);
            }
        } catch (error) {
            console.error('Failed to load notifications:', error);
        }
    };

    MarketplaceApp.prototype.updateNotificationBadge = function(count) {
        const badge = document.getElementById('notification-count');
        const moreBadge = document.getElementById('notification-count-more');

        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        // Also update more menu notification badge
        if (moreBadge) {
            if (count > 0) {
                moreBadge.textContent = count > 99 ? '99+' : count;
                moreBadge.classList.remove('hidden');
            } else {
                moreBadge.classList.add('hidden');
            }
        }
    };

    MarketplaceApp.prototype.startNotificationPolling = function() {
        // Poll every 30 seconds
        this.notificationTimer = setInterval(() => {
            this.loadNotifications();
        }, 30000);
    };

    MarketplaceApp.prototype.showNotificationsModal = async function() {
        const modal = document.getElementById('notifications-modal');
        if (!modal) return;

        modal.classList.remove('hidden');

        // Reset pagination
        this.notificationsPage = 1;
        this.allNotifications = [];

        try {
            const characters = api.getCharacters();
            if (!characters || characters.length === 0) return;

            const charId = characters[0].id;
            const data = await api.getNotifications(charId, false, this.notificationsPage);

            if (data.success) {
                this.allNotifications = data.notifications;
                this.notificationsPagination = data.pagination;
                this.displayNotifications(this.allNotifications);
                this.updateNotificationBadge(data.unread_count);
                this.updateNotificationsPaginationUI();
            }
        } catch (error) {
            console.error('Failed to load notifications:', error);
            this.showError('Failed to load notifications');
        }
    };

    MarketplaceApp.prototype.hideNotificationsModal = function() {
        const modal = document.getElementById('notifications-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    };

    MarketplaceApp.prototype.displayNotifications = function(notifications) {
        const container = document.getElementById('notifications-list');
        if (!container) return;

        if (!notifications || notifications.length === 0) {
            container.innerHTML = '<div class="no-data">No notifications</div>';
            return;
        }

        const html = notifications.map(notif => {
            const readClass = notif.is_read ? 'read' : 'unread';
            const typeIcon = this.getNotificationIcon(notif.notification_type);
            const timeAgo = this.formatTimeAgo(notif.created_date);

            return `
                <div class="notification-item ${readClass}" data-id="${notif.id}">
                    <span class="notif-icon">${typeIcon}</span>
                    <div class="notif-content">
                        <p class="notif-message">${notif.message}</p>
                        <span class="notif-time">${timeAgo}</span>
                    </div>
                    ${!notif.is_read ? '<button class="btn-mark-read btn-sm" data-notif-id="' + notif.id + '">Mark Read</button>' : ''}
                </div>
            `;
        }).join('');

        container.innerHTML = html;

        // Attach event listeners to all mark-read buttons
        container.querySelectorAll('.btn-mark-read').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const notifId = parseInt(e.target.dataset.notifId);
                this.markNotificationRead(notifId);
            });
        });
    };

    MarketplaceApp.prototype.getNotificationIcon = function(type) {
        const icons = {
            'watchlist_match': '👀',
            'wtb_fulfilled': '✅',
            'item_sold': '💰',
            'listing_expired': '⏰',
            'wtb_match': '🔍'
        };
        return icons[type] || '📢';
    };

    MarketplaceApp.prototype.formatTimeAgo = function(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);

        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + ' min ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
        if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';
        return date.toLocaleDateString();
    };

    MarketplaceApp.prototype.markNotificationRead = async function(notificationId) {
        try {
            const characters = api.getCharacters();
            if (!characters || characters.length === 0) return;

            const charId = characters[0].id;
            await api.markNotificationRead(charId, notificationId);

            // Reload notifications
            this.showNotificationsModal();
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    };

    MarketplaceApp.prototype.markAllNotificationsRead = async function() {
        try {
            const characters = api.getCharacters();
            if (!characters || characters.length === 0) return;

            const charId = characters[0].id;
            await api.markAllNotificationsRead(charId);

            // Reload notifications
            this.showNotificationsModal();
            this.updateNotificationBadge(0);
        } catch (error) {
            console.error('Failed to mark all notifications as read:', error);
        }
    };

    MarketplaceApp.prototype.clearAllNotifications = async function() {
        try {
            // Ask for confirmation before deleting all notifications
            if (!confirm('Are you sure you want to permanently delete all notifications? This cannot be undone.')) {
                return;
            }

            const characters = api.getCharacters();
            if (!characters || characters.length === 0) return;

            const charId = characters[0].id;
            await api.deleteAllNotifications(charId);

            // Reload notifications
            this.showNotificationsModal();
            this.updateNotificationBadge(0);
        } catch (error) {
            console.error('Failed to clear all notifications:', error);
            alert('Failed to clear notifications. Please try again.');
        }
    };

    MarketplaceApp.prototype.loadMoreNotifications = async function() {
        try {
            const characters = api.getCharacters();
            if (!characters || characters.length === 0) return;

            // Increment page
            this.notificationsPage++;

            const charId = characters[0].id;
            const data = await api.getNotifications(charId, false, this.notificationsPage);

            if (data.success && data.notifications.length > 0) {
                // Append new notifications
                this.allNotifications = [...this.allNotifications, ...data.notifications];
                this.notificationsPagination = data.pagination;
                this.displayNotifications(this.allNotifications);
                this.updateNotificationsPaginationUI();
            }
        } catch (error) {
            console.error('Failed to load more notifications:', error);
            this.showError('Failed to load more notifications');
        }
    };

    MarketplaceApp.prototype.updateNotificationsPaginationUI = function() {
        const paginationContainer = document.getElementById('notifications-pagination');
        const paginationInfo = document.getElementById('pagination-info');
        const loadMoreBtn = document.getElementById('load-more-notifications');

        if (!paginationContainer || !this.notificationsPagination) return;

        const { page, total, total_pages, has_more } = this.notificationsPagination;

        // Show pagination container
        paginationContainer.style.display = 'block';

        // Update info text
        const showing = this.allNotifications.length;
        paginationInfo.textContent = `Showing ${showing} of ${total} notifications`;

        // Show/hide load more button
        if (loadMoreBtn) {
            if (has_more) {
                loadMoreBtn.style.display = 'inline-block';
            } else {
                loadMoreBtn.style.display = 'none';
            }
        }

        // Hide pagination if there's only one page
        if (total_pages <= 1) {
            paginationContainer.style.display = 'none';
        }
    };

    // ===================================================================
    // WTB (Want to Buy) System
    // ===================================================================

    MarketplaceApp.prototype.loadWTBPage = async function() {
        // Show/hide create WTB button based on login status
        const createWTBBtn = document.getElementById('create-wtb-btn');
        if (createWTBBtn) {
            if (this.currentUser) {
                createWTBBtn.classList.remove('hidden');
            } else {
                createWTBBtn.classList.add('hidden');
            }
        }

        // Show/hide My WTB tab based on login status
        const myWTBTab = document.querySelector('.tab-btn[data-tab="my-wtb"]');
        if (myWTBTab) {
            if (this.currentUser) {
                myWTBTab.classList.remove('hidden');
            } else {
                myWTBTab.classList.add('hidden');
            }
        }

        // Load browse WTB by default (public, no login required)
        this.switchWTBTab('browse-wtb');
    };

    MarketplaceApp.prototype.switchWTBTab = function(tabName) {
        // Require login for "My WTB" tab
        if (tabName === 'my-wtb' && !this.currentUser) {
            this.showMessage('Please login to view your WTB orders', 'warning');
            this.switchWTBTab('browse-wtb');
            return;
        }

        // Update tab buttons
        document.querySelectorAll('.wtb-tabs .tab-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.tab === tabName) {
                btn.classList.add('active');
            }
        });

        // Update tab content
        document.querySelectorAll('#want-to-buy-page .tab-content').forEach(content => {
            content.classList.add('hidden');
        });

        const tabContent = document.getElementById(tabName);
        if (tabContent) {
            tabContent.classList.remove('hidden');
        }

        // Load data based on tab
        if (tabName === 'browse-wtb') {
            this.loadWTBListings();
        } else if (tabName === 'my-wtb') {
            this.loadMyWTBListings();
        }
    };

    MarketplaceApp.prototype.loadWTBListings = async function() {
        const container = document.getElementById('wtb-listings-container');
        if (!container) {
            console.error('WTB container not found');
            return;
        }

        container.innerHTML = '<div class="loading">Loading WTB orders...</div>';

        try {
            // Don't pass currentFilters which may have pagination - pass empty object
            const data = await api.getWTBListings({});

            if (data && data.success) {
                this.displayWTBListings(data.wtb_listings, container);
            } else {
                container.innerHTML = '<div class="error">No WTB orders available</div>';
            }
        } catch (error) {
            console.error('Failed to load WTB listings:', error);
            container.innerHTML = `<div class="error">Failed to load WTB orders: ${error.message}</div>`;
        }
    };

    MarketplaceApp.prototype.loadMyWTBListings = async function() {
        const container = document.getElementById('my-wtb-container');
        if (!container) return;

        container.innerHTML = '<div class="loading">Loading your WTB orders...</div>';

        try {
            const characters = api.getCharacters();
            if (!characters || characters.length === 0) {
                container.innerHTML = '<div class="no-data">No characters found</div>';
                return;
            }

            const charId = characters[0].id;
            const data = await api.getMyWTBListings(charId, 'all');

            if (data.success) {
                this.displayMyWTBListings(data.wtb_listings, container);
            }
        } catch (error) {
            console.error('Failed to load my WTB listings:', error);
            container.innerHTML = '<div class="error">Failed to load your WTB orders</div>';
        }
    };

    MarketplaceApp.prototype.displayWTBListings = function(listings, container) {
        if (!listings || listings.length === 0) {
            container.innerHTML = '<div class="no-data">No active WTB orders found</div>';
            return;
        }

        container.innerHTML = '';

        listings.forEach(wtb => {
            const pricePerUnit = this.formatPrice(wtb.price_per_unit_copper);
            const totalPrice = this.formatNumber((wtb.price_per_unit_copper / 1000) * wtb.quantity_remaining);

            const card = document.createElement('div');
            card.className = 'listing-card wtb-card';
            card.style.cursor = 'pointer';

            card.innerHTML = `
                <div class="item-header">
                    <h3 class="item-name">${this.escapeHtml(wtb.item_name)}</h3>
                    <span class="wtb-badge">WTB</span>
                </div>
                <div class="item-details">
                    <p><strong>Buyer:</strong> ${this.escapeHtml(wtb.buyer_name)}</p>
                    <p><strong>Quantity:</strong> ${wtb.quantity_remaining} / ${wtb.quantity_wanted}</p>
                    <p><strong>Price per unit:</strong> ${pricePerUnit} pp</p>
                    <p><strong>Total:</strong> ${totalPrice} pp</p>
                    ${wtb.notes ? `<p class="wtb-notes"><strong>Notes:</strong> ${this.escapeHtml(wtb.notes)}</p>` : ''}
                </div>
                <div class="item-footer">
                    <span class="listed-date">Listed ${this.formatTimeAgo(wtb.created_date)}</span>
                </div>
            `;

            // Add click handler with proper listing object
            // Pass false for isPurchasable since WTB orders can't be purchased (they need to be fulfilled/sold to)
            card.addEventListener('click', () => {
                this.showItemDetails({
                    item_id: wtb.item_id,
                    item_name: wtb.item_name,
                    buyer_name: wtb.buyer_name,  // Use buyer_name for WTB orders
                    quantity: wtb.quantity_remaining,
                    price_copper: wtb.price_per_unit_copper,
                    icon: wtb.icon
                }, false);
            });

            container.appendChild(card);
        });
    };

    MarketplaceApp.prototype.displayMyWTBListings = function(listings, container) {
        if (!listings || listings.length === 0) {
            container.innerHTML = '<div class="no-data">You have no WTB orders</div>';
            return;
        }

        const html = listings.map(wtb => {
            const pricePerUnit = this.formatPrice(wtb.price_per_unit_copper);
            const statusClass = wtb.status === 'active' ? 'status-active' : 'status-inactive';

            return `
                <div class="listing-card wtb-card my-wtb-card ${statusClass}" data-item-id="${wtb.item_id}" data-item-name="${this.escapeHtml(wtb.item_name)}" style="cursor: pointer;">
                    <div class="item-header">
                        <h3 class="item-name">${wtb.item_name}</h3>
                        <span class="status-badge">${wtb.status}</span>
                    </div>
                    <div class="item-details">
                        <p><strong>Quantity:</strong> ${wtb.quantity_fulfilled} / ${wtb.quantity_wanted} fulfilled</p>
                        <p><strong>Remaining:</strong> ${wtb.quantity_remaining}</p>
                        <p><strong>Price per unit:</strong> ${pricePerUnit} pp</p>
                        ${wtb.notes ? `<p class="wtb-notes"><strong>Notes:</strong> ${wtb.notes}</p>` : ''}
                    </div>
                    <div class="item-actions">
                        ${wtb.status === 'active' ? `
                            <button class="btn btn-secondary btn-cancel-wtb" data-wtb-id="${wtb.id}">Cancel</button>
                        ` : ''}
                    </div>
                </div>
            `;
        }).join('');

        container.innerHTML = html;

        // Add click handlers to cancel buttons
        container.querySelectorAll('.btn-cancel-wtb').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const wtbId = parseInt(e.target.dataset.wtbId);
                this.cancelWTBListing(wtbId);
            });
        });

        // Add click handlers to each WTB card to show item details
        container.querySelectorAll('.my-wtb-card').forEach(card => {
            card.addEventListener('click', (e) => {
                // Don't open details if clicking on a button
                if (e.target.tagName === 'BUTTON') return;

                const itemId = parseInt(card.dataset.itemId);
                const itemName = card.dataset.itemName;

                // Call the showItemDetails method with a listing-like object
                // Pass false for isPurchasable since WTB orders can't be purchased
                this.showItemDetails({
                    item_id: itemId,
                    item_name: itemName,
                    price_copper: 0 // WTB doesn't have a listing price
                }, false);
            });
        });
    };

    MarketplaceApp.prototype.showCreateWTBModal = function() {
        const modal = document.getElementById('create-wtb-modal');
        if (modal) {
            modal.classList.remove('hidden');

            // Reset form
            document.getElementById('create-wtb-form').reset();
            document.getElementById('wtb-item-id').value = '';
            document.getElementById('wtb-item-suggestions').innerHTML = '';

            // Populate character dropdown
            const characterSelect = document.getElementById('wtb-character');
            const characters = api.getCharacters();

            if (characterSelect && characters) {
                characterSelect.innerHTML = '<option value="">Select a character...</option>';
                characters.forEach(char => {
                    const option = document.createElement('option');
                    option.value = char.id;
                    option.textContent = char.name;
                    characterSelect.appendChild(option);
                });
            }
        }
    };

    MarketplaceApp.prototype.hideCreateWTBModal = function() {
        const modal = document.getElementById('create-wtb-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    };

    MarketplaceApp.prototype.handleCreateWTB = async function() {
        try {
            const itemId = document.getElementById('wtb-item-id').value;
            const charId = document.getElementById('wtb-character').value;
            const quantity = document.getElementById('wtb-quantity').value;
            const pricePerUnit = document.getElementById('wtb-price').value;
            const expires = document.getElementById('wtb-expires').value;
            const notes = document.getElementById('wtb-notes').value;

            if (!itemId) {
                this.showError('Please select an item');
                return;
            }

            if (!charId) {
                this.showError('Please select a character');
                return;
            }

            const wtbData = {
                char_id: parseInt(charId),
                item_id: parseInt(itemId),
                quantity_wanted: parseInt(quantity),
                price_per_unit: parseFloat(pricePerUnit),
                expires_days: parseInt(expires),
                notes: notes || null
            };

            const data = await api.createWTBListing(wtbData);

            if (data.success) {
                this.showSuccess('WTB order created successfully!');
                this.hideCreateWTBModal();
                this.loadMyWTBListings();
            }
        } catch (error) {
            console.error('Failed to create WTB:', error);
            this.showError(error.message || 'Failed to create WTB order');
        }
    };

    MarketplaceApp.prototype.cancelWTBListing = async function(wtbId) {
        if (!confirm('Are you sure you want to cancel this WTB order?')) {
            return;
        }

        try {
            const characters = api.getCharacters();
            if (!characters || characters.length === 0) return;

            const charId = characters[0].id;
            const data = await api.cancelWTBListing(wtbId, charId);

            if (data.success) {
                this.showSuccess('WTB order cancelled');
                this.loadMyWTBListings();
            }
        } catch (error) {
            console.error('Failed to cancel WTB:', error);
            this.showError(error.message || 'Failed to cancel WTB order');
        }
    };

    // ===================================================================
    // Watchlist
    // ===================================================================

    MarketplaceApp.prototype.loadWatchlistPage = async function() {
        if (!this.currentUser) {
            this.showError('Please login to view your watchlist');
            return;
        }

        const container = document.getElementById('watchlist-container');
        if (!container) return;

        container.innerHTML = '<div class="loading">Loading watchlist...</div>';

        try {
            const characters = api.getCharacters();
            if (!characters || characters.length === 0) {
                container.innerHTML = '<div class="no-data">No characters found</div>';
                return;
            }

            const charId = characters[0].id;
            const data = await api.getWatchlist(charId);

            if (data.success) {
                this.displayWatchlist(data.watchlist, container);
            }
        } catch (error) {
            console.error('Failed to load watchlist:', error);
            container.innerHTML = '<div class="error">Failed to load watchlist</div>';
        }
    };

    MarketplaceApp.prototype.displayWatchlist = function(watchlist, container) {
        if (!watchlist || watchlist.length === 0) {
            container.innerHTML = '<div class="no-data">Your watchlist is empty. Add items to get notified!</div>';
            return;
        }

        const html = watchlist.map(item => {
            const maxPrice = item.max_price_copper ? this.formatPrice(item.max_price_copper) + ' pp' : 'Any price';
            const criteria = [];
            if (item.min_ac) criteria.push(`AC ≥ ${item.min_ac}`);
            if (item.min_hp) criteria.push(`HP ≥ ${item.min_hp}`);
            if (item.min_mana) criteria.push(`Mana ≥ ${item.min_mana}`);

            return `
                <div class="watchlist-card">
                    <div class="item-header">
                        <h3 class="item-name">${item.item_name || item.item_name_search || 'Any item'}</h3>
                    </div>
                    <div class="item-details">
                        <p><strong>Max Price:</strong> ${maxPrice}</p>
                        ${criteria.length > 0 ? `<p><strong>Requirements:</strong> ${criteria.join(', ')}</p>` : ''}
                        ${item.notes ? `<p class="watchlist-notes">${item.notes}</p>` : ''}
                        <p class="added-date">Added ${this.formatTimeAgo(item.created_date)}</p>
                    </div>
                    <div class="item-actions">
                        <button class="btn btn-secondary btn-remove-watchlist" data-watchlist-id="${item.id}">Remove</button>
                    </div>
                </div>
            `;
        }).join('');

        container.innerHTML = html;

        // Attach event listeners to remove buttons
        container.querySelectorAll('.btn-remove-watchlist').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const watchlistId = parseInt(e.target.dataset.watchlistId);
                this.removeFromWatchlist(watchlistId);
            });
        });
    };

    MarketplaceApp.prototype.showAddWatchlistModal = function() {
        const modal = document.getElementById('add-watchlist-modal');
        if (modal) {
            modal.classList.remove('hidden');

            // Reset form
            document.getElementById('add-watchlist-form').reset();
            document.getElementById('watchlist-item-id').value = '';
            document.getElementById('watchlist-item-suggestions').innerHTML = '';
        }
    };

    MarketplaceApp.prototype.hideAddWatchlistModal = function() {
        const modal = document.getElementById('add-watchlist-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    };

    MarketplaceApp.prototype.handleAddToWatchlist = async function() {
        try {
            const itemId = document.getElementById('watchlist-item-id').value;
            const itemSearch = document.getElementById('watchlist-item-search').value;
            const maxPrice = document.getElementById('watchlist-max-price').value;
            const notes = document.getElementById('watchlist-notes').value;

            if (!itemId && !itemSearch) {
                this.showError('Please select an item or enter a search term');
                return;
            }

            const characters = api.getCharacters();
            if (!characters || characters.length === 0) {
                this.showError('No characters found');
                return;
            }

            const charId = characters[0].id;

            const watchlistData = {
                char_id: charId,
                item_id: itemId ? parseInt(itemId) : null,
                item_name_search: itemSearch || null,
                max_price: maxPrice ? parseFloat(maxPrice) : null,
                notes: notes || null
            };

            const data = await api.addToWatchlist(watchlistData);

            if (data.success) {
                this.showSuccess('Item added to watchlist!');
                this.hideAddWatchlistModal();
                this.loadWatchlistPage();
            }
        } catch (error) {
            console.error('Failed to add to watchlist:', error);
            this.showError(error.message || 'Failed to add to watchlist');
        }
    };

    MarketplaceApp.prototype.removeFromWatchlist = async function(watchlistId) {
        if (!confirm('Remove this item from your watchlist?')) {
            return;
        }

        try {
            const characters = api.getCharacters();
            if (!characters || characters.length === 0) return;

            const charId = characters[0].id;
            const data = await api.removeFromWatchlist(watchlistId, charId);

            if (data.success) {
                this.showSuccess('Item removed from watchlist');
                this.loadWatchlistPage();
            }
        } catch (error) {
            console.error('Failed to remove from watchlist:', error);
            this.showError(error.message || 'Failed to remove from watchlist');
        }
    };

    // ===================================================================
    // Item Search Autocomplete
    // ===================================================================

    MarketplaceApp.prototype.handleItemSearch = async function(query, context) {
        if (!query || query.length < 2) {
            this.hideItemSuggestions(context);
            return;
        }

        try {
            const data = await api.searchItems(query, 10);

            if (data.success && data.items.length > 0) {
                this.showItemSuggestions(data.items, context);
            } else {
                this.hideItemSuggestions(context);
            }
        } catch (error) {
            console.error('Item search error:', error);
        }
    };

    MarketplaceApp.prototype.showItemSuggestions = function(items, context) {
        const suggestionsId = context === 'wtb' ? 'wtb-item-suggestions' : 'watchlist-item-suggestions';
        const container = document.getElementById(suggestionsId);
        if (!container) return;

        // Store items data for popout
        this.currentItemSuggestions = items;

        const html = items.map((item, index) => {
            return `
                <div class="item-suggestion" data-id="${item.id}" data-name="${item.name}" data-context="${context}" data-item-index="${index}">
                    <span class="item-name">${item.name}</span>
                    <span class="item-id">#${item.id}</span>
                </div>
            `;
        }).join('');

        container.innerHTML = html;
        container.style.display = 'block';

        // Create inspection popout element if it doesn't exist
        let popout = document.getElementById('item-inspection-popout');
        if (!popout) {
            popout = document.createElement('div');
            popout.id = 'item-inspection-popout';
            popout.className = 'item-inspection-popout';
            document.body.appendChild(popout);

            // Keep popout visible when hovering over it
            popout.addEventListener('mouseenter', () => {
                popout.classList.add('visible');
            });

            popout.addEventListener('mouseleave', () => {
                this.hideItemInspectionPopout();
            });
        }

        // Attach event listeners to all suggestions
        container.querySelectorAll('.item-suggestion').forEach(suggestion => {
            // Click to select
            suggestion.addEventListener('click', (e) => {
                const itemId = parseInt(suggestion.dataset.id);
                const itemName = suggestion.dataset.name;
                const ctx = suggestion.dataset.context;
                this.selectItem(itemId, itemName, ctx);
            });

            // Hover to show inspection popout
            suggestion.addEventListener('mouseenter', (e) => {
                const itemIndex = parseInt(suggestion.dataset.itemIndex);
                const item = this.currentItemSuggestions[itemIndex];
                this.showItemInspectionPopout(item, suggestion);
            });

            suggestion.addEventListener('mouseleave', (e) => {
                // Use a small delay to allow moving to the popout
                setTimeout(() => {
                    const popout = document.getElementById('item-inspection-popout');
                    if (popout && !popout.matches(':hover')) {
                        this.hideItemInspectionPopout();
                    }
                }, 100);
            });
        });
    };

    MarketplaceApp.prototype.hideItemSuggestions = function(context) {
        const suggestionsId = context === 'wtb' ? 'wtb-item-suggestions' : 'watchlist-item-suggestions';
        const container = document.getElementById(suggestionsId);
        if (container) {
            container.innerHTML = '';
            container.style.display = 'none';
        }
        // Also hide the inspection popout and clear stored items
        this.hideItemInspectionPopout();
        this.currentItemSuggestions = [];
    };

    MarketplaceApp.prototype.selectItem = function(itemId, itemName, context) {
        if (context === 'wtb') {
            document.getElementById('wtb-item-id').value = itemId;
            document.getElementById('wtb-item-search').value = itemName;
            this.hideItemSuggestions('wtb');
        } else if (context === 'watchlist') {
            document.getElementById('watchlist-item-id').value = itemId;
            document.getElementById('watchlist-item-search').value = itemName;
            this.hideItemSuggestions('watchlist');
        }
    };

    MarketplaceApp.prototype.showItemInspectionPopout = function(item, targetElement) {
        const popout = document.getElementById('item-inspection-popout');
        if (!popout || !item) return;

        // Build stats array - only show non-zero stats
        const stats = [];
        if (item.ac > 0) stats.push({ label: 'AC', value: item.ac });
        if (item.hp > 0) stats.push({ label: 'HP', value: item.hp });
        if (item.mana > 0) stats.push({ label: 'Mana', value: item.mana });
        if (item.astr > 0) stats.push({ label: 'STR', value: item.astr });
        if (item.asta > 0) stats.push({ label: 'STA', value: item.asta });
        if (item.aagi > 0) stats.push({ label: 'AGI', value: item.aagi });
        if (item.adex > 0) stats.push({ label: 'DEX', value: item.adex });
        if (item.awis > 0) stats.push({ label: 'WIS', value: item.awis });
        if (item.aint > 0) stats.push({ label: 'INT', value: item.aint });
        if (item.acha > 0) stats.push({ label: 'CHA', value: item.acha });

        // Build HTML
        const iconHTML = item.icon && CONFIG.ICON_BASE_URL ?
            `<img src="${CONFIG.ICON_BASE_URL}${item.icon}.png" alt="${item.name}">` :
            '🎒';

        const statsHTML = stats.length > 0 ?
            stats.map(stat => `
                <div class="item-inspection-stat">
                    <span class="item-inspection-stat-label">${stat.label}:</span>
                    <span class="item-inspection-stat-value">+${stat.value}</span>
                </div>
            `).join('') :
            '<div class="item-inspection-no-stats">No combat stats</div>';

        // Always show stacksize
        const stacksize = item.stacksize || 1;

        popout.innerHTML = `
            <div class="item-inspection-header">
                <div class="item-inspection-icon">${iconHTML}</div>
                <div class="item-inspection-title">
                    <h4 class="item-inspection-name">${this.escapeHtml(item.name)}</h4>
                    <p class="item-inspection-id">Item ID: ${item.id}</p>
                    <p class="item-inspection-stacksize">Stack Size: ${stacksize}</p>
                </div>
            </div>
            <div class="item-inspection-body">
                ${statsHTML}
            </div>
            <div class="item-inspection-footer">
                <a href="http://65.49.60.92:8000/items/${item.id}" target="_blank" class="item-inspection-link">
                    🔍 View Full Details
                </a>
            </div>
        `;

        // Position popout next to the target element
        const rect = targetElement.getBoundingClientRect();
        const popoutRect = popout.getBoundingClientRect();

        // Try to position to the right first
        let left = rect.right + 10;
        let top = rect.top;

        // If it goes off screen to the right, position to the left instead
        if (left + 400 > window.innerWidth) {
            left = rect.left - 410;
        }

        // If it goes off screen at the top, adjust
        if (top < 0) {
            top = 10;
        }

        // If it goes off screen at the bottom, adjust
        if (top + 300 > window.innerHeight) {
            top = window.innerHeight - 310;
        }

        popout.style.left = left + 'px';
        popout.style.top = top + window.scrollY + 'px';
        popout.classList.add('visible');
    };

    MarketplaceApp.prototype.hideItemInspectionPopout = function() {
        const popout = document.getElementById('item-inspection-popout');
        if (popout) {
            popout.classList.remove('visible');
        }
    };

    // ===================================================================
    // Helper Methods
    // ===================================================================

    MarketplaceApp.prototype.showSuccess = function(message) {
        // Simple alert for now - can be enhanced with a toast notification
        alert(message);
    };

    MarketplaceApp.prototype.showError = function(message) {
        // Simple alert for now - can be enhanced with a toast notification
        alert('Error: ' + message);
    };

    // ===================================================================
    // Pagination
    // ===================================================================

    MarketplaceApp.prototype.currentPage = 1;
    MarketplaceApp.prototype.itemsPerPage = 20;
    MarketplaceApp.prototype.paginationData = null;

    MarketplaceApp.prototype.initPagination = function() {
        const itemsPerPageSelect = document.getElementById('items-per-page');
        if (itemsPerPageSelect) {
            itemsPerPageSelect.addEventListener('change', () => {
                this.itemsPerPage = parseInt(itemsPerPageSelect.value);
                this.currentPage = 1;
                this.loadMarketplace();
            });
        }

        document.getElementById('first-page')?.addEventListener('click', () => this.goToPage(1));
        document.getElementById('prev-page')?.addEventListener('click', () => this.goToPage(this.currentPage - 1));
        document.getElementById('next-page')?.addEventListener('click', () => this.goToPage(this.currentPage + 1));
        document.getElementById('last-page')?.addEventListener('click', () => {
            if (this.paginationData) {
                this.goToPage(this.paginationData.total_pages);
            }
        });
    };

    MarketplaceApp.prototype.goToPage = function(page) {
        if (!this.paginationData) return;

        if (page < 1 || page > this.paginationData.total_pages) return;

        this.currentPage = page;
        this.loadMarketplace();

        // Scroll to top of listings
        const container = document.getElementById('listings-container');
        if (container) {
            container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    MarketplaceApp.prototype.renderPagination = function(paginationData) {
        this.paginationData = paginationData;

        const paginationControls = document.getElementById('pagination-controls');
        const pageNumbers = document.getElementById('page-numbers');
        const paginationInfo = document.getElementById('pagination-info');

        if (!paginationControls || !pageNumbers || !paginationInfo) return;

        // Show pagination if more than one page
        if (paginationData.total_pages > 1) {
            paginationControls.classList.remove('hidden');
        } else {
            paginationControls.classList.add('hidden');
            return;
        }

        // Update buttons state
        document.getElementById('first-page').disabled = !paginationData.has_prev;
        document.getElementById('prev-page').disabled = !paginationData.has_prev;
        document.getElementById('next-page').disabled = !paginationData.has_next;
        document.getElementById('last-page').disabled = !paginationData.has_next;

        // Render page numbers
        pageNumbers.innerHTML = '';
        const currentPage = paginationData.current_page;
        const totalPages = paginationData.total_pages;

        const renderPageButton = (pageNum, isActive) => {
            const button = document.createElement('div');
            button.className = 'page-number' + (isActive ? ' active' : '');
            button.textContent = pageNum;
            if (!isActive) {
                button.addEventListener('click', () => this.goToPage(pageNum));
            }
            return button;
        };

        const renderEllipsis = () => {
            const ellipsis = document.createElement('div');
            ellipsis.className = 'page-number ellipsis';
            ellipsis.textContent = '...';
            return ellipsis;
        };

        // Show max 7 page numbers with ellipsis
        if (totalPages <= 7) {
            for (let i = 1; i <= totalPages; i++) {
                pageNumbers.appendChild(renderPageButton(i, i === currentPage));
            }
        } else {
            // Always show first page
            pageNumbers.appendChild(renderPageButton(1, currentPage === 1));

            if (currentPage > 3) {
                pageNumbers.appendChild(renderEllipsis());
            }

            // Show pages around current
            const start = Math.max(2, currentPage - 1);
            const end = Math.min(totalPages - 1, currentPage + 1);

            for (let i = start; i <= end; i++) {
                pageNumbers.appendChild(renderPageButton(i, i === currentPage));
            }

            if (currentPage < totalPages - 2) {
                pageNumbers.appendChild(renderEllipsis());
            }

            // Always show last page
            pageNumbers.appendChild(renderPageButton(totalPages, currentPage === totalPages));
        }

        // Update info text
        const start = (currentPage - 1) * paginationData.per_page + 1;
        const end = Math.min(currentPage * paginationData.per_page, paginationData.total_items);
        paginationInfo.textContent = `Showing ${start}-${end} of ${paginationData.total_items} items`;
    };

    // Override loadMarketplace to include pagination
    const originalLoadMarketplace = MarketplaceApp.prototype.loadMarketplace;
    MarketplaceApp.prototype.loadMarketplace = async function() {
        try {
            const container = document.getElementById('listings-container');
            if (!container) return;

            container.innerHTML = '<div class="loading">Loading listings...</div>';

            // Add pagination to filters
            this.currentFilters = {
                ...this.currentFilters,
                page: this.currentPage,
                limit: this.itemsPerPage
            };

            const data = await api.getListings(this.currentFilters);

            if (data.success && data.listings) {
                this.displayListings(data.listings, container);

                // Render pagination if available
                if (data.pagination) {
                    this.renderPagination(data.pagination);
                }
            } else {
                container.innerHTML = '<div class="no-data">No listings found.</div>';
            }
        } catch (error) {
            console.error('Failed to load marketplace:', error);
            const container = document.getElementById('listings-container');
            if (container) {
                container.innerHTML = `<div class="error">${error.message}</div>`;
            }
        }
    };

    // ===================================================================
    // View Toggle (Grid/List)
    // ===================================================================

    MarketplaceApp.prototype.initViewToggle = function() {
        const viewButtons = document.querySelectorAll('.btn-view');
        const listingsContainer = document.getElementById('listings-container');

        // Load saved view preference (default to list view)
        const savedView = localStorage.getItem('marketplace-view') || 'list';
        if (listingsContainer) {
            listingsContainer.setAttribute('data-view', savedView);
        }

        // Set active button
        viewButtons.forEach(btn => {
            if (btn.dataset.view === savedView) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // Add click handlers
        viewButtons.forEach(button => {
            button.addEventListener('click', () => {
                const view = button.dataset.view;

                // Update active button
                viewButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');

                // Update container
                if (listingsContainer) {
                    listingsContainer.setAttribute('data-view', view);
                }

                // Save preference
                localStorage.setItem('marketplace-view', view);
            });
        });
    };

    // ===================================================================
    // Quick View Modal
    // ===================================================================

    MarketplaceApp.prototype.openQuickView = function(listingId) {
        const modal = document.getElementById('quick-view-modal');
        const content = document.getElementById('quick-view-content');

        if (!modal || !content) return;

        modal.classList.remove('hidden');
        content.innerHTML = '<div class="loading">Loading item details...</div>';

        this.loadQuickViewItem(listingId);
    };

    MarketplaceApp.prototype.loadQuickViewItem = async function(listingId) {
        try {
            const data = await api.getListingById(listingId);
            const content = document.getElementById('quick-view-content');

            if (!content) return;

            if (data.success && data.listing) {
                const listing = data.listing;
                const item = listing.item;
                const priceInPP = this.formatPrice(listing.price_copper, 3);

                // Build augments display
                let augmentsHTML = '';
                if (listing.augments && listing.augments.length > 0) {
                    const validAugments = listing.augments.filter(aug => aug > 0);
                    if (validAugments.length > 0) {
                        augmentsHTML = `<p><strong>Augments:</strong> ${validAugments.join(', ')}</p>`;
                    }
                }

                content.innerHTML = `
                    <div class="quick-view-header">
                        <div class="quick-view-listing-info">
                            <h3>${this.escapeHtml(item.name)}</h3>
                            <div class="quick-view-price">${priceInPP} pp</div>
                            <p><strong>Seller:</strong> ${this.escapeHtml(listing.seller_name)}</p>
                            <p><strong>Quantity:</strong> ${listing.quantity}</p>
                            ${listing.charges > 0 ? `<p><strong>Charges:</strong> ${listing.charges}</p>` : ''}
                            ${augmentsHTML}
                        </div>
                        <div class="quick-view-actions">
                            <button class="btn btn-primary" id="quick-view-purchase-btn" data-listing-id="${listing.id}">Purchase</button>
                            <button class="btn btn-secondary" id="quick-view-close-btn">Close</button>
                        </div>
                    </div>
                    <div class="quick-view-iframe-container">
                        <iframe src="http://65.49.60.92:8000/items/${item.id}"
                                frameborder="0"
                                width="100%"
                                height="600px"
                                style="background: white; border-radius: 4px;">
                        </iframe>
                    </div>
                `;

                // Attach event listeners to buttons
                const purchaseBtn = document.getElementById('quick-view-purchase-btn');
                const closeBtn = document.getElementById('quick-view-close-btn');
                const modal = document.getElementById('quick-view-modal');

                if (purchaseBtn) {
                    purchaseBtn.addEventListener('click', () => {
                        this.showPurchaseConfirm(listing);
                    });
                }

                if (closeBtn && modal) {
                    closeBtn.addEventListener('click', () => {
                        modal.classList.add('hidden');
                    });
                }
            } else {
                content.innerHTML = '<div class="error">Failed to load item details</div>';
            }
        } catch (error) {
            console.error('Failed to load quick view:', error);
            const content = document.getElementById('quick-view-content');
            if (content) {
                content.innerHTML = `<div class="error">${error.message}</div>`;
            }
        }
    };

    MarketplaceApp.prototype.initQuickViewModal = function() {
        const closeBtn = document.getElementById('close-quick-view');
        const modal = document.getElementById('quick-view-modal');

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                if (modal) modal.classList.add('hidden');
            });
        }

        // Close on background click
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                }
            });
        }
    };

    // Update displayListings to add quick view button
    const originalDisplayListings = MarketplaceApp.prototype.displayListings;
    MarketplaceApp.prototype.displayListings = function(listings, container) {
        // Call the original function first
        if (originalDisplayListings) {
            originalDisplayListings.call(this, listings, container);
        }

        // Now enhance the cards with quick view buttons
        if (container && listings && listings.length > 0) {
            const cards = container.querySelectorAll('.listing-card');
            cards.forEach((card, index) => {
                const listing = listings[index];
                if (!listing) return;

                // Add quick view button if it doesn't already exist
                const actions = card.querySelector('.listing-actions');
                if (actions && !card.querySelector('.btn-quick-view')) {
                    const quickViewBtn = document.createElement('button');
                    quickViewBtn.className = 'btn btn-secondary btn-sm btn-quick-view';
                    quickViewBtn.textContent = 'Quick View';
                    quickViewBtn.onclick = () => this.openQuickView(listing.id);
                    actions.insertBefore(quickViewBtn, actions.firstChild);
                }
            });
        }
    };

}
