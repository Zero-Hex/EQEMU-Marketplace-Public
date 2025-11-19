/**
 * Marketplace Linked Accounts Management
 * Handles linking multiple game accounts to one marketplace profile
 */

(function() {
    'use strict';

    // ===================================================================
    // Load Linked Accounts
    // ===================================================================

    MarketplaceApp.prototype.loadLinkedAccounts = async function() {
        const container = document.getElementById('linked-accounts-list');
        if (!container) return;

        container.innerHTML = '<div class="loading">Loading linked accounts...</div>';

        try {
            const response = await api.request('/accounts/linked.php');

            if (response.success && response.accounts) {
                this.displayLinkedAccounts(response.accounts, response.active_account_id);
                this.linkedAccounts = response.accounts; // Store for later use
                this.populateAccountFilter(response.accounts);
            } else {
                container.innerHTML = '<div class="no-data">No accounts found</div>';
            }
        } catch (error) {
            console.error('Error loading linked accounts:', error);
            container.innerHTML = '<div class="error">Failed to load linked accounts</div>';
        }
    };

    // ===================================================================
    // Display Linked Accounts
    // ===================================================================

    MarketplaceApp.prototype.displayLinkedAccounts = function(accounts, activeAccountId) {
        const container = document.getElementById('linked-accounts-list');
        if (!container || !accounts || accounts.length === 0) {
            container.innerHTML = '<div class="no-data">No accounts linked</div>';
            return;
        }

        container.innerHTML = '';

        accounts.forEach(account => {
            const accountCard = document.createElement('div');
            accountCard.className = 'account-card' + (account.is_active ? ' active-account' : '');

            const primaryBadge = account.is_primary ? '<span class="badge badge-primary">Primary</span>' : '';
            const activeBadge = account.is_active ? '<span class="badge badge-success">Active</span>' : '';
            const linkedDate = account.linked_date ?
                `<p class="account-meta">Linked: ${new Date(account.linked_date).toLocaleDateString()}</p>` : '';
            const validatedChar = account.validated_character ?
                `<p class="account-meta">Validated with: ${this.escapeHtml(account.validated_character)}</p>` : '';

            accountCard.innerHTML = `
                <div class="account-header">
                    <h4 class="account-name">${this.escapeHtml(account.account_name)}</h4>
                    <div class="account-badges">
                        ${primaryBadge}
                        ${activeBadge}
                    </div>
                </div>
                <div class="account-details">
                    ${linkedDate}
                    ${validatedChar}
                </div>
                <div class="account-actions">
                    ${!account.is_active ?
                        `<button class="btn btn-sm btn-primary btn-switch-account" data-account-id="${account.account_id}" data-account-name="${this.escapeHtml(account.account_name)}">Switch to This Account</button>` :
                        '<span class="text-muted">Currently Active</span>'}
                </div>
            `;

            container.appendChild(accountCard);

            // Attach event listener to switch button if present
            const switchBtn = accountCard.querySelector('.btn-switch-account');
            if (switchBtn) {
                switchBtn.addEventListener('click', (e) => {
                    const accountId = parseInt(e.target.dataset.accountId);
                    const accountName = e.target.dataset.accountName;
                    this.switchAccount(accountId, accountName);
                });
            }
        });
    };

    // ===================================================================
    // Switch Active Account
    // ===================================================================

    MarketplaceApp.prototype.switchAccount = async function(accountId, accountName) {
        try {
            const response = await api.request('/accounts/switch.php', {
                method: 'POST',
                body: JSON.stringify({ account_id: accountId })
            });

            if (response.success) {
                this.showMessage(`Switched to account: ${accountName}`, 'success');

                // Refresh characters for the new active account
                await api.refreshCharacters();

                // Reload the accounts list to update active status
                await this.loadLinkedAccounts();

                // Reload any data that depends on the active account
                await this.reloadAccountDependentData();
            } else {
                this.showMessage(response.error || 'Failed to switch account', 'error');
            }
        } catch (error) {
            console.error('Error switching account:', error);
            this.showMessage('Failed to switch account', 'error');
        }
    };

    // ===================================================================
    // Reload Data After Account Switch
    // ===================================================================

    MarketplaceApp.prototype.reloadAccountDependentData = async function() {
        // Reload various sections that might depend on the active account
        const currentPage = document.querySelector('.page-content:not(.hidden)');
        if (!currentPage) return;

        const pageId = currentPage.id;
        if (pageId === 'my-listings-page') {
            await this.loadMyListings();
        } else if (pageId === 'my-purchases-page') {
            await this.loadMyPurchases();
        } else if (pageId === 'my-earnings-page') {
            await this.loadEarnings();
        } else if (pageId === 'my-accounts-page') {
            // Reload purchases list on accounts page
            const accountFilterSelect = document.getElementById('account-filter-select');
            const accountId = accountFilterSelect ? accountFilterSelect.value : null;
            await this.loadPurchasesByAccount(accountId || null);
        } else if (pageId === 'want-to-buy-page') {
            // Reload WTB page to refresh character dropdowns
            if (typeof this.loadMyWTB === 'function') {
                await this.loadMyWTB();
            }
        }
    };

    // ===================================================================
    // Link New Account
    // ===================================================================

    MarketplaceApp.prototype.linkAccount = async function(accountName, characterId) {
        try {
            const response = await api.request('/accounts/link.php', {
                method: 'POST',
                body: JSON.stringify({
                    account_name: accountName,
                    character_id: characterId
                })
            });

            if (response.success) {
                this.showMessage(response.message || 'Account linked successfully!', 'success');
                // Close modal
                document.getElementById('link-account-modal').classList.add('hidden');
                // Reset form
                document.getElementById('link-account-form').reset();
                // Reload accounts list
                await this.loadLinkedAccounts();
            } else {
                this.showMessage(response.error || 'Failed to link account', 'error');
            }
        } catch (error) {
            console.error('Error linking account:', error);
            this.showMessage('Failed to link account', 'error');
        }
    };

    // ===================================================================
    // Populate Account Filter
    // ===================================================================

    MarketplaceApp.prototype.populateAccountFilter = function(accounts) {
        const select = document.getElementById('account-filter-select');
        if (!select) return;

        // Clear existing options except "All Accounts"
        select.innerHTML = '<option value="">All Accounts</option>';

        accounts.forEach(account => {
            const option = document.createElement('option');
            option.value = account.account_id;
            option.textContent = account.account_name + (account.is_primary ? ' (Primary)' : '');
            select.appendChild(option);
        });
    };

    // ===================================================================
    // Load Purchases by Account
    // ===================================================================

    MarketplaceApp.prototype.loadPurchasesByAccount = async function(accountId) {
        const container = document.getElementById('account-purchases-list');
        if (!container) return;

        container.innerHTML = '<div class="loading">Loading purchases...</div>';

        try {
            const endpoint = accountId ?
                `/purchases/history.php?account_id=${accountId}` :
                '/purchases/history.php';

            const response = await api.request(endpoint);

            if (response.success && response.purchases) {
                this.displayAccountPurchases(response.purchases);
            } else {
                container.innerHTML = '<div class="no-data">No purchases found</div>';
            }
        } catch (error) {
            console.error('Error loading purchases:', error);
            container.innerHTML = '<div class="error">Failed to load purchases</div>';
        }
    };

    // ===================================================================
    // Display Account Purchases
    // ===================================================================

    MarketplaceApp.prototype.displayAccountPurchases = function(purchases) {
        const container = document.getElementById('account-purchases-list');
        if (!container || !purchases || purchases.length === 0) {
            container.innerHTML = '<div class="no-data">No purchases found</div>';
            return;
        }

        container.innerHTML = '';

        purchases.forEach(purchase => {
            const purchaseCard = document.createElement('div');
            purchaseCard.className = 'purchase-card';

            const priceInPP = this.formatPrice(purchase.price_copper);
            const purchaseDate = new Date(purchase.transaction_date).toLocaleDateString();

            const accountInfo = purchase.buyer_account_id && this.linkedAccounts ?
                this.linkedAccounts.find(a => a.account_id === purchase.buyer_account_id) : null;
            const accountName = accountInfo ? this.escapeHtml(accountInfo.account_name) : '';

            purchaseCard.innerHTML = `
                <div class="purchase-header">
                    <h4 class="item-name">${this.escapeHtml(purchase.item_name)}</h4>
                    ${accountName ? `<span class="badge badge-info">${accountName}</span>` : ''}
                </div>
                <div class="purchase-details">
                    <p><strong>Seller:</strong> ${this.escapeHtml(purchase.seller_name)}</p>
                    <p><strong>Buyer:</strong> ${this.escapeHtml(purchase.buyer_name)}</p>
                    <p><strong>Price:</strong> ${priceInPP} pp</p>
                    <p><strong>Date:</strong> ${purchaseDate}</p>
                </div>
            `;

            container.appendChild(purchaseCard);
        });
    };

    // ===================================================================
    // Event Listeners
    // ===================================================================

    MarketplaceApp.prototype.initAccountsEventListeners = function() {
        // Prevent duplicate initialization
        if (this._accountsEventListenersInitialized) {
            return;
        }
        this._accountsEventListenersInitialized = true;

        // Link Account Button
        const linkAccountBtn = document.getElementById('link-account-btn');
        if (linkAccountBtn) {
            linkAccountBtn.addEventListener('click', () => {
                document.getElementById('link-account-modal').classList.remove('hidden');
            });
        }

        // Close Link Account Modal
        const closeLinkAccount = document.getElementById('close-link-account');
        if (closeLinkAccount) {
            closeLinkAccount.addEventListener('click', () => {
                document.getElementById('link-account-modal').classList.add('hidden');
            });
        }

        // Cancel Link Account
        const cancelLinkAccount = document.getElementById('cancel-link-account');
        if (cancelLinkAccount) {
            cancelLinkAccount.addEventListener('click', () => {
                document.getElementById('link-account-modal').classList.add('hidden');
            });
        }

        // Link Account Form Submit
        const linkAccountForm = document.getElementById('link-account-form');
        if (linkAccountForm) {
            linkAccountForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const accountName = document.getElementById('link-account-name').value.trim();
                const characterId = parseInt(document.getElementById('link-character-id').value);

                if (accountName && characterId) {
                    await this.linkAccount(accountName, characterId);
                }
            });
        }

        // Account Filter Change
        const accountFilterSelect = document.getElementById('account-filter-select');
        if (accountFilterSelect) {
            accountFilterSelect.addEventListener('change', () => {
                const accountId = accountFilterSelect.value;
                this.loadPurchasesByAccount(accountId || null);
            });
        }

        // Refresh Account Purchases
        const refreshBtn = document.getElementById('refresh-account-purchases');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                const accountId = document.getElementById('account-filter-select').value;
                this.loadPurchasesByAccount(accountId || null);
            });
        }
    };

    // ===================================================================
    // Load My Accounts Page
    // ===================================================================

    MarketplaceApp.prototype.loadMyAccountsPage = async function() {
        // Initialize event listeners for this page
        this.initAccountsEventListeners();

        await this.loadLinkedAccounts();

        // Show purchases section
        const purchasesSection = document.getElementById('account-purchases-section');
        if (purchasesSection) {
            purchasesSection.classList.remove('hidden');
            // Load all purchases initially
            await this.loadPurchasesByAccount(null);
        }
    };

})();
