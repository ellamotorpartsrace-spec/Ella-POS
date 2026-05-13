/* =====================================================
   CART MANAGER - CART & PRICING LOGIC
   Core cart operations and rendering
===================================================== */

const CartManager = {
    // Undo history (stores deep-copy snapshots of POS.cart)
    _cartHistory: [],
    _maxHistory: 10,

    // Debounce state
    _renderTimer: null,
    _popoverTimer: null,
    _toastTimer: null,
    _needsScroll: false,

    _saveSnapshot() {
        try {
            this._cartHistory.push(JSON.parse(JSON.stringify(POS.cart)));
            if (this._cartHistory.length > this._maxHistory) {
                this._cartHistory.shift();
            }
        } catch (e) { /* ignore serialization errors */ }
    },

    undoLastAction() {
        if (this._cartHistory.length === 0) {
            EllaToast.info('Nothing to undo');
            return;
        }
        POS.cart = this._cartHistory.pop();
        this.renderCart();
        EllaToast.success('Undo successful');
    },
    // Helper: Get Current Active Tier
    getActiveTier() {
        return window.POS_BUYER ? window.POS_BUYER.price_tier : 'retail';
    },

    // Helper: Consistent brand color from name (subtle left-border palette)
    _brandColor(brand) {
        if (!brand) return '#cbd5e1'; // slate-300 for no-brand
        const palette = [
            '#6366f1', // indigo
            '#0ea5e9', // sky
            '#10b981', // emerald
            '#f59e0b', // amber
            '#ef4444', // red
            '#8b5cf6', // violet
            '#f97316', // orange
            '#06b6d4', // cyan
            '#84cc16', // lime
            '#ec4899', // pink
            '#14b8a6', // teal
            '#a855f7', // purple
        ];
        let hash = 0;
        for (let i = 0; i < brand.length; i++) {
            hash = brand.charCodeAt(i) + ((hash << 5) - hash);
        }
        return palette[Math.abs(hash) % palette.length];
    },

    // Calculate item price considering brand discounts (per-receipt) and manual discounts
    getDiscountedPrice(item) {
        const currentTier = item.override_tier || this.getActiveTier();
        let originalPrice = parseFloat(item.tiers[currentTier]);
        let tierFallback = false;
        if (!originalPrice || originalPrice <= 0) {
            originalPrice = parseFloat(item.tiers.retail);
            tierFallback = currentTier !== 'retail';
        }
        item.tier_fallback = tierFallback;

        // Priority 1: Manual item discount (cashier typed a specific amount or percent)
        if (item.manual_discount_type === 'custom') {
            return {
                original_price: originalPrice,
                price: parseFloat(item.manual_discount), // We stored custom price here
                discount: Math.max(0, originalPrice - parseFloat(item.manual_discount)),
                discount_raw: item.manual_discount,
                discount_type: 'custom',
                is_discounted: parseFloat(item.manual_discount) < originalPrice
            };
        } else if (item.manual_discount && item.manual_discount > 0) {
            let discountAmount = item.manual_discount;
            if (item.manual_discount_type === 'percent') {
                discountAmount = originalPrice * (item.manual_discount / 100);
            }
            return {
                original_price: originalPrice,
                price: Math.max(0, originalPrice - discountAmount),
                discount: discountAmount,
                discount_raw: item.manual_discount,
                discount_type: item.manual_discount_type || 'fixed',
                is_discounted: true
            };
        }

        // Priority 2: Brand discount (per-receipt, set by cashier)
        const brandDiscounts = POS.brandDiscounts || {};
        const brandKey = (item.brand || '').toUpperCase();

        if (brandKey && brandDiscounts[brandKey]) {
            const rule = brandDiscounts[brandKey];
            let discountAmount = 0;

            if (rule.type === 'percent') {
                discountAmount = originalPrice * (parseFloat(rule.value) / 100);
            } else {
                discountAmount = parseFloat(rule.value);
            }

            if (discountAmount > 0) {
                return {
                    original_price: originalPrice,
                    price: Math.max(0, originalPrice - discountAmount),
                    discount: discountAmount,
                    is_discounted: true
                };
            }
        }

        // No discount
        return {
            original_price: originalPrice,
            price: originalPrice,
            discount: 0,
            is_discounted: false
        };
    },

    // Get unique brands currently in the cart
    getCartBrands() {
        const brands = new Set();
        POS.cart.forEach(item => {
            if (item.brand) brands.add(item.brand.toUpperCase());
        });
        return Array.from(brands).sort();
    },

    // Apply a brand discount to all matching items in the current cart
    applyBrandDiscount(brand, type, value) {
        if (!brand || !value || value <= 0) return;

        const brandKey = brand.toUpperCase();
        POS.brandDiscounts[brandKey] = { type, value: parseFloat(value) };

        // Recalculate all items of this brand
        POS.cart.forEach(item => {
            if ((item.brand || '').toUpperCase() === brandKey) {
                const pricing = this.getDiscountedPrice(item);
                item.price = pricing.price;
                item.original_price = pricing.original_price;
                item.item_discount = pricing.discount;
            }
        });

        this.renderCart();
    },

    // Remove a brand discount
    removeBrandDiscount(brand) {
        const brandKey = brand.toUpperCase();
        delete POS.brandDiscounts[brandKey];

        // Recalculate items of this brand
        POS.cart.forEach(item => {
            if ((item.brand || '').toUpperCase() === brandKey) {
                const pricing = this.getDiscountedPrice(item);
                item.price = pricing.price;
                item.original_price = pricing.original_price;
                item.item_discount = pricing.discount;
            }
        });

        this.renderCart();
    },

    addToCart(product, fromScan = false) {
        this._saveSnapshot();
        const stock = parseInt(product.stock ?? 0);

        // 1. Stock Check
        if (stock <= 0) {
            EllaToast.warning('Item is out of stock!');
            return;
        }

        // 2. Prepare Price Tiers (Persist these for switching later)
        const tiers = {
            retail: parseFloat(product.price_retail || 0),
            wholesale: parseFloat(product.price_wholesale) > 0 ? parseFloat(product.price_wholesale) : parseFloat(product.price_retail || 0),
            dealer: parseFloat(product.price_dealer) > 0 ? parseFloat(product.price_dealer) : parseFloat(product.price_retail || 0)
        };

        // 3. Resolve Initial Price
        const currentTier = this.getActiveTier();
        const currentPrice = tiers[currentTier] || tiers.retail;

        // 4. Check Existing
        const existingIndex = POS.cart.findIndex(i =>
            i.variation_id === product.variation_id &&
            i.unit_id === (product.unit_id || null)
        );

        if (existingIndex > -1) {
            const existing = POS.cart[existingIndex];

            // Refresh stock and tiers based on latest product data
            existing.stock = stock;
            existing.tiers = tiers;

            if (existing.qty + 1 > existing.stock) {
                const isMax = existing.qty >= existing.stock;
                if (isMax) {
                    EllaToast.warning(`Maximum stock reached (${existing.stock}). If stock was added, please re-scan the item.`);
                } else {
                    EllaToast.warning('Maximum stock reached for this item.');
                }
                return;
            }
            existing.qty++;
            // Re-evaluate price in case rules changed or tier changed
            const pricing = this.getDiscountedPrice(existing);
            existing.price = pricing.price;
            existing.original_price = pricing.original_price;
            existing.item_discount = pricing.discount;

        } else {
            // New Item
            const newItem = {
                variation_id: product.variation_id,
                unit_id: product.unit_id || null,
                multiplier: parseInt(product.multiplier) || 1,
                name: product.product_name,
                brand: product.brand_name || '',
                variation: product.variation_name || '',
                unit_type: product.unit_type || 'pc',
                barcode: product.barcode || '',
                sku: product.sku || '',
                description: product.description || '',
                stock: stock,
                qty: 1,
                tiers: tiers,
                tier_fallback: false,
                manual_discount: 0,
                manual_discount_type: 'fixed'
            };

            // Calculate initial price with discounts
            const pricing = this.getDiscountedPrice(newItem);
            newItem.price = pricing.price;
            newItem.original_price = pricing.original_price;
            newItem.item_discount = pricing.discount;

            POS.cart.push(newItem);
        }

        this.renderCart();

        // Track last added index for flash animation
        this._lastAddedIndex = POS.cart.length - 1;
        // Apply flash after render completes
        setTimeout(() => {
            const row = document.querySelector(`tr[data-cart-index="${this._lastAddedIndex}"]`);
            if (row) {
                row.classList.add('cart-row-added');
                row.addEventListener('animationend', () => row.classList.remove('cart-row-added'), { once: true });
            }
        }, 50);

        // Scroll effect
        if (fromScan) {
            this._needsScroll = true;
        }

        // Show Toast Notification (debounced to avoid spam lag)
        if (this._toastTimer) clearTimeout(this._toastTimer);
        this._toastTimer = setTimeout(() => {
            EllaToast.success(`Added ${product.product_name}`);
        }, 150);
    },

    // Recalculates ALL items when a user switches from Walk-in -> Dealer (or vice versa)
    recalculateCart() {
        // Recalculate item prices based on new tier + automated rules
        POS.cart.forEach(item => {
            const pricing = this.getDiscountedPrice(item);
            item.price = pricing.price;
            item.original_price = pricing.original_price;
            item.item_discount = pricing.discount;
        });

        this.renderCart();
    },

    // NEW: Background refresh for a specific item's stock/prices
    async refreshStock(index) {
        const item = POS.cart[index];
        if (!item) return false;

        const query = item.barcode || item.sku || `${item.name} ${item.variation}`.trim();
        if (!query) return false;

        try {
            // Use the query to fetch latest from simple_search.php
            const res = await fetch(`../../api/pos/simple_search.php?q=${encodeURIComponent(query)}`);
            const data = await res.json();

            // Find the specific variation/unit in the results
            const updated = data.find(i =>
                i.variation_id === item.variation_id &&
                i.unit_id === (item.unit_id || null)
            );

            if (updated) {
                const newStock = parseInt(updated.stock || 0);
                const newTiers = {
                    retail: parseFloat(updated.price_retail || 0),
                    wholesale: parseFloat(updated.price_wholesale) > 0 ? parseFloat(updated.price_wholesale) : parseFloat(updated.price_retail || 0),
                    dealer: parseFloat(updated.price_dealer) > 0 ? parseFloat(updated.price_dealer) : parseFloat(updated.price_retail || 0)
                };

                // Update if different
                if (item.stock !== newStock || JSON.stringify(item.tiers) !== JSON.stringify(newTiers)) {
                    console.log(`[POS] Auto-refreshed ${item.name}: Stock ${item.stock} -> ${newStock}`);
                    item.stock = newStock;
                    item.tiers = newTiers;

                    // Re-evaluate price in case tiers changed
                    const pricing = this.getDiscountedPrice(item);
                    item.price = pricing.price;
                    item.original_price = pricing.original_price;
                    item.item_discount = pricing.discount;

                    this.renderCart();
                    return true;
                }
            }
        } catch (err) {
            console.error("[POS] Stock refresh failed:", err);
        }
        return false;
    },

    async updateQty(index, change) {
        const item = POS.cart[index];
        if (!item) return;
        this._saveSnapshot();

        const newQty = item.qty + change;

        if (newQty <= 0) {
            EllaConfirm.show({
                title: "Remove Item?",
                message: "This item will be removed from your cart.",
                confirmText: "Remove",
                confirmClass: "btn-danger",
                icon: "fa-trash-can",
                iconColor: "text-danger",
                onConfirm: () => {
                    POS.cart.splice(index, 1);
                    this.renderCart();
                },
            });
            return;
        }

        // AUTO-REFRESH LOGIC:
        // If trying to increase and we hit the limit, try refreshing from server once
        if (change > 0 && newQty > item.stock) {
            await this.refreshStock(index);
            if (newQty > item.stock) {
                EllaToast.warning(`Maximum stock reached (${item.stock}).`);
                return;
            }
        }

        if (newQty <= item.stock) {
            item.qty = newQty;
        }
        this.renderCart();
    },

    async setQty(index, value) {
        this._saveSnapshot();
        const item = POS.cart[index];
        const qty = parseInt(value);
        if (!item || isNaN(qty)) return;

        if (qty <= 0) {
            POS.cart.splice(index, 1);
        } else {
            // If target qty > current stock, try one refresh
            if (qty > item.stock) {
                await this.refreshStock(index);
            }

            if (qty <= item.stock) {
                item.qty = qty;
            } else {
                item.qty = item.stock; // Cap at max
                EllaToast.warning(`Quantity capped at max stock (${item.stock}).`);
            }
        }

        this.renderCart();
    },

    removeItem(index) {
        this._saveSnapshot();
        const removed = POS.cart.splice(index, 1)[0];
        this._lastRemoved = { item: removed, index };
        this.renderCart();

        // Show undo toast
        if (removed) {
            EllaToast.show({
                message: `Removed ${removed.name}`,
                type: 'danger',
                duration: 5000,
                actionLabel: 'UNDO',
                onAction: () => {
                    if (this._lastRemoved) {
                        POS.cart.splice(this._lastRemoved.index, 0, this._lastRemoved.item);
                        this._lastRemoved = null;
                        this.renderCart();
                        EllaToast.success('Item restored');
                    }
                }
            });
        }
    },

    // Duplicate a cart item as a new independent line
    duplicateItem(index) {
        const item = POS.cart[index];
        if (!item) return;
        this._saveSnapshot();
        const clone = JSON.parse(JSON.stringify(item));
        POS.cart.splice(index + 1, 0, clone);
        this.renderCart();
        EllaToast.success(`Duplicated: ${item.name}`);
    },

    // Open a floating numpad for quick quantity entry
    openQtyPad(index, inputEl) {
        // Remove any existing pad
        document.getElementById('cart-qty-pad')?.remove();

        const item = POS.cart[index];
        if (!item) return;

        const rect = inputEl.getBoundingClientRect();

        const pad = document.createElement('div');
        pad.id = 'cart-qty-pad';
        pad.className = 'cart-qty-pad';

        // On mobile (<=768px), use centered bottom-sheet style
        const isMobile = window.innerWidth <= 768;
        if (isMobile) {
            pad.style.cssText = `
                position: fixed;
                bottom: 0; left: 0; right: 0;
                z-index: 9999;
                transform: none;
            `;
            pad.classList.add('qty-pad-mobile');
        } else {
            // Desktop: anchor below the input, clamped to viewport
            let top = rect.bottom + 6;
            let left = rect.left + rect.width / 2;
            // Clamp so it doesn't overflow right
            if (left + 100 > window.innerWidth) left = window.innerWidth - 110;
            if (left < 110) left = 110;
            // Clamp so it doesn't overflow bottom
            if (top + 200 > window.innerHeight) top = rect.top - 210;

            pad.style.cssText = `
                position: fixed;
                top: ${top}px;
                left: ${left}px;
                transform: translateX(-50%);
                z-index: 9999;
            `;
        }

        const maxStock = item.stock || 999;
        const presets = [10, 12, 24, 36, 48, 96].filter(v => v <= maxStock);

        pad.innerHTML = `
            <div class="qty-pad-header">
                <span class="qty-pad-title">${item.name}</span>
                <span class="qty-pad-stock">Stock: ${maxStock}</span>
            </div>
            <div class="qty-pad-presets">
                ${presets.map(v => `<button class="qty-pad-preset ${v === item.qty ? 'active' : ''}" data-val="${v}">${v}</button>`).join('')}
            </div>
            <div class="qty-pad-custom">
                <input type="number" class="qty-pad-input" value="${item.qty}" min="1" max="${maxStock}" autofocus>
                <button class="qty-pad-apply"><i class="fa-solid fa-check"></i></button>
            </div>
        `;

        document.body.appendChild(pad);

        // Focus the input
        const qtyInput = pad.querySelector('.qty-pad-input');
        setTimeout(() => { qtyInput.focus(); qtyInput.select(); }, 50);

        // Preset click
        pad.querySelectorAll('.qty-pad-preset').forEach(btn => {
            btn.addEventListener('click', () => {
                const val = parseInt(btn.dataset.val);
                this.setQty(index, val);
                pad.remove();
            });
        });

        // Apply custom value
        const applyBtn = pad.querySelector('.qty-pad-apply');
        const applyQty = () => {
            const val = parseInt(qtyInput.value);
            if (val > 0) {
                this.setQty(index, val);
            }
            pad.remove();
        };
        applyBtn.addEventListener('click', applyQty);
        qtyInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') applyQty();
            if (e.key === 'Escape') pad.remove();
        });

        // Close on click outside
        setTimeout(() => {
            document.addEventListener('click', function closePad(e) {
                if (!pad.contains(e.target) && e.target !== inputEl) {
                    pad.remove();
                    document.removeEventListener('click', closePad);
                }
            });
        }, 100);
    },

    // Move an item from one position to another (drag-and-drop reorder)
    moveItem(fromIndex, toIndex) {
        if (fromIndex === toIndex) return;
        if (fromIndex < 0 || fromIndex >= POS.cart.length) return;
        if (toIndex < 0 || toIndex >= POS.cart.length) return;
        this._saveSnapshot();
        const [item] = POS.cart.splice(fromIndex, 1);
        POS.cart.splice(toIndex, 0, item);
        this.renderCart();
    },

    clearCart() {
        const hasItems = POS.cart.length > 0;
        const buyerSelected = window.POS_BUYER && window.POS_BUYER.is_walkin === 0;
        const hasDiscount = (SimpleCheckout.globalDiscount && SimpleCheckout.globalDiscount.value > 0) || 
                            (POS.brandDiscounts && Object.keys(POS.brandDiscounts).length > 0);
        const hasTendered = document.getElementById('amount-tendered')?.value !== '';

        if (!hasItems && !buyerSelected && !hasDiscount && !hasTendered) return;

        EllaConfirm.show({
            title: 'Reset Transaction?',
            message: 'This will clear all items, customer selection, and payment details.',
            confirmText: 'Reset All',
            confirmClass: 'btn-danger',
            icon: 'fa-trash-can',
            iconColor: 'text-danger',
            onConfirm: () => {
                // 1. Clear Data
                POS.cart = [];
                this._undoStack = [];
                if (typeof SimpleCheckout !== 'undefined') SimpleCheckout.removeDiscount(true);
                
                // 2. Reset Modules
                if (typeof CustomerSelector !== 'undefined') CustomerSelector.resetToWalkin();
                if (typeof PaymentMethodHandler !== 'undefined') PaymentMethodHandler.reset();
                
                // 3. Clear Persisted State
                if (typeof POS !== 'undefined' && typeof POS.clearState === 'function') POS.clearState();

                // 4. Update UI
                this.renderCart();
                this.updateChange();
                
                EllaToast.info('Transaction cleared');
            }
        });
    },

    renderCart() {
        if (this._renderTimer) clearTimeout(this._renderTimer);
        this._renderTimer = setTimeout(() => {
            this._doRenderCart();
        }, 30); // 30ms debounce
    },

    _doRenderCart() {
        const tbody = document.getElementById('cart-tbody');
        const totalEl = document.getElementById('cart-total');
        const btnPay = document.getElementById('btn-pay');
        const btnPreview = document.getElementById('btn-preview');

        if (!tbody || !totalEl) return;

        let subtotal = 0;
        const rowsHtml = [];

        if (POS.cart.length === 0) {
            rowsHtml.push(`<tr><td colspan="5">
                <div class="cart-empty-state">
                    <i class="fa-solid fa-cart-shopping d-block"></i>
                    <h6>Your cart is empty</h6>
                    <p>Scan a barcode or search to add items</p>
                </div>
            </td></tr>`);
        }

        let totalSavings = 0;

        let totalQty = 0;
        POS.cart.forEach((item, index) => {
            const lineTotal = item.qty * item.price;
            subtotal += lineTotal;
            totalQty += item.qty;
            if (item.item_discount > 0) totalSavings += item.item_discount * item.qty;

            const isDiscounted = item.item_discount > 0;
            // Build discount detail label for badge
            let discountDetail = '';
            if (isDiscounted) {
                if (item.manual_discount_type === 'custom') {
                    // Custom target price - show the derived discount off the original price
                    discountDetail = `${POS.config.currency}${item.item_discount.toFixed(2)} OFF`;
                } else if (item.manual_discount > 0) {
                    if (item.manual_discount_type === 'percent') {
                        discountDetail = `${item.manual_discount}% OFF`;
                    } else {
                        discountDetail = `${POS.config.currency}${parseFloat(item.manual_discount).toFixed(2)} OFF`;
                    }
                } else {
                    // Brand / batch discount or other automated discount
                    const brandKey = (item.brand || '').toUpperCase();
                    if (brandKey && POS.brandDiscounts && POS.brandDiscounts[brandKey]) {
                        const rule = POS.brandDiscounts[brandKey];
                        if (rule.type === 'percent') {
                            discountDetail = `${rule.value}% OFF`;
                        } else {
                            discountDetail = `${POS.config.currency}${parseFloat(rule.value).toFixed(2)} OFF`;
                        }
                    } else {
                        // Fallback
                        discountDetail = `${POS.config.currency}${item.item_discount.toFixed(2)} OFF`;
                    }
                }
            }
            const priceDisplay = isDiscounted
                ? `<div> <span class="text-decoration-line-through text-muted small">${POS.config.currency}${item.original_price.toFixed(2)}</span></div>
    <div class="text-danger fw-bold">${POS.config.currency}${item.price.toFixed(2)}</div>`
                : `<div class="fw-bold" > ${POS.config.currency}${item.price.toFixed(2)}</div> `;

            // Price tier badge (per-item override takes priority)
            const itemTier = item.override_tier || CartManager.getActiveTier();
            const isOverride = !!item.override_tier;
            let tierLabel = 'SRP';
            let tierBadgeClass = 'bg-success';
            if (itemTier === 'wholesale') {
                tierLabel = 'WHOLESALE';
                tierBadgeClass = 'bg-info';
            } else if (itemTier === 'dealer') {
                tierLabel = 'DEALER';
                tierBadgeClass = 'bg-warning text-dark';
            }
            // If overridden, add outline style to make it stand out
            if (isOverride) {
                tierLabel += ' ✎';
            }

            // Build meta info string (SKU | Barcode | Brand | Variation)
            let metaInfo = [];
            if (item.sku) metaInfo.push(`<span class="font-monospace text-secondary" style="font-size:9px;"><i class="fa-solid fa-tag me-1 opacity-60"></i>SKU: ${item.sku}</span>`);
            if (item.barcode) metaInfo.push(`<span class="font-monospace text-muted" style="font-size:9px" > <i class="fa-solid fa-barcode me-1"></i>${item.barcode}</span> `);
            if (item.brand) metaInfo.push(item.brand);
            if (item.variation) metaInfo.push(item.variation);
            metaInfo.push(`(${item.unit_type})`);

            // Unit price breakdown info (only for items with a unit, e.g. Box of 20)
            let unitBreakdownHtml = '';
            if (item.unit_id && item.multiplier > 1) {
                const pricePerPc = item.price / item.multiplier;
                unitBreakdownHtml = `
                    <div class="mt-1" style="font-size:10px; color: var(--bs-secondary);">
                        <i class="fa-solid fa-calculator me-1 opacity-50"></i>
                        <span class="font-monospace">${POS.config.currency}${pricePerPc.toFixed(2)}/pc &times; ${item.multiplier} pcs = <strong>${POS.config.currency}${item.price.toFixed(2)}</strong></span>
                    </div>`;
            }

            const metaHtml = metaInfo.join(' | ');

            // Build description icon (shown only when description exists)
            const desc = item.description || '';
            const safeDesc = desc.replace(/'/g, "\\'").replace(/"/g, '&quot;');
            const descIcon = desc
                ? `<button type = "button" class="btn btn-link btn-sm p-0 ms-2 desc-info-btn"
data-bs-toggle="popover"
data-bs-trigger="hover focus"
data-bs-placement="right"
data-bs-content="${safeDesc}"
data-bs-title="Description"
style="font-size:12px; vertical-align:middle; color: var(--pos-primary);"
aria-label="View product description"
onclick="event.stopPropagation()" >
    <i class='fa-solid fa-circle-info'></i>
                   </button> `
                : '';

            // Low stock indicator
            let lowStockHtml = '';
            if (item.stock > 0) {
                const stockUsedPct = (item.qty / item.stock) * 100;
                if (stockUsedPct >= 100) {
                    lowStockHtml = `<span class="cart-low-stock-badge danger"><i class="fa-solid fa-triangle-exclamation"></i>MAX STOCK</span>`;
                } else if (stockUsedPct >= 80) {
                    lowStockHtml = `<span class="cart-low-stock-badge warning"><i class="fa-solid fa-triangle-exclamation"></i>${item.stock - item.qty} left</span>`;
                }
            }

            rowsHtml.push(`
    <tr data-cart-index="${index}" class="${isDiscounted ? 'cart-row-discounted' : ''}"
        style="transition: background 0.15s ease; border-left: 4px solid ${this._brandColor(item.brand)};">
                    <td class="ps-2 py-2">
                        <div class="d-flex align-items-start">
                            <span class="cart-drag-handle text-muted me-2 d-flex align-items-center"
                                  draggable="true"
                                  style="cursor:grab; font-size:16px; line-height:1; padding-top:2px; user-select:none;"
                                  title="Drag to reorder">⠿</span>
                            <div style="flex:1; min-width:0;">
                                <div class="fw-bold text-dark d-flex align-items-start" style="word-wrap: break-word; overflow-wrap: break-word; max-width: 200px;">
                                    <span class="flex-grow-1">${item.name}</span>${descIcon}
                                </div>
                                <div class="small text-muted" style="font-size:10px;">
                                    ${metaHtml}
                                    <div class="mt-1">
                                        <span class="badge ${tierBadgeClass}" style="font-size:8px">${tierLabel}</span>
                                        ${isDiscounted ? `<span class="badge bg-danger ms-1" style="font-size:8px">${discountDetail || 'DISCOUNTED'}</span>` : ''}
                                        ${item.tier_fallback ? `<span class="badge bg-warning text-dark ms-1" style="font-size:8px">No ${itemTier} price &ndash; using SRP</span>` : ''}
                                    </div>
                                    ${unitBreakdownHtml}
                                    ${lowStockHtml ? `<div class="mt-1">${lowStockHtml}</div>` : ''}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="text-center align-middle">
                         <div class="input-group input-group-sm justify-content-center" style="width: 80px; margin:auto;">
                            <button class="btn btn-outline-secondary px-1" onclick="CartManager.updateQty(${index}, -1)">-</button>
                            <input type="text" class="form-control text-center px-0 fw-bold" value="${item.qty}" 
                                   onchange="CartManager.setQty(${index}, this.value)"
                                   onclick="CartManager.openQtyPad(${index}, this)" readonly
                                   style="cursor:pointer; caret-color:transparent;"
                                   title="Click for quick qty">
                            <button class="btn btn-outline-secondary px-1" onclick="CartManager.updateQty(${index}, 1)">+</button>
                        </div>
                    </td>
                    <td class="text-end align-middle pe-3 small" 
                        ondblclick="CartManager.editPrice(this, ${index})"
                        title="Double-click to edit price/discount">
                        ${priceDisplay}
                    </td>
                    <td class="text-end align-middle pe-3 fw-bold text-primary small">
                        ${POS.config.currency}${lineTotal.toFixed(2)}
                    </td>
                    <td class="text-center align-middle">
                        <div class="d-flex gap-1 justify-content-center">
                            <i class="fa-solid fa-copy text-secondary opacity-50" 
                               style="cursor:pointer; font-size:12px;" title="Duplicate item"
                               onclick="CartManager.duplicateItem(${index})"></i>
                            <i class="fa-solid fa-trash-can text-danger opacity-50" 
                               style="cursor:pointer" onclick="CartManager.removeItem(${index})"></i>
                        </div>
                    </td>
                </tr>
    `);
        });

        // Batch DOM update (performance: single innerHTML write instead of N+= appends)
        tbody.innerHTML = rowsHtml.join('');

        // Update cart count badge + savings
        const badgeEl = document.getElementById('cart-count-badge');
        if (badgeEl) {
            if (POS.cart.length > 0) {
                badgeEl.textContent = `${POS.cart.length} line${POS.cart.length > 1 ? 's' : ''} · ${totalQty} item${totalQty > 1 ? 's' : ''}`;
                badgeEl.classList.remove('d-none');
            } else {
                badgeEl.textContent = '';
                badgeEl.classList.add('d-none');
            }
        }

        // Update savings badge in cart header
        const savingsEl = document.getElementById('cart-savings-badge');
        if (savingsEl) {
            if (totalSavings > 0) {
                savingsEl.innerHTML = `<i class="fa-solid fa-tag"></i> Saved ${POS.config.currency}${totalSavings.toFixed(2)}`;
                savingsEl.classList.remove('d-none');
            } else {
                savingsEl.innerHTML = '';
                savingsEl.classList.add('d-none');
            }
        }

        // Update undo button state
        const undoBtn = document.getElementById('btn-cart-undo');
        if (undoBtn) {
            undoBtn.disabled = this._cartHistory.length === 0;
            undoBtn.classList.toggle('opacity-50', this._cartHistory.length === 0);
        }

        // Show/hide cart filter bar (visible when 3+ items)
        const filterWrapper = document.getElementById('cart-filter-wrapper');
        if (filterWrapper) {
            if (POS.cart.length >= 3) {
                filterWrapper.classList.remove('d-none');
            } else {
                filterWrapper.classList.add('d-none');
                // Clear filter when hidden
                const filterInput = document.getElementById('cart-filter-input');
                if (filterInput) filterInput.value = '';
            }
        }

        // Re-apply filter if active
        const activeFilter = document.getElementById('cart-filter-input')?.value;
        if (activeFilter) this.filterCart(activeFilter);

        // Attach drag-and-drop listeners to cart rows
        this._initCartDragDrop(tbody);

        // Global Transaction Discount
        let globalDiscountAmount = 0;
        if (SimpleCheckout.globalDiscount.value > 0) {
            if (SimpleCheckout.globalDiscount.type === 'percent') {
                globalDiscountAmount = subtotal * (SimpleCheckout.globalDiscount.value / 100);
            } else {
                globalDiscountAmount = parseFloat(SimpleCheckout.globalDiscount.value);
            }
        }

        const grandTotal = Math.round(Math.max(0, subtotal - globalDiscountAmount) * 100) / 100;

        let globalDiscountLabel = 'Discount';
        if (SimpleCheckout.globalDiscount && SimpleCheckout.globalDiscount.value > 0) {
            if (SimpleCheckout.globalDiscount.type === 'percent') {
                globalDiscountLabel += ` (${SimpleCheckout.globalDiscount.value}%)`;
            }
        }

        const itemDiscountSum = POS.cart.reduce((sum, i) => sum + ((i.item_discount || 0) * i.qty), 0);

        // Helper to expose totals for saving
        POS.totals = {
            subtotal: subtotal,              // Sum of qty * price (net after item discounts)
            item_discount_total: itemDiscountSum,
            transaction_discount: globalDiscountAmount,
            discount_amount: globalDiscountAmount, // Header discount should only be transaction-level
            grand_total: grandTotal,
            global_discount: globalDiscountAmount
        };

        // Update Total Display
        if (globalDiscountAmount > 0) {
            totalEl.innerHTML = `
    <div class="d-flex justify-content-between h6 mb-0 text-muted" >
                    <span>Subtotal:</span>
                    <span>${POS.config.currency}${subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                </div>
                <div class="d-flex justify-content-between h6 mb-0 text-danger">
                    <span>${globalDiscountLabel}:</span>
                    <span>-${POS.config.currency}${globalDiscountAmount.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                </div>
                <div class="border-top my-1"></div>
                ${POS.config.currency}${grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}
`;
        } else {
            totalEl.innerText = POS.config.currency + grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
        }

        // Update Discount Display on Payment Panel
        const discDisplay = document.getElementById('discount-display');
        if (discDisplay) {
            const val = SimpleCheckout.globalDiscount.value;
            const type = SimpleCheckout.globalDiscount.type === 'percent' ? '%' : '₱';
            discDisplay.value = val > 0 ? `${val}${type} ` : '0.00';
            if (val > 0) discDisplay.classList.add('text-danger');
            else discDisplay.classList.remove('text-danger');
        }

        // Update Buttons
        const hasItems = POS.cart.length > 0;

        if (btnPay) {
            btnPay.disabled = !hasItems;
            btnPay.classList.toggle('opacity-50', !hasItems);
        } else {
            console.error('Create Button (btn-pay) not found in DOM');
        }

        if (btnPreview) {
            btnPreview.disabled = !hasItems;
            btnPreview.classList.toggle('opacity-50', !hasItems);
        } else {
            console.error('Preview Button (btn-preview) not found in DOM');
        }

        this.updateChange();

        // Reinitialise Bootstrap tooltips and popovers for description icons
        if (this._popoverTimer) clearTimeout(this._popoverTimer);
        this._popoverTimer = setTimeout(() => {
            // Dispose old popovers first
            document.querySelectorAll('.desc-info-btn').forEach(el => {
                const existing = bootstrap.Popover.getInstance(el);
                if (existing) existing.dispose();
                new bootstrap.Popover(el, {
                    boundary: 'window',
                    trigger: 'hover focus',
                    html: false
                });
            });
        }, 150); // increased debounce for popovers

        // Persist cart state to sessionStorage
        if (typeof POS.saveState === 'function') POS.saveState();

        // Handle scheduled scroll from scan
        if (this._needsScroll && tbody) {
            tbody.scrollIntoView({ behavior: 'smooth', block: 'end' });
            this._needsScroll = false;
        }
    },

    // Drag-and-drop handler for cart rows
    _initCartDragDrop(tbody) {
        let dragSrcIndex = null;

        tbody.querySelectorAll('.cart-drag-handle').forEach(handle => {
            handle.addEventListener('dragstart', (e) => {
                const row = handle.closest('tr');
                if (!row) return;
                dragSrcIndex = parseInt(row.dataset.cartIndex);
                row.style.opacity = '0.4';
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', dragSrcIndex);
                // Set the drag ghost image to the entire row instead of just the handle
                if (typeof e.dataTransfer.setDragImage === 'function') {
                    e.dataTransfer.setDragImage(row, 15, 15);
                }
            });

            handle.addEventListener('dragend', (e) => {
                const row = handle.closest('tr');
                if (row) row.style.opacity = '';
                // Clean up all row highlights
                tbody.querySelectorAll('tr[data-cart-index]').forEach(r => {
                    r.style.background = '';
                    r.style.borderTop = '';
                });
                dragSrcIndex = null;
            });
        });

        tbody.querySelectorAll('tr[data-cart-index]').forEach(row => {
            row.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                // Visual feedback: highlight the row being hovered
                row.style.background = 'rgba(13, 110, 253, 0.08)';
                row.style.borderTop = '2px solid #0d6efd';
            });

            row.addEventListener('dragleave', () => {
                row.style.background = '';
                row.style.borderTop = '';
            });

            row.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                row.style.background = '';
                row.style.borderTop = '';
                const toIndex = parseInt(row.dataset.cartIndex);
                if (dragSrcIndex !== null && dragSrcIndex !== toIndex) {
                    CartManager.moveItem(dragSrcIndex, toIndex);
                }
            });
        });
    },

    // Cart filter — hides rows that don't match the query
    filterCart(query) {
        const tbody = document.getElementById('cart-tbody');
        if (!tbody) return;
        const q = (query || '').toLowerCase().trim();
        const rows = tbody.querySelectorAll('tr[data-cart-index]');
        rows.forEach((row, i) => {
            const item = POS.cart[parseInt(row.dataset.cartIndex)];
            if (!item || !q) {
                row.classList.remove('d-none');
                return;
            }
            const searchable = [
                item.name, item.brand, item.variation,
                item.sku, item.barcode, item.unit_type
            ].join(' ').toLowerCase();
            row.classList.toggle('d-none', !searchable.includes(q));
        });
    },

    clearCartFilter() {
        const input = document.getElementById('cart-filter-input');
        if (input) input.value = '';
        this.filterCart('');
    },

    // Helper to create a small popup editor inside a table cell
    _createCellPopup(cell, { html, width }) {
        // Clear any existing popups in any cell to avoid duplicates
        document.querySelectorAll('.cell-popup-editor').forEach(el => el.remove());

        const div = document.createElement('div');
        div.className = 'cell-popup-editor bg-white p-2 shadow rounded position-absolute';
        div.style.zIndex = 1001; // Higher than other elements
        div.style.width = `${width}px`;
        div.innerHTML = html;

        cell.style.position = 'relative';
        cell.appendChild(div);

        const input = div.querySelector('input, select');
        if (input) {
            setTimeout(() => { // Timeout to ensure element is in DOM and focusable
                if (input.select) input.select();
                input.focus();
            }, 10);
        }
        return div;
    },

    updateChange() {
        const tenderedEl = document.getElementById('amount-tendered');
        const changeEl = document.getElementById('change-display');
        if (!tenderedEl || !changeEl) return;

        const total = POS.totals ? POS.totals.grand_total : 0;
        const tendered = parseFloat(tenderedEl.value || 0);
        const change = Math.max(tendered - total, 0);

        changeEl.innerText = POS.config.currency + change.toLocaleString(undefined, { minimumFractionDigits: 2 });

        const saveWalletWrapper = document.getElementById('save-wallet-wrapper');
        const isExistingBuyer = window.POS_BUYER && window.POS_BUYER.buyer_id && !window.POS_BUYER.is_walkin;

        if (change > 0 && isExistingBuyer) {
            if (saveWalletWrapper) saveWalletWrapper.classList.remove('d-none');
        } else {
            if (saveWalletWrapper) saveWalletWrapper.classList.add('d-none');
            const saveWalletCheck = document.getElementById('save-change-wallet');
            if (saveWalletCheck) saveWalletCheck.checked = false;
        }

        if (tendered > 0 && tendered < total) {
            changeEl.classList.remove('text-success');
            changeEl.classList.add('text-danger');
            changeEl.innerText = "Short: " + POS.config.currency + (total - tendered).toFixed(2);
        } else {
            changeEl.classList.remove('text-danger');
            changeEl.classList.add('text-success');
        }
    },

    editPrice(cell, index) {
        const item = POS.cart[index];
        if (!item) return;

        const currentDiscount = item.manual_discount || 0;

        // Step 1: Show choice popup - Edit Price or Discount Price
        const html = `
            <label class="small fw-bold d-block text-center mb-2" > Choose Action</label>
            <div class="d-flex flex-column gap-1">
                <button class="btn btn-sm btn-primary w-100" id="btn-choice-edit">
                    <i class="fa-solid fa-pen me-1"></i> Edit Price
                </button>
                <button class="btn btn-sm btn-warning w-100" id="btn-choice-discount">
                    <i class="fa-solid fa-tag me-1"></i> Discount Price
                </button>
                <button class="btn btn-sm btn-info w-100" id="btn-choice-tier">
                    <i class="fa-solid fa-layer-group me-1"></i> Change Price Tier
                </button>
                <button class="btn btn-sm btn-outline-secondary w-100 mt-1" id="btn-choice-cancel">Cancel</button>
            </div>
        `;

        const choiceDiv = this._createCellPopup(cell, { html, width: 210 });

        // Choice: Edit Price directly
        choiceDiv.querySelector('#btn-choice-edit').onclick = () => {
            this._showEditPriceForm(cell, index, item);
        };

        // Choice: Discount Price
        choiceDiv.querySelector('#btn-choice-discount').onclick = () => {
            this._showDiscountForm(cell, index, item, currentDiscount);
        };

        // Choice: Change Price Tier
        choiceDiv.querySelector('#btn-choice-tier').onclick = () => {
            this._showTierChangeForm(cell, index, item);
        };

        // Cancel
        choiceDiv.querySelector('#btn-choice-cancel').onclick = () => {
            this.renderCart(); // Re-rendering removes the popup
        };
    },

    // Sub-form: Edit Price directly
    _showEditPriceForm(cell, index, item) {
        const html = `
            <label class="small fw-bold" > Set New Price(₱)</label>
            <input type="number" id="edit-price-input" class="form-control form-control-sm mb-2"
                value="${item.price.toFixed(2)}" step="0.01" min="0">
            <div class="d-flex justify-content-between">
                <button class="btn btn-sm btn-primary w-100 me-1" id="btn-save-price">Set Price</button>
                <button class="btn btn-sm btn-secondary w-100" id="btn-cancel-price">Cancel</button>
            </div>
        `;

        const formDiv = this._createCellPopup(cell, { html, width: 210 });
        const input = formDiv.querySelector('#edit-price-input');

        formDiv.querySelector('#btn-save-price').onclick = () => {
            const newPrice = parseFloat(input.value);
            if (!isNaN(newPrice) && newPrice >= 0) {
                const currentTier = CartManager.getActiveTier();
                const originalPrice = parseFloat(item.tiers[currentTier] || item.tiers.retail);

                // Set the price directly; calculate discount as difference from original
                item.manual_discount = newPrice; // Store the exact custom price here
                item.manual_discount_type = 'custom';

                const pricing = this.getDiscountedPrice(item);
                item.price = pricing.price;
                item.original_price = pricing.original_price;
                item.item_discount = pricing.discount;
                this.renderCart();
            }
        };

        formDiv.querySelector('#btn-cancel-price').onclick = () => {
            this.renderCart();
        };
    },

    // Sub-form: Apply discount amount (with percent/fixed toggle)
    _showDiscountForm(cell, index, item, currentDiscount) {
        const currentType = item.manual_discount_type || 'fixed';
        const html = `
            <label class="small fw-bold d-block mb-1">Manual Discount</label>
            <div class="btn-group w-100 mb-2" role="group">
                <input type="radio" class="btn-check" name="item-disc-type" id="item-disc-percent" value="percent" ${currentType === 'percent' ? 'checked' : ''}>
                <label class="btn btn-outline-dark btn-sm" for="item-disc-percent">% Percent</label>
                <input type="radio" class="btn-check" name="item-disc-type" id="item-disc-fixed" value="fixed" ${currentType === 'fixed' ? 'checked' : ''}>
                <label class="btn btn-outline-dark btn-sm" for="item-disc-fixed">₱ Fixed</label>
            </div>
            <input type="number" id="manual-disc-input" class="form-control form-control-sm mb-2"
                value="${currentDiscount}" step="0.01" min="0" placeholder="Enter value">
            <div class="d-flex justify-content-between">
                <button class="btn btn-sm btn-success w-100 me-1" id="btn-save-disc">Apply</button>
                <button class="btn btn-sm btn-secondary w-100" id="btn-cancel-disc">Cancel</button>
            </div>
        `;

        const formDiv = this._createCellPopup(cell, { html, width: 230 });
        const input = formDiv.querySelector('#manual-disc-input');

        formDiv.querySelector('#btn-save-disc').onclick = () => {
            const val = parseFloat(input.value);
            const discType = formDiv.querySelector('input[name="item-disc-type"]:checked')?.value || 'fixed';
            if (!isNaN(val) && val >= 0) {
                if (discType === 'percent' && val > 100) {
                    EllaToast.warning('Percent cannot exceed 100');
                    return;
                }
                item.manual_discount = val;
                item.manual_discount_type = discType;
                const pricing = this.getDiscountedPrice(item);
                item.price = pricing.price;
                item.original_price = pricing.original_price;
                item.item_discount = pricing.discount;
                this.renderCart();
            }
        };

        formDiv.querySelector('#btn-cancel-disc').onclick = () => {
            this.renderCart();
        };
    },

    // Sub-form: Change Price Tier for a specific item
    _showTierChangeForm(cell, index, item) {
        const currentItemTier = item.override_tier || CartManager.getActiveTier();
        // Show prices for each tier
        const srpPrice = parseFloat(item.tiers.retail || 0);
        const wsPrice = parseFloat(item.tiers.wholesale || 0);
        const dlrPrice = parseFloat(item.tiers.dealer || 0);
        const currency = POS.config.currency;

        const html = `
                        <label class="small fw-bold d-block text-center mb-2">
                            <i class="fa-solid fa-layer-group me-1"></i>Select Price Tier
                        </label>
                        <div class="d-flex flex-column gap-1">
                            <button class="btn btn-sm ${currentItemTier === 'retail' ? 'btn-success' : 'btn-outline-success'} w-100 text-start" data-tier="retail">
                                <div class="d-flex justify-content-between">
                                    <span><i class="fa-solid fa-${currentItemTier === 'retail' ? 'check-circle' : 'circle'} me-1"></i>SRP</span>
                                    <span class="fw-bold">${currency}${srpPrice.toFixed(2)}</span>
                                </div>
                            </button>
                            <button class="btn btn-sm ${currentItemTier === 'wholesale' ? 'btn-info' : 'btn-outline-info'} w-100 text-start" data-tier="wholesale" ${wsPrice <= 0 ? 'disabled' : ''}>
                                <div class="d-flex justify-content-between">
                                    <span><i class="fa-solid fa-${currentItemTier === 'wholesale' ? 'check-circle' : 'circle'} me-1"></i>WHOLESALE</span>
                                    <span class="fw-bold">${wsPrice > 0 ? currency + wsPrice.toFixed(2) : 'N/A'}</span>
                                </div>
                            </button>
                            <button class="btn btn-sm ${currentItemTier === 'dealer' ? 'btn-warning' : 'btn-outline-warning'} w-100 text-start" data-tier="dealer" ${dlrPrice <= 0 ? 'disabled' : ''}>
                                <div class="d-flex justify-content-between">
                                    <span><i class="fa-solid fa-${currentItemTier === 'dealer' ? 'check-circle' : 'circle'} me-1"></i>DEALER</span>
                                    <span class="fw-bold">${dlrPrice > 0 ? currency + dlrPrice.toFixed(2) : 'N/A'}</span>
                                </div>
                            </button>
                        </div>
                        <div class="d-flex gap-1 mt-2">
                            <button class="btn btn-sm btn-outline-danger w-50" id="btn-reset-tier">
                                <i class="fa-solid fa-rotate-left me-1"></i>Reset
                            </button>
                            <button class="btn btn-sm btn-outline-secondary w-50" id="btn-cancel-tier">Cancel</button>
                        </div>
        `;

        const formDiv = this._createCellPopup(cell, { html, width: 230 });

        // Tier selection buttons
        formDiv.querySelectorAll('[data-tier]').forEach(btn => {
            btn.onclick = () => {
                const selectedTier = btn.dataset.tier;
                const globalTier = CartManager.getActiveTier();

                // If selected tier matches global buyer tier, remove override
                if (selectedTier === globalTier) {
                    delete item.override_tier;
                } else {
                    item.override_tier = selectedTier;
                }

                // Clear any manual discount since we're changing tier
                item.manual_discount = 0;
                item.manual_discount_type = 'fixed';

                // Recalculate price
                const pricing = this.getDiscountedPrice(item);
                item.price = pricing.price;
                item.original_price = pricing.original_price;
                item.item_discount = pricing.discount;

                this.renderCart();
            };
        });

        // Reset to buyer's tier
        formDiv.querySelector('#btn-reset-tier').onclick = () => {
            delete item.override_tier;
            item.manual_discount = 0;
            item.manual_discount_type = 'fixed';
            const pricing = this.getDiscountedPrice(item);
            item.price = pricing.price;
            item.original_price = pricing.original_price;
            item.item_discount = pricing.discount;
            this.renderCart();
        };

        // Cancel
        formDiv.querySelector('#btn-cancel-tier').onclick = () => {
            this.renderCart();
        };
    }
};

// Expose CartManager globally
window.CartManager = CartManager;
