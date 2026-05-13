/* =====================================================
   POS INITIALIZATION
   Initialize all modules and expose public API
===================================================== */

// Expose public methods for HTML attributes (onclick="CartManager.xyz")
window.SimpleCheckout = {
    // Cart operations
    addToCart: (...args) => CartManager.addToCart(...args),
    updateQty: (...args) => CartManager.updateQty(...args),
    setQty: (...args) => CartManager.setQty(...args),
    removeItem: (...args) => CartManager.removeItem(...args),
    clearCart: () => CartManager.clearCart(),
    editPrice: (...args) => CartManager.editPrice(...args),
    recalculateCart: () => CartManager.recalculateCart(),

    // Receipt & Payment
    previewReceipt: () => ReceiptFunctions.previewReceipt(),
    updatePreviewFormat: () => ReceiptFunctions.updatePreviewFormat(),
    processPayment: () => ReceiptFunctions.processPayment(),

    // Customer
    resetBuyer: () => {
        if (typeof CustomerSelector !== 'undefined') CustomerSelector.resetToWalkin();
    },

    // ===== DISCOUNT METHODS =====
    globalDiscount: { type: 'percent', value: 0 },

    openDiscountModal() {
        // Populate brand dropdown with brands from current cart
        const brands = CartManager.getCartBrands();
        const select = document.getElementById('brand-discount-select');
        if (select) {
            select.innerHTML = '<option value="">-- Select Brand --</option>';
            brands.forEach(b => {
                const existing = POS.brandDiscounts[b];
                const label = existing ? `${b} (${existing.value}${existing.type === 'percent' ? '%' : '₱'} applied)` : b;
                select.innerHTML += `<option value="${b}">${label}</option>`;
            });
        }

        // Show active brand discounts list
        this.renderActiveBrandDiscounts();

        // Reset total discount fields to current values
        const discValInput = document.getElementById('discount-value');
        if (discValInput) discValInput.value = this.globalDiscount.value || '';

        // Set the correct radio
        const typeRadio = document.querySelector(`input[name="discount-type"][value="${this.globalDiscount.type}"]`);
        if (typeRadio) typeRadio.checked = true;

        // Ensure Items list is fresh (for the Items tab)
        this.populateItemDiscountList();

        const modalEl = document.getElementById('discountModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    },

    // Refresh brand discount UI elements in-place (no modal re-open)
    refreshBrandDiscountUI() {
        const brands = CartManager.getCartBrands();
        const select = document.getElementById('brand-discount-select');
        if (select) {
            select.innerHTML = '<option value="">-- Select Brand --</option>';
            brands.forEach(b => {
                const existing = POS.brandDiscounts[b];
                const label = existing ? `${b} (${existing.value}${existing.type === 'percent' ? '%' : '₱'} applied)` : b;
                select.innerHTML += `<option value="${b}">${label}</option>`;
            });
        }
        this.renderActiveBrandDiscounts();
    },

    renderActiveBrandDiscounts() {
        const container = document.getElementById('active-brand-discounts');
        if (!container) return;

        const entries = Object.entries(POS.brandDiscounts || {});
        if (entries.length === 0) {
            container.innerHTML = '<div class="text-muted small text-center py-2">No brand discounts applied</div>';
            return;
        }

        container.innerHTML = entries.map(([brand, rule]) => `
            <div class="d-flex justify-content-between align-items-center py-1 px-2 mb-1 bg-light rounded">
                <span class="fw-bold small">${brand}</span>
                <span class="badge bg-danger">${rule.value}${rule.type === 'percent' ? '%' : '₱'} OFF</span>
                <button class="btn btn-sm btn-outline-danger px-1 py-0" onclick="SimpleCheckout.removeBrandDiscount('${brand}')">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        `).join('');
    },

    applyBrandDiscount() {
        const select = document.getElementById('brand-discount-select');
        const typeEl = document.querySelector('input[name="brand-disc-type"]:checked');
        const valueEl = document.getElementById('brand-discount-value');

        const brand = select?.value;
        const type = typeEl?.value || 'percent';
        const value = parseFloat(valueEl?.value || 0);

        if (!brand) { EllaToast.warning('Please select a brand'); return; }
        if (value <= 0) { EllaToast.warning('Please enter a discount value'); return; }

        CartManager.applyBrandDiscount(brand, type, value);

        // Refresh the modal UI in-place (don't re-open)
        this.refreshBrandDiscountUI();

        // Clear the value input
        if (valueEl) valueEl.value = '';
    },

    removeBrandDiscount(brand) {
        CartManager.removeBrandDiscount(brand);
        this.renderActiveBrandDiscounts();

        // Refresh brand dropdown
        const brands = CartManager.getCartBrands();
        const select = document.getElementById('brand-discount-select');
        if (select) {
            select.innerHTML = '<option value="">-- Select Brand --</option>';
            brands.forEach(b => {
                const existing = POS.brandDiscounts[b];
                const label = existing ? `${b} (${existing.value}${existing.type === 'percent' ? '%' : '₱'} applied)` : b;
                select.innerHTML += `<option value="${b}">${label}</option>`;
            });
        }
    },

    applyDiscount() {
        const typeEl = document.querySelector('input[name="discount-type"]:checked');
        const valueEl = document.getElementById('discount-value');

        const type = typeEl?.value || 'percent';
        const value = parseFloat(valueEl?.value || 0);

        if (value <= 0) { EllaToast.warning('Please enter a discount value'); return; }
        if (type === 'percent' && value > 100) { EllaToast.warning('Percent cannot exceed 100'); return; }

        this.globalDiscount = { type, value };
        CartManager.renderCart();

        bootstrap.Modal.getInstance(document.getElementById('discountModal'))?.hide();
    },

    removeDiscount(silent = false) {
        this.globalDiscount = { type: 'percent', value: 0 };
        POS.brandDiscounts = {};

        // Clear manual discounts from all items
        if (POS.cart && POS.cart.length > 0) {
            POS.cart.forEach(item => {
                delete item.manual_discount;
                delete item.manual_discount_type;
            });
        }

        CartManager.recalculateCart();

        if (!silent) {
            const modalEl = document.getElementById('discountModal');
            if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();
        }
    },


    // ===== MULTI-ITEM SELECTION DISCOUNT =====
    populateItemDiscountList() {
        const container = document.getElementById('items-disc-list');
        if (!container) return;

        const cart = POS.cart || [];
        if (cart.length === 0) {
            container.innerHTML = '<div class="text-muted small text-center py-3">Cart is empty</div>';
            return;
        }

        container.innerHTML = cart.map((item, idx) => {
            const hasDisc = item.item_discount > 0;
            const discBadge = hasDisc
                ? `<span class="badge bg-danger ms-1" style="font-size:9px;">${POS.config.currency}${item.item_discount.toFixed(2)} OFF</span>`
                : '';
            return `
                <div class="form-check py-1 border-bottom d-flex align-items-center">
                    <input class="form-check-input me-2 items-disc-check" type="checkbox"
                        value="${idx}" id="items-disc-chk-${idx}">
                    <label class="form-check-label small flex-grow-1" for="items-disc-chk-${idx}" style="cursor:pointer;">
                        <span class="fw-bold">${item.name}</span>
                        ${item.brand ? `<span class="text-muted"> · ${item.brand}</span>` : ''}
                        ${item.variation ? `<span class="text-secondary"> (${item.variation})</span>` : ''}
                        <br>
                        <span class="text-muted" style="font-size:10px;">Qty: ${item.qty} × ${POS.config.currency}${item.price.toFixed(2)}</span>
                        ${discBadge}
                    </label>
                </div>`;
        }).join('');
    },

    toggleAllItemDisc() {
        const checks = document.querySelectorAll('.items-disc-check');
        const allChecked = Array.from(checks).every(c => c.checked);
        checks.forEach(c => c.checked = !allChecked);

        const btn = document.getElementById('items-disc-toggle-all');
        if (btn) btn.textContent = allChecked ? 'Select All' : 'Deselect All';
    },

    applyItemsDiscount() {
        const checks = document.querySelectorAll('.items-disc-check:checked');
        if (checks.length === 0) {
            EllaToast.warning('Please select at least one item');
            return;
        }

        const typeEl = document.querySelector('input[name="items-disc-type"]:checked');
        const valueEl = document.getElementById('items-disc-value');
        const type = typeEl?.value || 'percent';
        const value = parseFloat(valueEl?.value || 0);

        if (value <= 0) { EllaToast.warning('Please enter a discount value'); return; }
        if (type === 'percent' && value > 100) { EllaToast.warning('Percent cannot exceed 100'); return; }

        let count = 0;
        checks.forEach(chk => {
            const idx = parseInt(chk.value);
            const item = POS.cart[idx];
            if (!item) return;

            item.manual_discount = value;
            item.manual_discount_type = type;

            const pricing = CartManager.getDiscountedPrice(item);
            item.price = pricing.price;
            item.original_price = pricing.original_price;
            item.item_discount = pricing.discount;
            count++;
        });

        CartManager.renderCart();
        EllaToast.success(`Discount applied to ${count} item${count > 1 ? 's' : ''}`);
        bootstrap.Modal.getInstance(document.getElementById('discountModal'))?.hide();
    }
};

// Restore global discount from sessionStorage if available
(function () {
    const saved = sessionStorage.getItem('pos_global_discount');
    if (saved) {
        try {
            SimpleCheckout.globalDiscount = JSON.parse(saved);
        } catch (e) { }
    }
})();

// Initialize POS system when DOM is ready
document.addEventListener('DOMContentLoaded', async () => {
    console.log('🚀 Initializing POS System...');

    // Initialize per-receipt discount storage (only if not restored from session)
    if (!POS.brandDiscounts || Object.keys(POS.brandDiscounts).length === 0) {
        POS.brandDiscounts = {};
    }

    // Initialize all modules with error protection
    const initModules = () => {
        try { CustomerSelector.init(); } catch (e) { console.error('Error init CustomerSelector:', e); }
        try { ProductSearch.init(); } catch (e) { console.error('Error init ProductSearch:', e); }
        try { ScannerMode.init(); } catch (e) { console.error('Error init ScannerMode:', e); }
        try { PaymentMethodHandler.init(); } catch (e) { console.error('Error init PaymentMethodHandler:', e); }
        try { if (window.OfflineQueue) OfflineQueue.init(); } catch (e) { console.error('Error init OfflineQueue:', e); }
        try { SessionGuard.init(); } catch (e) { console.error('Error init SessionGuard:', e); }
    };

    initModules();

    // === RECOVERY LOGIC ===
    // Priority 1: URL Parameters (handled in view script, but we check here to avoid double-prompts)
    const urlParams = new URLSearchParams(window.location.search);
    const hasUrlRecovery = urlParams.get('recover') || urlParams.get('draft_id');

    // Priority 2: Crash Recovery (only if not recovering from URL)
    if (!hasUrlRecovery && POS.cart.length === 0 && POS.hasLocalBackup()) {
        try {
            await POS.restoreFromLocal();
        } catch (e) {
            console.error('Crash recovery failed:', e);
        }
    }

    // Initialize Network Guard
    NetworkGuard.init();

    // Initial cart render
    CartManager.renderCart();

    // Populate items discount list when Items tab is shown
    const itemsDiscTab = document.getElementById('tab-items-disc-btn');
    if (itemsDiscTab) {
        itemsDiscTab.addEventListener('shown.bs.tab', () => {
            SimpleCheckout.populateItemDiscountList();
        });
    }

    // Bind change calculation
    const tendered = document.getElementById('amount-tendered');
    if (tendered) {
        tendered.addEventListener('input', () => CartManager.updateChange());
    }

    // =====================================================
    // KEYBOARD SHORTCUTS
    // =====================================================
    document.addEventListener('keydown', (e) => {
        const tag = document.activeElement?.tagName;
        const inInput = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';

        // Alt + Shift + Z — Undo last cart action
        if (e.altKey && e.shiftKey && e.key.toLowerCase() === 'z') {
            if (inInput) return;
            e.preventDefault();
            e.stopPropagation();
            if (window.CartManager) CartManager.undoLastAction();
            return;
        }

        // Escape — Clear cart filter (if filter is focused or active)
        if (e.key === 'Escape') {
            const filterInput = document.getElementById('cart-filter-input');
            if (filterInput && (document.activeElement === filterInput || filterInput.value)) {
                e.preventDefault();
                e.stopPropagation();
                if (window.CartManager) CartManager.clearCartFilter();
                filterInput.blur();
            }
            return;
        }

        // Alt + Shift + F — Focus cart filter input
        if (e.altKey && e.shiftKey && e.key.toLowerCase() === 'f') {
            e.preventDefault();
            e.stopPropagation();
            const filterInput = document.getElementById('cart-filter-input');
            if (filterInput && !filterInput.closest('.d-none')) {
                filterInput.focus();
                filterInput.select();
            }
            return;
        }

        // Alt + Shift + S — Focus product search
        if (e.altKey && e.shiftKey && e.key.toLowerCase() === 's') {
            e.preventDefault();
            e.stopPropagation();
            const searchInput = document.getElementById('product-search') ||
                document.getElementById('product-search-input') ||
                document.querySelector('.product-search input');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
            return;
        }

        // Alt + Shift + E — Set tendered to EXACT grand total
        if (e.altKey && e.shiftKey && e.key.toLowerCase() === 'e') {
            if (inInput) return;
            e.preventDefault();
            e.stopPropagation();
            if (window.QuickCash) QuickCash.exact();
            return;
        }

        // Alt + Shift + P — Process payment (Pay)
        if (e.altKey && e.shiftKey && e.key.toLowerCase() === 'p') {
            if (inInput) return;
            e.preventDefault();
            e.stopPropagation();
            if (window.ReceiptFunctions) ReceiptFunctions.processPayment();
            return;
        }

        // Alt + Shift + H — Hold current cart
        if (e.altKey && e.shiftKey && e.key.toLowerCase() === 'h') {
            if (inInput) return;
            e.preventDefault();
            e.stopPropagation();
            if (window.HoldCart) HoldCart.hold();
            return;
        }

        // Alt + Shift + B — Save Draft (Backup)
        if (e.altKey && e.shiftKey && e.key.toLowerCase() === 'b') {
            if (inInput) return;
            e.preventDefault();
            e.stopPropagation();
            if (window.DraftUI) DraftUI.saveDraft();
            return;
        }

        // Alt + Shift + L — Load Drafts
        if (e.altKey && e.shiftKey && e.key.toLowerCase() === 'l') {
            if (inInput) return;
            e.preventDefault();
            e.stopPropagation();
            if (window.DraftUI) DraftUI.loadDraft();
            return;
        }

        // ===== CART KEYBOARD NAVIGATION =====
        // Alt + ArrowUp/ArrowDown — Navigate cart rows
        if (e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown') && !inInput) {
            e.preventDefault();
            const rows = document.querySelectorAll('tr[data-cart-index]');
            if (rows.length === 0) return;

            const currentFocused = document.querySelector('tr.cart-row-focused');
            let currentIdx = currentFocused ? parseInt(currentFocused.dataset.cartIndex) : -1;

            if (e.key === 'ArrowDown') {
                currentIdx = currentIdx < POS.cart.length - 1 ? currentIdx + 1 : 0;
            } else {
                currentIdx = currentIdx > 0 ? currentIdx - 1 : POS.cart.length - 1;
            }

            // Remove old focus
            rows.forEach(r => r.classList.remove('cart-row-focused'));
            // Add new focus
            const targetRow = document.querySelector(`tr[data-cart-index="${currentIdx}"]`);
            if (targetRow) {
                targetRow.classList.add('cart-row-focused');
                targetRow.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
            return;
        }

        // Alt + Enter — Open qty pad for focused cart row
        if (e.altKey && e.key === 'Enter' && !inInput) {
            const focusedRow = document.querySelector('tr.cart-row-focused');
            if (focusedRow) {
                e.preventDefault();
                const idx = parseInt(focusedRow.dataset.cartIndex);
                const qtyInput = focusedRow.querySelector('input[type="text"]');
                if (qtyInput) CartManager.openQtyPad(idx, qtyInput);
            }
            return;
        }

        // Alt + Delete — Remove focused cart item
        if (e.altKey && e.key === 'Delete' && !inInput) {
            const focusedRow = document.querySelector('tr.cart-row-focused');
            if (focusedRow) {
                e.preventDefault();
                const idx = parseInt(focusedRow.dataset.cartIndex);
                CartManager.removeItem(idx);
            }
            return;
        }
    });

    // === AUTO-BACKUP to localStorage every 30 seconds ===
    setInterval(() => {
        if (POS.cart && POS.cart.length > 0) {
            POS.backupToLocal();
        }
    }, 30000);

    // === BEFOREUNLOAD PROTECTION ===
    window.addEventListener('beforeunload', (e) => {
        // Backup before leave
        if (POS.cart && POS.cart.length > 0) {
            POS.backupToLocal();
            e.preventDefault();
            e.returnValue = 'You have items in your cart. Are you sure you want to leave?';
        }
    });

    console.log('✅ POS System Ready');
});

// =====================================================
// NETWORK GUARD
// Monitors server connection and shows status banners
// =====================================================
window.NetworkGuard = {
    _online: true,
    _checkInterval: null,
    _bannerTimeout: null,

    init() {
        // Browser online/offline events
        window.addEventListener('online', () => this.onOnline());
        window.addEventListener('offline', () => this.onOffline());

        // Periodic server check every 30 seconds
        this._checkInterval = setInterval(() => this.checkServer(), 30000);

        // Initial status
        if (!navigator.onLine) this.onOffline();
    },

    async checkServer() {
        try {
            const res = await fetch('../../api/pos/session_keepalive.php', {
                method: 'HEAD',
                cache: 'no-store'
            });
            if (!res.ok) throw new Error('Server error');
            if (!this._online) this.onOnline();
        } catch (e) {
            if (this._online) this.onOffline();
        }
    },

    onOffline() {
        if (!this._online) return; // already offline
        this._online = false;
        console.warn('🔴 Connection lost');

        // Disable PAY button
        const payBtn = document.getElementById('btn-pay');
        if (payBtn) {
            payBtn.dataset.wasDisabled = payBtn.disabled;
            payBtn.disabled = true;
        }

        // Show offline banner
        this._showBanner(
            'offline',
            '<i class="fa-solid fa-wifi" style="font-size:18px;"></i>' +
            '<span>Server connection lost — cart data is safe, payments disabled</span>',
            '#ef4444', '#dc2626', '#fff'
        );
    },

    onOnline() {
        this._online = true;
        console.log('🟢 Connection restored');

        // Re-enable PAY button
        const payBtn = document.getElementById('btn-pay');
        if (payBtn) {
            payBtn.disabled = payBtn.dataset.wasDisabled === 'true';
            delete payBtn.dataset.wasDisabled;
        }

        // Show restored banner (auto-hide after 3s)
        this._showBanner(
            'online',
            '<i class="fa-solid fa-wifi" style="font-size:18px;"></i>' +
            '<span>Connection restored</span>',
            '#22c55e', '#16a34a', '#fff'
        );

        if (this._bannerTimeout) clearTimeout(this._bannerTimeout);
        this._bannerTimeout = setTimeout(() => this.hideBanner(), 3000);

        // Sync any offline queued sales
        if (window.OfflineQueue) {
            setTimeout(() => OfflineQueue.syncAll(), 1000);
        }
    },

    _showBanner(type, html, color1, color2, textColor) {
        let banner = document.getElementById('network-status-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'network-status-banner';
            document.body.appendChild(banner);
        }
        banner.style.cssText = `
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 99999;
            background: linear-gradient(135deg, ${color1}, ${color2});
            color: ${textColor}; text-align: center; padding: 10px 16px;
            font-size: 13px; font-weight: 600;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.15);
            display: flex; align-items: center; justify-content: center; gap: 10px;
            transition: transform 0.3s ease;
        `;
        banner.innerHTML = html;
    },

    hideBanner() {
        const banner = document.getElementById('network-status-banner');
        if (banner) banner.remove();
    },

    isOnline() {
        return this._online;
    }
};

// Quick Cash denomination buttons helper
window.QuickCash = {
    add(amount) {
        const el = document.getElementById('amount-tendered');
        if (!el) return;
        const current = parseFloat(el.value) || 0;
        el.value = (current + amount).toFixed(2);
        CartManager.updateChange();
    },
    exact() {
        const el = document.getElementById('amount-tendered');
        if (!el) return;
        const total = POS.totals ? POS.totals.grand_total : 0;
        el.value = total.toFixed(2);
        CartManager.updateChange();
    }
};

// =====================================================
// SESSION TIMEOUT WARNING
// Monitors PHP session and warns before expiry
// =====================================================
window.SessionGuard = {
    maxLifetime: 1440,   // seconds (PHP default, updated from server)
    warnBefore: 300,     // warn 5 minutes before expiry
    checkInterval: 60,   // check every 60 seconds
    lastActivity: Date.now(),
    _timer: null,
    _warned: false,
    _expired: false,

    init() {
        // Track user activity to reset the timer
        ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach(evt => {
            document.addEventListener(evt, () => this.onActivity(), { passive: true });
        });

        // Get initial session info from server
        this.ping(true);

        // Periodic check
        this._timer = setInterval(() => this.check(), this.checkInterval * 1000);
    },

    onActivity() {
        this.lastActivity = Date.now();
        // If we had shown the warning and user is active, extend session
        if (this._warned && !this._expired) {
            this.extend();
        }
    },

    async ping(isInit = false) {
        try {
            const res = await fetch('../../api/pos/session_keepalive.php');
            const data = await res.json();
            if (!data.alive) {
                this.showExpired();
                return;
            }
            if (data.max_lifetime) {
                this.maxLifetime = data.max_lifetime;
            }
            this.lastActivity = Date.now();
            if (isInit) {
                console.log(`🔒 Session Guard active (timeout: ${this.maxLifetime}s)`);
            }
        } catch (e) {
            console.error('Session check failed:', e);
        }
    },

    check() {
        if (this._expired) return;

        const elapsed = (Date.now() - this.lastActivity) / 1000;
        const remaining = this.maxLifetime - elapsed;

        if (remaining <= 0) {
            // Session likely expired — verify with server
            this.verifyExpired();
        } else if (remaining <= this.warnBefore && !this._warned) {
            this.showWarning(Math.floor(remaining));
        }
    },

    async verifyExpired() {
        try {
            const res = await fetch('../../api/pos/session_keepalive.php');
            const data = await res.json();
            if (!data.alive) {
                this.showExpired();
            } else {
                // Server session is still alive (another tab kept it alive)
                this.lastActivity = Date.now();
                this._warned = false;
                this.hideWarning();
            }
        } catch (e) {
            this.showExpired();
        }
    },

    async extend() {
        this._warned = false;
        this.hideWarning();
        await this.ping();
        EllaToast.success('Session extended');
    },

    showWarning(secondsLeft) {
        this._warned = true;
        const mins = Math.ceil(secondsLeft / 60);

        // Create or update warning banner
        let banner = document.getElementById('session-timeout-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'session-timeout-banner';
            banner.style.cssText = `
                position: fixed; top: 0; left: 0; right: 0; z-index: 99999;
                background: linear-gradient(135deg, #f59e0b, #d97706);
                color: #000; text-align: center; padding: 10px 16px;
                font-size: 14px; font-weight: 600;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                display: flex; align-items: center; justify-content: center; gap: 12px;
                animation: slideDown 0.3s ease;
            `;
            document.body.appendChild(banner);
        }
        banner.innerHTML = `
            <i class="fa-solid fa-clock" style="font-size:18px;"></i>
            <span>Session expires in ~${mins} minute${mins > 1 ? 's' : ''}. Save your work or extend.</span>
            <button onclick="SessionGuard.extend()" 
                style="background:#fff; color:#d97706; border:none; padding:5px 14px; border-radius:6px; font-weight:700; cursor:pointer; font-size:13px;">
                <i class="fa-solid fa-arrows-rotate me-1"></i>Extend Session
            </button>
        `;
    },

    showExpired() {
        if (this._expired) return;
        this._expired = true;
        clearInterval(this._timer);

        let banner = document.getElementById('session-timeout-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'session-timeout-banner';
            document.body.appendChild(banner);
        }
        banner.style.cssText = `
            position: fixed; top: 0; left: 0; right: 0; z-index: 99999;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #fff; text-align: center; padding: 12px 16px;
            font-size: 14px; font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            display: flex; align-items: center; justify-content: center; gap: 12px;
        `;
        banner.innerHTML = `
            <i class="fa-solid fa-triangle-exclamation" style="font-size:20px;"></i>
            <span>Your session has expired. Redirecting to login...</span>
            <a href="../../views/auth/login.php" 
               style="background:#fff; color:#dc2626; padding:5px 14px; border-radius:6px; font-weight:700; text-decoration:none; font-size:13px;">
                Login Now
            </a>
        `;

        // Auto-redirect after 10 seconds
        setTimeout(() => {
            window.location.href = '../../views/auth/login.php';
        }, 10000);
    },

    hideWarning() {
        const banner = document.getElementById('session-timeout-banner');
        if (banner) banner.remove();
    }
};

// SessionGuard & amount-tendered logic moved to main init block for cleanliness
// No additional DOMContentLoaded listener needed here

