/* =====================================================
   POS GLOBAL STATE & CONFIGURATION
   With sessionStorage persistence for cart survival
===================================================== */

// --- Restore from sessionStorage if available ---
const _savedCart = sessionStorage.getItem('pos_cart');
const _savedBuyer = sessionStorage.getItem('pos_buyer');
const _savedBrandDiscounts = sessionStorage.getItem('pos_brand_discounts');

const POS = {
    cart: _savedCart ? JSON.parse(_savedCart) : [],
    brandDiscounts: _savedBrandDiscounts ? JSON.parse(_savedBrandDiscounts) : {},
    previewFormat: 'thermal80',
    config: {
        currency: '₱',
        defaultImage: '../../assets/img/products/no-image.png'
    },

    // Persist current state to sessionStorage
    saveState() {
        try {
            sessionStorage.setItem('pos_cart', JSON.stringify(this.cart));
            sessionStorage.setItem('pos_buyer', JSON.stringify(window.POS_BUYER));
            sessionStorage.setItem('pos_brand_discounts', JSON.stringify(this.brandDiscounts || {}));
            // Also persist global discount if SimpleCheckout exists
            if (typeof SimpleCheckout !== 'undefined' && SimpleCheckout.globalDiscount) {
                sessionStorage.setItem('pos_global_discount', JSON.stringify(SimpleCheckout.globalDiscount));
            }
        } catch (e) {
            console.warn('POS: Failed to save state to sessionStorage', e);
        }
    },

    // Clear all persisted state
    clearState() {
        sessionStorage.removeItem('pos_cart');
        sessionStorage.removeItem('pos_buyer');
        sessionStorage.removeItem('pos_brand_discounts');
        sessionStorage.removeItem('pos_global_discount');
        localStorage.removeItem('pos_cart_backup');
        localStorage.removeItem('pos_buyer_backup');
    },

    // === LOCAL STORAGE BACKUP (survives browser crash/close) ===
    backupToLocal() {
        try {
            if (this.cart && this.cart.length > 0) {
                localStorage.setItem('pos_cart_backup', JSON.stringify(this.cart));
                localStorage.setItem('pos_buyer_backup', JSON.stringify(window.POS_BUYER));
                localStorage.setItem('pos_brand_discounts_backup', JSON.stringify(this.brandDiscounts || {}));
                
                // Also backup global discount if available
                if (typeof SimpleCheckout !== 'undefined' && SimpleCheckout.globalDiscount) {
                    localStorage.setItem('pos_global_discount_backup', JSON.stringify(SimpleCheckout.globalDiscount));
                }

                localStorage.setItem('pos_backup_time', new Date().toISOString());
            }
        } catch (e) { /* quota exceeded or unavailable */ }
    },

    hasLocalBackup() {
        const backup = localStorage.getItem('pos_cart_backup');
        if (!backup) return false;
        try {
            const cart = JSON.parse(backup);
            return Array.isArray(cart) && cart.length > 0;
        } catch (e) { return false; }
    },

    async restoreFromLocal() {
        try {
            const cart = JSON.parse(localStorage.getItem('pos_cart_backup') || '[]');
            const buyer = JSON.parse(localStorage.getItem('pos_buyer_backup') || 'null');
            const backupTime = localStorage.getItem('pos_backup_time') || 'unknown';

            if (cart.length === 0) return false;

            const confirmed = await EllaConfirm.show({
                title: 'Recover Cart Data',
                message: `Found a saved cart with ${cart.length} item(s) from ${new Date(backupTime).toLocaleString()}. Recover it?`,
                confirmText: 'Recover Cart',
                confirmClass: 'btn-success',
                icon: 'fa-rotate-left',
                iconColor: 'text-primary'
            });

            if (confirmed) {
                this.cart = cart;
                if (buyer) window.POS_BUYER = buyer;
                
                // Restore Brand Discounts
                const savedBrandDisc = localStorage.getItem('pos_brand_discounts_backup');
                if (savedBrandDisc) {
                    try {
                        this.brandDiscounts = JSON.parse(savedBrandDisc);
                    } catch (e) { }
                }

                // Restore Global Discount
                const savedGlobalDisc = localStorage.getItem('pos_global_discount_backup');
                if (savedGlobalDisc && typeof SimpleCheckout !== 'undefined') {
                    try {
                        SimpleCheckout.globalDiscount = JSON.parse(savedGlobalDisc);
                    } catch (e) { }
                }

                this.saveState();
                if (window.CartManager) CartManager.renderCart();
                EllaToast.success(`Recovered ${cart.length} cart item(s)`);
                return true;
            } else {
                // User declined — clear backup
                localStorage.removeItem('pos_cart_backup');
                localStorage.removeItem('pos_buyer_backup');
                localStorage.removeItem('pos_brand_discounts_backup');
                localStorage.removeItem('pos_global_discount_backup');
                localStorage.removeItem('pos_backup_time');
            }
        } catch (e) {
            console.error('Recovery failed:', e);
        }
        return false;
    },


    // Show a toast notification
    showToast(message, type = 'success') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '1100';
            document.body.appendChild(container);
        }

        const icons = {
            success: 'fa-check-circle text-success',
            danger: 'fa-exclamation-circle text-danger',
            warning: 'fa-exclamation-triangle text-warning',
            info: 'fa-info-circle text-info'
        };

        const toastEl = document.createElement('div');
        toastEl.className = 'toast align-items-center border-0 shadow-lg';
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');

        const iconClass = icons[type] || icons.info;

        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center">
                    <i class="fa-solid ${iconClass} me-2 fs-5"></i>
                    <span class="fw-semibold">${message}</span>
                </div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

        container.appendChild(toastEl);

        if (window.bootstrap) {
            const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
            toast.show();
            toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
        } else {
            console.warn('Bootstrap not loaded, toast skipped');
        }
    }
};

// Global Active Buyer (restore from session or default)
window.POS_BUYER = _savedBuyer ? JSON.parse(_savedBuyer) : {
    buyer_id: null,
    buyer_name: 'Walk-in',
    price_tier: 'retail',
    is_walkin: 1,
    shop: ''
};
