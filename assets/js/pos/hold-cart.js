/**
 * ============================================================
 * HOLD CART — Quick Suspend/Resume Transactions
 * ============================================================
 * Usage:
 *   HoldCart.hold()          — Park current cart in next slot
 *   HoldCart.resume(index)   — Restore a held cart
 *   HoldCart.renderBadges()  — Update badge bar UI
 *
 * Keyboard: F3 = Hold, F5 = Resume most recent
 * ============================================================
 */

const HoldCart = (() => {
    const STORAGE_KEY = 'ella_held_carts';
    const MAX_SLOTS = 5;

    /** Get all slots from sessionStorage */
    function getSlots() {
        try {
            const raw = sessionStorage.getItem(STORAGE_KEY);
            const slots = raw ? JSON.parse(raw) : [];
            // Ensure exactly MAX_SLOTS entries
            while (slots.length < MAX_SLOTS) slots.push(null);
            return slots.slice(0, MAX_SLOTS);
        } catch {
            return Array(MAX_SLOTS).fill(null);
        }
    }

    /** Save slots to sessionStorage */
    function saveSlots(slots) {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(slots));
    }

    /** Count occupied slots */
    function occupiedCount() {
        return getSlots().filter(s => s !== null).length;
    }

    /** Hold the current cart */
    function hold() {
        // Validate
        if (!POS.cart || POS.cart.length === 0) {
            EllaToast.warning('Cart is empty — nothing to hold.');
            return;
        }

        const slots = getSlots();
        const freeIndex = slots.findIndex(s => s === null);

        if (freeIndex === -1) {
            EllaToast.error('All 5 hold slots are full! Resume or clear a slot first.');
            return;
        }

        // Snapshot current state
        const buyer = window.POS_BUYER || {};
        const globalDiscount = (typeof SimpleCheckout !== 'undefined' && SimpleCheckout.globalDiscount)
            ? { ...SimpleCheckout.globalDiscount }
            : { type: 'percent', value: 0 };

        const total = POS.totals ? POS.totals.grand_total : POS.cart.reduce((s, i) => s + i.qty * i.price, 0);

        const snapshot = {
            cart: JSON.parse(JSON.stringify(POS.cart)),
            buyer: JSON.parse(JSON.stringify(buyer)),
            brandDiscounts: JSON.parse(JSON.stringify(POS.brandDiscounts || {})),
            globalDiscount: globalDiscount,
            total: total,
            label: buyer.buyer_name || 'Walk-in',
            itemCount: POS.cart.reduce((s, i) => s + i.qty, 0),
            heldAt: new Date().toISOString(),
            heldBy: window.CURRENT_USER_NAME || 'Staff'
        };

        slots[freeIndex] = snapshot;
        saveSlots(slots);

        // Clear current cart
        POS.cart = [];
        POS.brandDiscounts = {};
        if (typeof SimpleCheckout !== 'undefined') {
            SimpleCheckout.globalDiscount = { type: 'percent', value: 0 };
        }
        if (typeof POS.clearState === 'function') POS.clearState();

        // Re-render
        if (typeof CartManager !== 'undefined') CartManager.renderCart();
        renderBadges();

        // Focus search for next customer
        const searchInput = document.getElementById('product-search');
        if (searchInput) {
            searchInput.focus();
            searchInput.value = '';
        }

        const currency = POS.config?.currency || '₱';
        EllaToast.success(`Held in Slot #${freeIndex + 1} — ${snapshot.label} ${currency}${total.toLocaleString(undefined, { minimumFractionDigits: 2 })}`);
    }

    /** Resume a held cart by slot index */
    async function resume(index) {
        const slots = getSlots();

        if (!slots[index]) {
            EllaToast.warning('This slot is empty.');
            return;
        }

        // If current cart has items, warn
        if (POS.cart && POS.cart.length > 0) {
            const confirmed = await EllaConfirm.show({
                title: 'Replace Current Cart?',
                message: 'You have items in your cart. Resuming will replace them. Continue?',
                confirmText: 'Resume',
                confirmClass: 'btn-warning',
                icon: 'fa-triangle-exclamation',
                iconColor: 'text-warning'
            });
            if (!confirmed) return;
        }

        const snapshot = slots[index];

        // Restore state
        POS.cart = snapshot.cart || [];
        POS.brandDiscounts = snapshot.brandDiscounts || {};

        if (snapshot.buyer) {
            window.POS_BUYER = snapshot.buyer;
            if (typeof DraftUI !== 'undefined' && typeof DraftUI.restoreBuyerUI === 'function') {
                DraftUI.restoreBuyerUI(snapshot.buyer);
            }
        }

        if (typeof SimpleCheckout !== 'undefined' && snapshot.globalDiscount) {
            SimpleCheckout.globalDiscount = snapshot.globalDiscount;
        }

        // Clear slot
        slots[index] = null;
        saveSlots(slots);

        // Re-render
        if (typeof CartManager !== 'undefined') CartManager.renderCart();
        renderBadges();

        EllaToast.success(`Resumed Slot #${index + 1}`);
    }

    /** Resume the most recent held cart (for F5 shortcut) */
    function resumeMostRecent() {
        const slots = getSlots();
        // Find the most recently held slot
        let latestIndex = -1;
        let latestTime = '';

        slots.forEach((slot, i) => {
            if (slot && slot.heldAt > latestTime) {
                latestTime = slot.heldAt;
                latestIndex = i;
            }
        });

        if (latestIndex === -1) {
            EllaToast.info('No held transactions to resume.');
            return;
        }

        resume(latestIndex);
    }

    /** Clear a slot without resuming */
    async function clearSlot(index) {
        const slots = getSlots();
        if (!slots[index]) return;

        const confirmed = await EllaConfirm.show({
            title: 'Discard Held Cart?',
            message: `Discard Slot #${index + 1} (${slots[index].label})? This cannot be undone.`,
            confirmText: 'Discard',
            confirmClass: 'btn-danger',
            icon: 'fa-trash-can',
            iconColor: 'text-danger'
        });

        if (!confirmed) return;

        slots[index] = null;
        saveSlots(slots);
        renderBadges();
        EllaToast.info(`Slot #${index + 1} cleared.`);
    }

    /** Render the badge bar UI */
    function renderBadges() {
        const container = document.getElementById('hold-badge-bar');
        if (!container) return;

        const slots = getSlots();
        const hasAny = slots.some(s => s !== null);

        if (!hasAny) {
            container.innerHTML = '';
            container.classList.add('d-none');
            return;
        }

        container.classList.remove('d-none');
        const currency = POS.config?.currency || '₱';

        let html = '<div class="d-flex align-items-center gap-1 flex-wrap">';
        html += '<span class="badge bg-secondary bg-opacity-50 me-1" style="font-size:9px"><i class="fa-solid fa-pause me-1"></i>HELD:</span>';

        slots.forEach((slot, i) => {
            if (slot) {
                const timeAgo = getTimeAgo(slot.heldAt);
                html += `
                    <div class="held-slot-badge" title="Click to resume · Right-click to discard">
                        <span class="held-slot-resume" onclick="HoldCart.resume(${i})">
                            <span class="held-slot-num">#${i + 1}</span>
                            <span class="held-slot-label">${escapeHtml(slot.label)}</span>
                            <span class="held-slot-total">${currency}${slot.total.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                            <span class="held-slot-time">${timeAgo}</span>
                        </span>
                        <button class="held-slot-close" onclick="event.stopPropagation(); HoldCart.clearSlot(${i})" title="Discard">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>`;
            }
        });

        html += '</div>';
        container.innerHTML = html;
    }

    /** Time ago helper */
    function getTimeAgo(isoString) {
        const diff = Date.now() - new Date(isoString).getTime();
        const mins = Math.floor(diff / 60000);
        if (mins < 1) return 'just now';
        if (mins < 60) return `${mins}m ago`;
        const hours = Math.floor(mins / 60);
        return `${hours}h ago`;
    }

    /** HTML escape */
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    // Auto-render on load
    function init() {
        renderBadges();
        // Refresh time labels every 30s
        setInterval(renderBadges, 30000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Public API
    return {
        hold,
        resume,
        resumeMostRecent,
        clearSlot,
        renderBadges,
        getSlots,
        occupiedCount
    };
})();

// Expose globally
window.HoldCart = HoldCart;
