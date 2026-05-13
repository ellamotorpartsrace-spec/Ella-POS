/* =====================================================
   WALLET SUPPLEMENT MODULE
   Allows buyers to partially or fully pay using their
   stored wallet balance alongside another payment method.
   ===================================================== */

window.WalletSupplement = {
    _active: false,

    /**
     * Call this whenever the buyer changes (from customer-selector.js).
     * Shows or hides the wallet supplement panel based on buyer state.
     */
    refresh() {
        const wrapper = document.getElementById('wallet-supplement-wrapper');
        if (!wrapper) return;

        const buyer = window.POS_BUYER;
        const isExisting = buyer && buyer.buyer_id && !buyer.is_walkin;
        const balance = isExisting ? parseFloat(buyer.wallet_balance || 0) : 0;

        // Update available badge always
        const availBadge = document.getElementById('wallet-supplement-avail');
        if (availBadge) {
            availBadge.textContent = '₱' + balance.toLocaleString(undefined, { minimumFractionDigits: 2 }) + ' available';
            availBadge.className = balance > 0 ? 'badge bg-success' : 'badge bg-secondary';
        }

        if (isExisting && balance > 0) {
            wrapper.classList.remove('d-none');
        } else {
            wrapper.classList.add('d-none');
            this._reset();
        }
    },

    /**
     * Toggle switch changed.
     */
    toggle(checked) {
        this._active = checked;
        const detail = document.getElementById('wallet-supplement-detail');
        if (!detail) return;

        if (checked) {
            detail.classList.remove('d-none');
            this._autofill();
        } else {
            detail.classList.add('d-none');
            this._reset(false); // don't change toggle state
        }
        this.updateDisplay();
    },

    /**
     * Auto-fill the wallet-use-amount with the minimum needed
     * (i.e. the shortfall from the current tendered amount).
     */
    _autofill() {
        const grandTotal = POS.totals ? POS.totals.grand_total : 0;
        const tendered = parseFloat(document.getElementById('amount-tendered')?.value || 0);
        // Fixed: Round to 2 decimals to avoid floating point noise
        const shortfall = Math.max(Math.round((grandTotal - tendered) * 100) / 100, 0);

        const balance = parseFloat(window.POS_BUYER?.wallet_balance || 0);
        const toUse = Math.min(shortfall, balance);

        const input = document.getElementById('wallet-use-amount');
        if (input) input.value = toUse > 0 ? toUse.toFixed(2) : '';
        this.updateDisplay();
    },

    /**
     * Input changed — clamp and update display.
     */
    onAmountChange() {
        const balance = parseFloat(window.POS_BUYER?.wallet_balance || 0);
        const input = document.getElementById('wallet-use-amount');
        if (!input) return;

        let val = parseFloat(input.value || 0);

        // Clamp to wallet balance
        if (val > balance) {
            val = balance;
            input.value = val.toFixed(2);
        }
        if (val < 0) {
            val = 0;
            input.value = '0.00';
        }

        this.updateDisplay();
    },

    /**
     * Recalculate and show how much cash is still needed.
     */
    updateDisplay() {
        if (!this._active) return;

        const grandTotal = POS.totals ? POS.totals.grand_total : 0;
        const tendered = parseFloat(document.getElementById('amount-tendered')?.value || 0);
        const walletUsed = parseFloat(document.getElementById('wallet-use-amount')?.value || 0);
        const totalCovered = tendered + walletUsed;
        const cashRemaining = Math.max(grandTotal - totalCovered, 0);
        const currencySymbol = POS.config?.currency || '₱';

        const cashEl = document.getElementById('wallet-cash-remaining');
        if (cashEl) {
            cashEl.textContent = currencySymbol + cashRemaining.toLocaleString(undefined, { minimumFractionDigits: 2 });
            cashEl.className = cashRemaining > 0 ? 'text-danger fw-bold' : 'text-success fw-bold';
        }
    },

    /**
     * Returns the wallet amount being used, or 0 if not active.
     */
    getUsedAmount() {
        if (!this._active) return 0;
        return parseFloat(document.getElementById('wallet-use-amount')?.value || 0);
    },

    /**
     * Validates that wallet usage doesn't exceed balance
     * and that together with tendered it covers the total.
     * Returns true if OK, false if validation fails.
     */
    validate() {
        if (!this._active) return true;

        const grandTotal = POS.totals ? POS.totals.grand_total : 0;
        const tendered = parseFloat(document.getElementById('amount-tendered')?.value || 0);
        const walletUsed = this.getUsedAmount();
        const balance = parseFloat(window.POS_BUYER?.wallet_balance || 0);

        if (walletUsed > balance) {
            EllaToast.error(`Wallet usage (₱${walletUsed.toFixed(2)}) exceeds available balance (₱${balance.toFixed(2)}).`);
            return false;
        }

        const totalCovered = tendered + walletUsed;
        const roundedCovered = Math.round(totalCovered * 100);
        const roundedTotal = Math.round(grandTotal * 100);

        if (roundedCovered < roundedTotal) {
            const shortfall = (grandTotal - totalCovered).toFixed(2);
            EllaToast.error(`Still short by ₱${shortfall}. Increase cash or wallet amount.`);
            return false;
        }

        return true;
    },

    /**
     * Reset state (called on buyer reset or toggle off).
     */
    _reset(resetToggle = true) {
        this._active = false;
        const toggle = document.getElementById('use-wallet-toggle');
        if (resetToggle && toggle) toggle.checked = false;
        const detail = document.getElementById('wallet-supplement-detail');
        if (detail) detail.classList.add('d-none');
        const input = document.getElementById('wallet-use-amount');
        if (input) input.value = '';
        const cashEl = document.getElementById('wallet-cash-remaining');
        if (cashEl) { cashEl.textContent = '₱0.00'; cashEl.className = 'text-primary fw-bold'; }
    }
};
