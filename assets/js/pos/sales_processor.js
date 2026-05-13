/**
 * =====================================================
 * SALES PROCESSOR MODULE
 * Dedicated module for handling POS sale transactions
 * =====================================================
 */

const SalesProcessor = {
    // API endpoint
    apiUrl: '../../api/pos/save_sale.php',

    /**
     * Main entry point - process a sale transaction
     */
    async processSale() {
        // 1. Validate cart
        if (!this.validateCart()) return;

        // 2. Confirm with user
        const confirmed = await EllaConfirm.show({
            title: 'Confirm Sale',
            message: 'Process this sale transaction?',
            confirmText: 'Confirm Sale',
            confirmClass: 'btn-success',
            icon: 'fa-cash-register',
            iconColor: 'text-success'
        });
        if (!confirmed) return;

        // 3. Show loading state
        this.setLoading(true);

        try {
            // 4. Build payload
            const payload = this.buildPayload();

            // 5. Send to API
            const response = await fetch(this.apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            // 6. Handle response
            if (data.success) {
                this.handleSuccess(data);
            } else {
                this.handleError(data.message || 'Transaction failed');
            }

        } catch (err) {
            console.error('Sale processing error:', err);
            this.handleError('Network error. Please check your connection.');
        } finally {
            this.setLoading(false);
        }
    },

    /**
     * Validate cart before processing
     */
    validateCart() {
        const cart = POS.cart || [];

        if (cart.length === 0) {
            EllaToast.warning('Cart is empty. Add items before processing.');
            return false;
        }

        // Check for zero quantity items
        const invalidItems = cart.filter(item => item.qty <= 0);
        if (invalidItems.length > 0) {
            EllaToast.warning('Some items have invalid quantity. Please check your cart.');
            return false;
        }

        // Check if amount tendered is sufficient for cash payments
        const paymentMethod = document.getElementById('payment-method')?.value || 'cash';
        if (paymentMethod === 'cash') {
            const total = POS.totals ? POS.totals.grand_total : this.calculateTotal();
            const tendered = parseFloat(document.getElementById('amount-tendered')?.value || 0);

            if (tendered < total) {
                const isExistingBuyer = window.POS_BUYER && window.POS_BUYER.buyer_id && !window.POS_BUYER.is_walkin;
                if (!isExistingBuyer) {
                    EllaToast.error(`Insufficient amount. Total is ₱${total.toFixed(2)}, tendered is ₱${tendered.toFixed(2)}`);
                    return false;
                }
            }
        } else if (paymentMethod === 'wallet') {
            const isExistingBuyer = window.POS_BUYER && window.POS_BUYER.buyer_id && !window.POS_BUYER.is_walkin;
            if (!isExistingBuyer) {
                EllaToast.error('Walk-in customers cannot pay using Wallet Balance.');
                return false;
            }
        }

        return true;
    },

    /**
     * Calculate cart total
     */
    calculateTotal() {
        return (POS.cart || []).reduce((sum, item) => sum + (item.qty * item.price), 0);
    },

    /**
     * Build the API payload with all required data
     */
    buildPayload() {
        const cart = POS.cart || [];
        const buyer = window.POS_BUYER || {};
        
        const total = POS.totals ? POS.totals.subtotal : this.calculateTotal();
        const discountTotal = POS.totals ? (POS.totals.transaction_discount || 0) : 0;
        const grandTotal = POS.totals ? POS.totals.grand_total : total;
        
        const paymentMethod = document.getElementById('payment-method')?.value || 'cash';
        let tendered = parseFloat(document.getElementById('amount-tendered')?.value || 0);
        
        // Default tendered to grand total if using wallet and input is 0
        if (paymentMethod === 'wallet' && tendered === 0) {
            tendered = grandTotal;
        }

        const change = Math.max(tendered - grandTotal, 0);

        return {
            // Cart items with full details
            items: cart.map(item => ({
                variation_id: item.variation_id,
                unit_id: item.unit_id || null,
                multiplier: item.multiplier || 1,
                product_name: item.name,
                brand_name: item.brand || null,
                variation_name: item.variation || null,
                unit_type: item.unit_type || 'pc',
                barcode: item.barcode || null,
                price: item.price,
                quantity: item.qty,
                subtotal: item.price * item.qty
            })),

            // Full buyer details (snapshot at time of sale)
            buyer: {
                buyer_id: buyer.buyer_id || null,
                buyer_name: buyer.buyer_name || 'Walk-in Customer',
                shop_name: buyer.shop_name || buyer.shop || null,
                address: buyer.address || null,
                contact_number: buyer.contact_number || null,
                price_tier: buyer.price_tier || 'retail',
                is_walkin: buyer.is_walkin ? 1 : (buyer.buyer_id ? 0 : 1)
            },

            // Payment details
            payment: {
                method: paymentMethod,
                amount_tendered: tendered,
                change_due: change,
                reference_no: document.getElementById('payment-reference')?.value || null,
                save_to_wallet: document.getElementById('save-change-wallet')?.checked || false
            },

            // Totals
            subtotal: total,
            tax_amount: 0,  // Adjust if you have tax calculations
            discount_amount: discountTotal,  // Adjust if you have discounts
            grand_total: grandTotal,

            // Remarks (if field exists)
            remarks: document.getElementById('sale-remarks')?.value || null
        };
    },

    /**
     * Handle successful sale
     */
    handleSuccess(data) {
        console.log('Sale completed:', data);

        // Show success message
        EllaToast.success(`Sale completed! Reference: ${data.sale_ref || data.sale_id}`);

        // Preview/print receipt
        if (typeof previewReceipt === 'function') {
            previewReceipt();
        }

        // Clear cart and persisted state
        POS.cart = [];
        if (typeof POS.clearState === 'function') POS.clearState();

        // Clear tendered amount
        const tenderedInput = document.getElementById('amount-tendered');
        if (tenderedInput) tenderedInput.value = '';

        // Re-render cart
        if (typeof renderCart === 'function') {
            renderCart();
        }

        // Optional: Reset buyer to walk-in after sale
        // if (typeof CustomerSelector !== 'undefined') {
        //     CustomerSelector.resetToWalkin();
        // }
    },

    /**
     * Handle sale error
     */
    handleError(message) {
        console.error('Sale error:', message);
        EllaToast.error(`Transaction Failed: ${message}`);
    },

    /**
     * Set loading state on pay button
     */
    setLoading(loading) {
        const btn = document.getElementById('btn-pay');
        if (!btn) return;

        if (loading) {
            btn._originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        } else {
            btn.disabled = false;
            btn.innerHTML = btn._originalText || 'PAY <i class="fa-solid fa-chevron-right ms-1"></i>';
        }
    }
};

// Export for global use
window.SalesProcessor = SalesProcessor;

// Also provide backward compatibility with existing processPayment calls
window.processPayment = function () {
    SalesProcessor.processSale();
};
