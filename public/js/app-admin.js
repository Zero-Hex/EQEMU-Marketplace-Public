/**
 * Admin functionality for Marketplace App
 * Extends MarketplaceApp with admin-only features
 */

if (typeof MarketplaceApp !== 'undefined') {

    // ===================================================================
    // Admin Functions
    // ===================================================================

    MarketplaceApp.prototype.isAdmin = function() {
        return this.currentUser && this.currentUser.status && this.currentUser.status >= 80;
    };

    MarketplaceApp.prototype.showAdminNav = function() {
        const adminLink = document.getElementById('admin-nav-link');
        if (adminLink && this.isAdmin()) {
            adminLink.classList.remove('hidden');
        } else if (adminLink) {
            adminLink.classList.add('hidden');
        }
    };

    MarketplaceApp.prototype.loadAdminPage = async function() {
        console.log('Loading admin page...');
        console.log('Current user:', this.currentUser);
        console.log('User status:', this.currentUser?.status);
        console.log('Is admin:', this.isAdmin());

        // Check status with backend
        try {
            const statusCheck = await api.request('/admin/check-status.php');
            console.log('Backend status check:', statusCheck);

            if (statusCheck.success && statusCheck.account) {
                // Update the page title to show current status
                const pageTitle = document.querySelector('#admin-page .page-title');
                if (pageTitle) {
                    pageTitle.innerHTML = `Admin Panel <span style="color: #bbb; font-size: 0.8em; font-weight: normal;">(Status: ${statusCheck.account.status}${statusCheck.account.is_gm ? ' ✓ GM' : ' ✗ Not GM'})</span>`;
                }
            }
        } catch (error) {
            console.error('Failed to check status:', error);
        }

        if (!this.isAdmin()) {
            const errorMsg = this.currentUser
                ? `Unauthorized access. Your account status (${this.currentUser.status}) is below GM level (80).`
                : 'Unauthorized access. Please log in with a GM account.';
            this.showError(errorMsg);
            this.navigateToPage('marketplace');
            return;
        }

        // Load admin listings by default
        this.switchAdminTab('admin-listings');
    };

    MarketplaceApp.prototype.switchAdminTab = function(tabName) {
        // Update tab buttons
        document.querySelectorAll('.admin-tabs .tab-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.tab === tabName) {
                btn.classList.add('active');
            }
        });

        // Update tab content
        document.querySelectorAll('#admin-page .tab-content').forEach(content => {
            content.classList.add('hidden');
        });

        const tabContent = document.getElementById(tabName);
        if (tabContent) {
            tabContent.classList.remove('hidden');
        }

        // Load data based on tab
        if (tabName === 'admin-listings') {
            this.loadAdminListings();
        } else if (tabName === 'admin-wtb') {
            this.loadAdminWTB();
        } else if (tabName === 'admin-users') {
            this.loadAdminUsers();
        } else if (tabName === 'admin-stats') {
            this.loadAdminStats();
        }
    };

    MarketplaceApp.prototype.loadAdminListings = async function() {
        const container = document.getElementById('admin-listings-container');
        if (!container) return;

        container.innerHTML = '<div class="loading">Loading all listings...</div>';

        try {
            console.log('Loading admin listings...');
            console.log('Auth token:', api.token ? 'Present' : 'Missing');

            const searchTerm = document.getElementById('admin-listing-search')?.value || '';
            const endpoint = '/admin/all-listings.php' + (searchTerm ? '?search=' + encodeURIComponent(searchTerm) : '');

            console.log('Requesting:', endpoint);

            const response = await api.request(endpoint);

            console.log('Admin listings response:', response);

            if (response.success && response.listings) {
                this.displayAdminListings(response.listings, container);
            } else {
                container.innerHTML = '<div class="no-data">No listings found</div>';
            }
        } catch (error) {
            console.error('Failed to load admin listings:', error);
            let errorMsg = error.message || 'Failed to load listings';

            // Provide more helpful error messages
            if (errorMsg.includes('Authorization required') || errorMsg.includes('Unauthorized')) {
                errorMsg = 'Authorization failed. Please ensure:<br>1. You are logged in<br>2. Your account has GM status (level 80+)<br>3. Your session token is valid';
            }

            container.innerHTML = '<div class="error">' + errorMsg + '</div>';
        }
    };

    MarketplaceApp.prototype.displayAdminListings = function(listings, container) {
        if (!listings || listings.length === 0) {
            container.innerHTML = '<div class="no-data">No listings found</div>';
            return;
        }

        // Create table structure with scroll wrapper
        container.innerHTML = `
            <div class="table-scroll-wrapper">
                <table class="admin-listings-table">
                    <thead>
                        <tr>
                            <th>Item ID</th>
                            <th>Item Name</th>
                            <th>Seller</th>
                            <th>Buyer</th>
                            <th>Quantity</th>
                            <th>Price (pp)</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Listed Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="admin-listings-tbody"></tbody>
                </table>
            </div>
        `;

        const tbody = document.getElementById('admin-listings-tbody');

        listings.forEach(listing => {
            const row = document.createElement('tr');
            const priceInPP = this.formatPrice(listing.price_copper);
            const listedDate = new Date(listing.listed_date).toLocaleDateString();
            const itemNameEscaped = this.escapeHtml(listing.item_name);
            const buyerDisplay = listing.buyer_name ? this.escapeHtml(listing.buyer_name) : '-';
            const isActive = listing.status === 'active';
            const isSold = listing.status === 'sold';
            const paymentStatus = listing.payment_status || '-';
            const hasTransaction = listing.transaction_id !== null;
            const canRestore = isSold && hasTransaction;

            // Payment status display with color coding
            let paymentDisplay = paymentStatus;
            let paymentClass = '';
            if (paymentStatus === 'pending') {
                paymentClass = 'status-pending';
                paymentDisplay = '<span class="status-badge pending">Pending</span>';
            } else if (paymentStatus === 'paid') {
                paymentClass = 'status-paid';
                paymentDisplay = '<span class="status-badge paid">Paid</span>';
            } else if (paymentStatus === 'cancelled') {
                paymentClass = 'status-cancelled';
                paymentDisplay = '<span class="status-badge cancelled">Cancelled</span>';
            } else {
                paymentDisplay = '<span class="status-badge">-</span>';
            }

            row.innerHTML = `
                <td>${listing.item_id}</td>
                <td><strong>${itemNameEscaped}</strong></td>
                <td>${this.escapeHtml(listing.seller_name)}</td>
                <td>${buyerDisplay}</td>
                <td>${listing.quantity}</td>
                <td>${priceInPP}</td>
                <td><span class="status-badge ${listing.status}">${listing.status}</span></td>
                <td>${paymentDisplay}</td>
                <td>${listedDate}</td>
                <td class="admin-actions">
                    ${isActive ? `<button class="btn btn-warning btn-sm admin-cancel-btn" data-listing-id="${listing.id}" data-item-name="${itemNameEscaped}">Cancel</button>` : ''}
                    ${canRestore ? `<button class="btn btn-primary btn-sm admin-restore-btn" data-listing-id="${listing.id}" data-item-name="${itemNameEscaped}">Restore</button>` : ''}
                    <button class="btn btn-danger btn-sm admin-delete-btn" data-listing-id="${listing.id}" data-item-name="${itemNameEscaped}">Delete</button>
                </td>
            `;

            // Add cancel button handler (only for active listings)
            if (isActive) {
                const cancelBtn = row.querySelector('.admin-cancel-btn');
                cancelBtn.addEventListener('click', () => {
                    this.adminCancelListing(listing.id, itemNameEscaped);
                });
            }

            // Add restore button handler (only for sold listings with transactions)
            if (canRestore) {
                const restoreBtn = row.querySelector('.admin-restore-btn');
                restoreBtn.addEventListener('click', () => {
                    this.adminRestoreListing(listing.id, itemNameEscaped, listing.buyer_name, paymentStatus);
                });
            }

            // Add delete button handler
            const deleteBtn = row.querySelector('.admin-delete-btn');
            deleteBtn.addEventListener('click', () => {
                this.adminDeleteListing(listing.id, itemNameEscaped);
            });

            tbody.appendChild(row);
        });
    };

    MarketplaceApp.prototype.loadAdminWTB = async function() {
        const container = document.getElementById('admin-wtb-container');
        if (!container) return;

        container.innerHTML = '<div class="loading">Loading all WTB orders...</div>';

        try {
            const searchTerm = document.getElementById('admin-wtb-search')?.value || '';
            const endpoint = '/admin/all-wtb.php' + (searchTerm ? '?search=' + encodeURIComponent(searchTerm) : '');

            const response = await api.request(endpoint);

            if (response.success && response.wtb_orders) {
                this.displayAdminWTB(response.wtb_orders, container);
            } else {
                container.innerHTML = '<div class="no-data">No WTB orders found</div>';
            }
        } catch (error) {
            console.error('Failed to load admin WTB:', error);
            container.innerHTML = '<div class="error">Failed to load WTB orders: ' + error.message + '</div>';
        }
    };

    MarketplaceApp.prototype.displayAdminWTB = function(orders, container) {
        if (!orders || orders.length === 0) {
            container.innerHTML = '<div class="no-data">No WTB orders found</div>';
            return;
        }

        // Create table structure with scroll wrapper
        container.innerHTML = `
            <div class="table-scroll-wrapper">
                <table class="admin-wtb-table">
                    <thead>
                        <tr>
                            <th>Item ID</th>
                            <th>Item Name</th>
                            <th>Buyer</th>
                            <th>Quantity</th>
                            <th>Price/Unit (pp)</th>
                            <th>Total (pp)</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="admin-wtb-tbody"></tbody>
                </table>
            </div>
        `;

        const tbody = document.getElementById('admin-wtb-tbody');

        orders.forEach(wtb => {
            const row = document.createElement('tr');
            const pricePerUnit = this.formatPrice(wtb.price_per_unit_copper);
            const totalPrice = this.formatNumber((wtb.price_per_unit_copper / 1000) * wtb.quantity_remaining);
            const createdDate = new Date(wtb.created_date).toLocaleDateString();
            const itemNameEscaped = this.escapeHtml(wtb.item_name);
            const notesDisplay = wtb.notes ? this.escapeHtml(wtb.notes) : '-';
            const notesTitle = wtb.notes ? this.escapeHtml(wtb.notes) : '';

            row.innerHTML = `
                <td>${wtb.item_id}</td>
                <td><strong>${itemNameEscaped}</strong></td>
                <td>${this.escapeHtml(wtb.buyer_name)}</td>
                <td>${wtb.quantity_remaining} / ${wtb.quantity_wanted}</td>
                <td>${pricePerUnit}</td>
                <td>${totalPrice}</td>
                <td><span class="status-badge ${wtb.status}">${wtb.status}</span></td>
                <td>${createdDate}</td>
                <td class="wtb-notes-cell" title="${notesTitle}">${notesDisplay}</td>
                <td class="admin-actions">
                    <button class="btn btn-danger btn-sm admin-delete-btn" data-wtb-id="${wtb.id}" data-item-name="${itemNameEscaped}">Delete</button>
                </td>
            `;

            // Add delete button handler
            const deleteBtn = row.querySelector('.admin-delete-btn');
            deleteBtn.addEventListener('click', () => {
                this.adminDeleteWTB(wtb.id, itemNameEscaped);
            });

            tbody.appendChild(row);
        });
    };

    MarketplaceApp.prototype.loadAdminStats = async function() {
        try {
            const response = await api.request('/admin/stats.php');

            if (response.success && response.stats) {
                document.getElementById('stat-total-listings').textContent = response.stats.total_listings;
                document.getElementById('stat-total-wtb').textContent = response.stats.total_wtb;
                document.getElementById('stat-total-transactions').textContent = response.stats.total_transactions;
                document.getElementById('stat-active-users').textContent = response.stats.active_users;
            }
        } catch (error) {
            console.error('Failed to load admin stats:', error);
        }
    };

    MarketplaceApp.prototype.loadAdminUsers = async function() {
        const container = document.getElementById('admin-users-container');
        if (!container) return;

        container.innerHTML = '<div class="loading">Loading registered users...</div>';

        try {
            const searchTerm = document.getElementById('admin-user-search')?.value || '';
            const endpoint = '/admin/all-users.php' + (searchTerm ? '?search=' + encodeURIComponent(searchTerm) : '');

            const response = await api.request(endpoint);

            if (response.success && response.users) {
                this.displayAdminUsers(response.users, container);
            } else {
                container.innerHTML = '<div class="no-data">No users found</div>';
            }
        } catch (error) {
            console.error('Failed to load admin users:', error);
            container.innerHTML = '<div class="error">Failed to load users: ' + error.message + '</div>';
        }
    };

    MarketplaceApp.prototype.displayAdminUsers = function(users, container) {
        if (!users || users.length === 0) {
            container.innerHTML = '<div class="no-data">No users found</div>';
            return;
        }

        container.innerHTML = '<div class="table-scroll-wrapper"><table class="admin-users-table"><thead><tr><th>Account</th><th>Status</th><th>Email</th><th>Characters</th><th>Listings</th><th>WTB Orders</th><th>Registered</th><th>Last Login</th><th>Actions</th></tr></thead><tbody id="admin-users-tbody"></tbody></table></div>';

        const tbody = document.getElementById('admin-users-tbody');

        users.forEach(user => {
            const row = document.createElement('tr');
            const registeredDate = user.registered_date ? new Date(user.registered_date).toLocaleDateString() : 'N/A';
            const lastLogin = user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never';

            row.innerHTML = `
                <td><strong>${this.escapeHtml(user.account_name)}</strong></td>
                <td><span class="status-level">${user.status}</span></td>
                <td>${user.email ? this.escapeHtml(user.email) : 'N/A'}</td>
                <td>${user.character_count}</td>
                <td>${user.active_listings} / ${user.total_listings}</td>
                <td>${user.total_wtb_orders}</td>
                <td>${registeredDate}</td>
                <td>${lastLogin}</td>
                <td>
                    <button class="btn btn-warning btn-sm" data-account-id="${user.account_id}" data-account-name="${this.escapeHtml(user.account_name)}">Reset Password</button>
                </td>
            `;

            // Add reset password button handler
            const resetBtn = row.querySelector('.btn-warning');
            resetBtn.addEventListener('click', () => {
                this.adminResetPassword(user.account_id, user.account_name);
            });

            tbody.appendChild(row);
        });
    };

    MarketplaceApp.prototype.adminResetPassword = async function(accountId, accountName) {
        const newPassword = prompt(`Enter new password for account "${accountName}":`);

        if (!newPassword) {
            return;
        }

        if (newPassword.length < 6) {
            this.showError('Password must be at least 6 characters');
            return;
        }

        try {
            const response = await api.request('/admin/reset-password.php', {
                method: 'POST',
                body: {
                    account_id: accountId,
                    new_password: newPassword
                }
            });

            if (response.success) {
                this.showSuccess(response.message);
            }
        } catch (error) {
            console.error('Failed to reset password:', error);
            this.showError(error.message || 'Failed to reset password');
        }
    };

    MarketplaceApp.prototype.adminCancelListing = async function(listingId, itemName) {
        if (!confirm('Cancel listing for "' + itemName + '"? The item will be returned to the seller via parcel.')) {
            return;
        }

        try {
            const response = await api.request('/admin/cancel-listing.php', {
                method: 'POST',
                body: { listing_id: listingId }
            });

            if (response.success) {
                this.showSuccess(response.message || 'Listing cancelled successfully');
                this.loadAdminListings();
            }
        } catch (error) {
            console.error('Failed to cancel listing:', error);
            this.showError(error.message || 'Failed to cancel listing');
        }
    };

    MarketplaceApp.prototype.adminDeleteListing = async function(listingId, itemName) {
        if (!confirm('Delete listing for "' + itemName + '"?')) {
            return;
        }

        try {
            const response = await api.request('/admin/delete-listing.php', {
                method: 'POST',
                body: { listing_id: listingId }
            });

            if (response.success) {
                this.showSuccess('Listing deleted successfully');
                this.loadAdminListings();
            }
        } catch (error) {
            console.error('Failed to delete listing:', error);
            this.showError(error.message || 'Failed to delete listing');
        }
    };

    MarketplaceApp.prototype.adminRestoreListing = async function(listingId, itemName, buyerName, paymentStatus) {
        const buyerInfo = buyerName ? ` purchased by ${buyerName}` : '';
        const paymentInfo = paymentStatus ? ` (Payment: ${paymentStatus})` : '';

        if (!confirm(`Restore listing for "${itemName}"${buyerInfo}${paymentInfo}? This will cancel the purchase and make the item available again.`)) {
            return;
        }

        try {
            const response = await api.request('/admin/restore-listing.php', {
                method: 'POST',
                body: { listing_id: listingId }
            });

            if (response.success) {
                this.showSuccess(response.message || 'Listing restored successfully');
                this.loadAdminListings();
            }
        } catch (error) {
            console.error('Failed to restore listing:', error);
            this.showError(error.message || 'Failed to restore listing');
        }
    };

    MarketplaceApp.prototype.adminDeleteWTB = async function(wtbId, itemName) {
        if (!confirm('Delete WTB order for "' + itemName + '"?')) {
            return;
        }

        try {
            const response = await api.request('/admin/delete-wtb.php', {
                method: 'POST',
                body: { wtb_id: wtbId }
            });

            if (response.success) {
                this.showSuccess('WTB order deleted successfully');
                this.loadAdminWTB();
            }
        } catch (error) {
            console.error('Failed to delete WTB:', error);
            this.showError(error.message || 'Failed to delete WTB order');
        }
    };

    // Add event listeners for admin section
    MarketplaceApp.prototype.attachAdminEventListeners = function() {
        // Admin tab switching
        document.querySelectorAll('.admin-tabs .tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.switchAdminTab(btn.dataset.tab);
            });
        });

        // Admin search/refresh buttons
        const adminListingRefresh = document.getElementById('admin-refresh-listings');
        if (adminListingRefresh) {
            adminListingRefresh.addEventListener('click', () => this.loadAdminListings());
        }

        const adminWTBRefresh = document.getElementById('admin-refresh-wtb');
        if (adminWTBRefresh) {
            adminWTBRefresh.addEventListener('click', () => this.loadAdminWTB());
        }

        const adminUsersRefresh = document.getElementById('admin-refresh-users');
        if (adminUsersRefresh) {
            adminUsersRefresh.addEventListener('click', () => this.loadAdminUsers());
        }

        const adminListingSearch = document.getElementById('admin-listing-search');
        if (adminListingSearch) {
            adminListingSearch.addEventListener('keyup', (e) => {
                if (e.key === 'Enter') {
                    this.loadAdminListings();
                }
            });
        }

        const adminWTBSearch = document.getElementById('admin-wtb-search');
        if (adminWTBSearch) {
            adminWTBSearch.addEventListener('keyup', (e) => {
                if (e.key === 'Enter') {
                    this.loadAdminWTB();
                }
            });
        }

        const adminUserSearch = document.getElementById('admin-user-search');
        if (adminUserSearch) {
            adminUserSearch.addEventListener('keyup', (e) => {
                if (e.key === 'Enter') {
                    this.loadAdminUsers();
                }
            });
        }
    };

    // Override navigateToPage to handle admin page
    const originalNavigateToPageAdmin = MarketplaceApp.prototype.navigateToPage;
    MarketplaceApp.prototype.navigateToPage = function(pageName) {
        if (originalNavigateToPageAdmin) {
            originalNavigateToPageAdmin.call(this, pageName);
        }

        if (pageName === 'admin') {
            this.loadAdminPage();
        }
    };

    // Override updateUserInterface to show/hide admin nav
    const originalUpdateUserInterfaceAdmin = MarketplaceApp.prototype.updateUserInterface;
    MarketplaceApp.prototype.updateUserInterface = function() {
        if (originalUpdateUserInterfaceAdmin) {
            originalUpdateUserInterfaceAdmin.call(this);
        }

        this.showAdminNav();
    };

    // Override init to include admin event listeners
    const originalInitAdmin = MarketplaceApp.prototype.init;
    MarketplaceApp.prototype.init = function() {
        if (originalInitAdmin) {
            originalInitAdmin.call(this);
        }

        this.attachAdminEventListeners();
        this.showAdminNav();
    };

}
