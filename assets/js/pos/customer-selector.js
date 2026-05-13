/* =====================================================
   CUSTOMER SELECTOR MODULE
   Handles searching, selecting, and resetting buyers
   Uses Bootstrap Modal for search results
===================================================== */

const CustomerSelector = {
    modal: null,

    init() {
        // Elements
        this.els = {
            radioWalkin: document.getElementById('cust-walkin'),
            radioExisting: document.getElementById('cust-existing'),
            inputName: document.getElementById('buyer-name'),
            wrapperSearch: document.getElementById('buyer-search-wrapper'),
            btnSearchBuyer: document.getElementById('btn-search-buyer'),
            inputSearch: document.getElementById('buyer-search'),
            resultsBox: document.getElementById('buyer-results'),
            buyerIdInput: document.getElementById('buyer-id'),

            // Buyer card elements (matching simple_checkout.php HTML)
            cardProfile: document.getElementById('selected-buyer-card'),
            changeBuyerBtn: document.getElementById('btn-change-buyer'),

            // Profile Data Fields
            profName: document.getElementById('selected-buyer-name'),
            profShop: document.getElementById('selected-buyer-shop'),
            profTier: document.getElementById('selected-buyer-tier'),
            profPhone: document.getElementById('selected-buyer-contact'),
            profAddress: document.getElementById('selected-buyer-address')
        };

        // Safety Check
        if (!this.els.radioWalkin || !this.els.inputName) return;

        // Initialize Bootstrap Modal
        const modalEl = document.getElementById('buyerSearchModal');
        if (modalEl) {
            this.modal = new bootstrap.Modal(modalEl);

            // Focus search input when modal opens
            modalEl.addEventListener('shown.bs.modal', () => {
                this.els.inputSearch.focus();
                this.els.inputSearch.value = '';
                this.resetResultsPlaceholder();
            });

            // Clear search when modal closes
            modalEl.addEventListener('hidden.bs.modal', () => {
                this.els.inputSearch.value = '';
                this.resetResultsPlaceholder();
            });
        }

        this.bindEvents();
        this.bindChangeBuyer();

        // Initialize State — check if we have restored state from sessionStorage
        const hasRestoredCart = POS.cart && POS.cart.length > 0;
        const hasRestoredBuyer = window.POS_BUYER && window.POS_BUYER.buyer_id && !window.POS_BUYER.is_walkin;

        if (hasRestoredBuyer) {
            // Restore existing buyer UI without clearing cart
            this.els.radioExisting.checked = true;
            this.els.inputName.classList.add('d-none');
            this.els.wrapperSearch.classList.remove('d-none');

            // Fill buyer card
            this.els.profName.innerText = window.POS_BUYER.buyer_name || '';
            this.els.profShop.innerText = window.POS_BUYER.shop || window.POS_BUYER.shop_name || '—';
            this.els.profPhone.innerText = window.POS_BUYER.contact_number || '';
            this.els.profAddress.innerText = window.POS_BUYER.address || '';
            this.els.profTier.innerText = (window.POS_BUYER.price_tier || 'retail').toUpperCase();
            this.els.buyerIdInput.value = window.POS_BUYER.buyer_id;

            // Show buyer card, lock mode switch
            this.els.cardProfile.classList.remove('d-none');
            this.els.wrapperSearch.classList.add('d-none');
            this.els.radioWalkin.disabled = true;
            this.els.radioExisting.disabled = true;
        } else if (hasRestoredCart) {
            // Walk-in with items in cart — just set UI mode without clearing cart
            this.els.inputName.classList.remove('d-none');
            this.els.wrapperSearch.classList.add('d-none');
            this.els.radioWalkin.checked = true;
            // Restore walk-in name if available
            if (window.POS_BUYER && window.POS_BUYER.buyer_name) {
                this.els.inputName.value = window.POS_BUYER.buyer_name;
            }
        } else {
            // Fresh start — no restored data
            this.setMode('walkin');
        }
    },

    bindEvents() {
        // Mode Switching
        this.els.radioWalkin.addEventListener('change', () => this.setMode('walkin'));
        this.els.radioExisting.addEventListener('change', () => this.setMode('existing'));

        // Open Modal on button click
        if (this.els.btnSearchBuyer) {
            this.els.btnSearchBuyer.addEventListener('click', () => {
                if (this.modal) {
                    this.modal.show();
                }
            });
        }

        // Search Input Debounce
        let timer;
        this.els.inputSearch.addEventListener('input', (e) => {
            const val = e.target.value.trim();
            clearTimeout(timer);
            if (val.length < 2) {
                this.resetResultsPlaceholder();
                return;
            }
            timer = setTimeout(() => this.fetchBuyers(val), 300);
        });

        // Sync Walk-in Name
        this.els.inputName.addEventListener('input', (e) => {
            if (window.POS_BUYER && window.POS_BUYER.is_walkin) {
                window.POS_BUYER.buyer_name = e.target.value.trim() || 'Walk-in Customer';
            }
        });
    },

    setMode(mode) {
        if (mode === 'walkin') {
            this.resetToWalkin();
            this.els.inputName.classList.remove('d-none');
            this.els.wrapperSearch.classList.add('d-none');
        } else {
            this.els.inputName.classList.add('d-none');
            this.els.wrapperSearch.classList.remove('d-none');
        }
    },

    fetchBuyers(query) {
        // Show loading state
        this.els.resultsBox.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mb-0 small text-muted mt-2">Searching...</p>
            </div>
        `;

        fetch(`../../api/pos/search_buyer.php?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                const buyers = data.success ? data.data : [];
                this.renderResults(buyers, query);
            })
            .catch(err => {
                console.error(err);
                this.els.resultsBox.innerHTML = `
                    <div class="text-center text-danger py-4">
                        <i class="fa-solid fa-exclamation-circle fa-2x mb-2"></i>
                        <p class="mb-0 small">Error loading buyers</p>
                    </div>
                `;
            });
    },

    resetResultsPlaceholder() {
        this.els.resultsBox.innerHTML = `
            <div class="text-center text-muted py-4">
                <i class="fa-solid fa-users fa-2x mb-3 opacity-25"></i>
                <p class="mb-0 small">Start typing to search buyers</p>
            </div>
        `;
    },

    renderResults(buyers, query = '') {
        const box = this.els.resultsBox;
        box.innerHTML = '';

        if (!buyers || !buyers.length) {
            box.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="fa-solid fa-user-slash fa-2x mb-3 opacity-25"></i>
                    <p class="mb-0 small">No buyers found</p>
                </div>
            `;
            return;
        }

        const safeQuery = query ? query.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&').split(/\\s+/).filter(Boolean) : [];
        const highlight = (text) => {
            if (!text) return '';
            // Basic HTML escape
            const div = document.createElement('div');
            div.textContent = text;
            let hlText = div.innerHTML;

            if (safeQuery.length === 0) return hlText;
            safeQuery.forEach(q => {
                const regex = new RegExp(`(${q})`, 'gi');
                hlText = hlText.replace(regex, '<mark class="bg-warning bg-opacity-50 p-0 rounded text-dark">$1</mark>');
            });
            return hlText;
        };

        // Create buyer result items
        buyers.forEach(buyer => {
            const item = document.createElement('div');
            item.className = 'buyer-result-item';

            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="buyer-name">${highlight(buyer.buyer_name)}</div>
                        <div class="buyer-shop">${highlight(buyer.shop_name || 'Individual')}</div>
                        <div class="buyer-contact">${highlight(buyer.contact_number || '')}</div>
                    </div>
                    <span class="badge bg-info-subtle text-info-emphasis rounded-pill">
                        ${buyer.price_tier.toUpperCase()}
                    </span>
                </div>
            `;

            item.onclick = () => this.selectBuyer(buyer);
            box.appendChild(item);
        });
    },

    selectBuyer(buyer) {
        // 1. Update Global State
        window.POS_BUYER = {
            buyer_id: buyer.buyer_id,
            buyer_name: buyer.buyer_name,
            price_tier: buyer.price_tier || 'retail',
            shop: buyer.shop_name,
            contact_number: buyer.contact_number,
            address: buyer.address,
            wallet_balance: parseFloat(buyer.wallet_balance || 0),
            is_walkin: 0
        };

        // 2. Update UI Elements
        this.els.buyerIdInput.value = buyer.buyer_id;
        this.els.inputSearch.value = '';

        // Fill buyer card
        this.els.profName.innerText = buyer.buyer_name;
        this.els.profShop.innerText = buyer.shop_name || '—';
        this.els.profPhone.innerText = buyer.contact_number || '';
        this.els.profAddress.innerText = buyer.address || '';
        this.els.profTier.innerText = buyer.price_tier.toUpperCase();

        const walletDisplay = document.getElementById('buyer-wallet-display');
        const walletBalance = document.getElementById('buyer-wallet-balance');
        if (walletDisplay && walletBalance) {
            walletBalance.innerText = '₱' + parseFloat(buyer.wallet_balance || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
            walletDisplay.classList.remove('d-none');
        }

        // Hide search wrapper, show buyer card
        this.els.wrapperSearch.classList.add('d-none');
        this.els.cardProfile.classList.remove('d-none');

        // Lock mode switch
        this.els.radioWalkin.disabled = true;
        this.els.radioExisting.disabled = true;

        // Reflect name for receipt preview
        if (this.els.inputName) {
            this.els.inputName.value = buyer.buyer_name;
        }

        // 3. Close Modal
        if (this.modal) {
            this.modal.hide();
        }

        // 4. Trigger Price Recalculation (requires cart-manager.js to be loaded)
        if (window.CartManager && window.CartManager.recalculateCart) {
            window.CartManager.recalculateCart();
            window.CartManager.updateChange();
        }
        if (window.WalletSupplement) WalletSupplement.refresh();
        this.updatePaymentMethods(buyer);

        // 5. Persist buyer selection to sessionStorage
        if (typeof POS !== 'undefined' && POS.saveState) POS.saveState();

        // 6. Fetch purchase history stats (last 30 days)
        this.fetchBuyerHistory(buyer.buyer_id);
    },

    // Fetch and display buyer purchase history summary
    async fetchBuyerHistory(buyerId) {
        const statsEl = document.getElementById('buyer-history-stats');
        const textEl = document.getElementById('buyer-history-text');
        if (!statsEl || !textEl) return;

        // Show loading state
        textEl.textContent = 'Loading history...';
        statsEl.classList.remove('d-none');

        try {
            const res = await fetch(`../../api/buyers/get_buyer_sales.php?buyer_id=${buyerId}`);
            const data = await res.json();
            if (data.success && data.stats) {
                const count = data.stats.count || 0;
                const total = parseFloat(data.stats.total || 0);
                if (count > 0) {
                    textEl.textContent = `${count} order${count > 1 ? 's' : ''} · ₱${total.toLocaleString(undefined, { minimumFractionDigits: 2 })} total (last 30 days)`;
                } else {
                    textEl.textContent = 'No purchase history (last 30 days)';
                }
                statsEl.classList.remove('d-none');
            } else {
                statsEl.classList.add('d-none');
            }
        } catch (e) {
            console.error('Buyer history fetch error:', e);
            statsEl.classList.add('d-none');
        }
    },

    resetToWalkin() {
        // Reset State
        window.POS_BUYER = {
            buyer_id: null,
            buyer_name: 'Walk-in',
            price_tier: 'retail',
            wallet_balance: 0,
            is_walkin: 1
        };

        // Reset UI
        this.els.cardProfile.classList.add('d-none');
        this.els.wrapperSearch.classList.remove('d-none');

        this.els.inputSearch.value = '';
        this.resetResultsPlaceholder();
        this.els.buyerIdInput.value = '';

        this.els.radioWalkin.disabled = false;
        this.els.radioExisting.disabled = false;
        this.els.radioWalkin.checked = true;

        // Hide buyer history stats
        const statsEl = document.getElementById('buyer-history-stats');
        if (statsEl) statsEl.classList.add('d-none');

        const walletDisplay = document.getElementById('buyer-wallet-display');
        if (walletDisplay) walletDisplay.classList.add('d-none');

        // Clear cart and persisted state when resetting buyer
        POS.cart = [];
        if (typeof POS.clearState === 'function') POS.clearState();
        if (window.CartManager && window.CartManager.renderCart) {
            window.CartManager.renderCart();
            window.CartManager.updateChange();
        }
        if (window.WalletSupplement) WalletSupplement.refresh();
        this.updatePaymentMethods({ is_walkin: 1 });
    },

    // Show/Hide wallet payment method
    updatePaymentMethods(buyer) {
        const walletOption = document.querySelector('#payment-method option[value="wallet"]');
        const walletHint = document.getElementById('wallet-hint');
        if (!walletOption) return;

        const isExisting = buyer && buyer.buyer_id && !buyer.is_walkin;
        if (isExisting) {
            walletOption.classList.remove('d-none');
            walletOption.disabled = false;
            if (walletHint) walletHint.classList.add('d-none');
        } else {
            walletOption.classList.add('d-none');
            walletOption.disabled = true;
            if (walletHint) walletHint.classList.remove('d-none');

            // If currently selected, reset to cash
            const select = document.getElementById('payment-method');
            if (select && select.value === 'wallet') {
                select.value = 'cash';
                if (typeof PaymentMethodHandler !== 'undefined') PaymentMethodHandler.handleMethodChange();
            }
        }
    },

    // Bind Change Buyer button
    bindChangeBuyer() {
        if (!this.els.changeBuyerBtn) return;

        this.els.changeBuyerBtn.addEventListener('click', () => {
            this.els.cardProfile.classList.add('d-none');
            this.els.wrapperSearch.classList.remove('d-none');

            this.els.buyerIdInput.value = '';
            this.els.inputSearch.value = '';
            this.resetResultsPlaceholder();

            this.unlockModeSwitch();
            window.POS_BUYER = {
                buyer_id: null,
                buyer_name: 'Walk-in',
                price_tier: 'retail',
                is_walkin: 1
            };

            // Hide buyer history stats
            const statsEl = document.getElementById('buyer-history-stats');
            if (statsEl) statsEl.classList.add('d-none');

            const walletDisplay = document.getElementById('buyer-wallet-display');
            if (walletDisplay) walletDisplay.classList.add('d-none');

            // Clear cart and persisted state
            POS.cart = [];
            if (typeof POS.clearState === 'function') POS.clearState();
            if (window.CartManager && window.CartManager.renderCart) {
                window.CartManager.renderCart();
            }
        });
    },

    lockModeSwitch() {
        this.els.radioWalkin.disabled = true;
        this.els.radioExisting.disabled = true;
    },

    unlockModeSwitch() {
        this.els.radioWalkin.disabled = false;
        this.els.radioExisting.disabled = false;
    },

    // Called by the "X" button on the profile card
    reset() {
        this.resetToWalkin();
        this.setMode('existing'); // Keep them in search mode
    }
};
