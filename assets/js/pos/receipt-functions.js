/* =====================================================
   RECEIPT & PAYMENT FUNCTIONS
   Handle receipt preview and payment processing
===================================================== */

const ReceiptFunctions = {
  previewReceipt() {
    if (!POS.cart.length) return;

    const buyer = window.POS_BUYER;
    const select = document.getElementById("paper-size-select");

    // Check if ReceiptPreview loaded
    if (typeof ReceiptPreview === "undefined") {
      EllaToast.error('Receipt module not loaded');
      return;
    }

    const globalDisc = SimpleCheckout.globalDiscount || { type: 'percent', value: 0 };
    const cartTotal = POS.cart.reduce((s, i) => s + i.qty * i.price, 0);
    let globalDiscountAmount = 0;
    if (globalDisc.value > 0) {
      globalDiscountAmount = globalDisc.type === 'percent'
        ? cartTotal * (globalDisc.value / 100)
        : globalDisc.value;
    }

    const paymentMethod = document.getElementById("payment-method")?.value || "cash";
    let mix_details = [];
    let financing_provider = null;

    if (paymentMethod === 'mix') {
      mix_details = PaymentMethodHandler.getMixPaymentData() || [];
    } else if (['financing', 'home_credit'].includes(paymentMethod)) {
      const hcData = PaymentMethodHandler.getHomeCreditData();
      if (hcData) {
        financing_provider = hcData.provider || 'Home Credit';
        if (hcData.down_payment > 0) {
          if (hcData.dp_method === 'mix' && hcData.mix_details) {
            hcData.mix_details.forEach(mixItem => {
              mix_details.push({
                method: mixItem.method,
                amount: parseFloat(mixItem.amount),
                ref: 'DP-' + (hcData.reference_no || ''),
                provider: financing_provider
              });
            });
          } else {
            mix_details.push({
              method: hcData.dp_method || 'cash',
              amount: parseFloat(hcData.down_payment),
              ref: 'DP-' + (hcData.reference_no || ''),
              provider: financing_provider
            });
          }
        }
        const cartTotalActual = POS.totals ? POS.totals.grand_total : cartTotal;
        const financedAmount = cartTotalActual - parseFloat(hcData.down_payment);
        if (financedAmount > 0) {
          mix_details.push({
            method: 'financing',
            amount: financedAmount,
            ref: hcData.reference_no || '',
            provider: financing_provider
          });
        }
      }
    }

    const receiptData = {
      cart: POS.cart,
      globalDiscount: globalDiscountAmount,
      buyer: {
        name: buyer.buyer_name,
        shop: buyer.shop || "",
        price_tier: buyer.price_tier || "retail",
        address: buyer.address || "",
      },
      payment: {
        method: paymentMethod,
        amount: parseFloat(
          document.getElementById("amount-tendered")?.value || 0,
        ),
        mix_details: mix_details,
        financing_provider: financing_provider
      },
      user: window.CURRENT_USER_NAME || "Staff",
    };

    ReceiptPreview.openWindow(receiptData, select ? select.value : "thermal80");
  },

  updatePreviewFormat() {
    // Format changes apply on next preview
    // No action needed since we use window.open() not modal
  },

  async processPayment() {
    if (!POS.cart.length) return;

    const grandTotal = POS.totals ? POS.totals.grand_total : POS.cart.reduce((s, i) => s + i.qty * i.price, 0);
    const paymentMethod = document.getElementById("payment-method").value;
    const isPayLater = paymentMethod === "pay_later";
    const isHomeCredit = paymentMethod === "financing";

    // Fix: Capture Walk-in Name explicitly
    if (window.POS_BUYER.is_walkin) {
      const walkinInput = document.getElementById("buyer-name");
      if (walkinInput) {
        window.POS_BUYER.buyer_name =
          walkinInput.value.trim() || "Walk-in Customer";
      }
    }

    // Validate pay later and mix fields if applicable
    if (!PaymentMethodHandler.validate()) {
      return;
    }
    // Validate wallet supplement if active
    if (window.WalletSupplement && !WalletSupplement.validate()) {
      return;
    }

    let amountTendered = 0;
    let changeDue = 0;
    let shortfallAmt = 0; // Track shortfall for receipt
    let shortfallAsCredit = false; // Track if shortfall should be recorded as credit
    const isMix = paymentMethod === "mix";
    const isExistingBuyer = window.POS_BUYER && window.POS_BUYER.buyer_id && !window.POS_BUYER.is_walkin;
    const walletSupplementAmt = (window.WalletSupplement ? WalletSupplement.getUsedAmount() : 0);

    if (!isPayLater && !isMix && !isHomeCredit) {
      amountTendered = parseFloat(
        document.getElementById("amount-tendered").value || 0,
      );

      // Allow underpayment for existing buyers (shortfall deducted from wallet)
      // BUT if wallet supplement is active, the combined coverage must meet or exceed total
      const effectiveTendered = amountTendered + walletSupplementAmt;
      
      // Fixed: Use rounded values for comparison to avoid floating-point precision bugs (e.g. 100.00 < 100.00000001)
      const roundedTendered = Math.round(effectiveTendered * 100);
      const roundedTotal = Math.round(grandTotal * 100);

      if (roundedTendered < roundedTotal && !isExistingBuyer) {
        const shortAmount = (grandTotal - effectiveTendered).toFixed(2);
        EllaToast.error(
          `Insufficient payment! Total: ₱${grandTotal.toFixed(2)}, Tendered: ₱${effectiveTendered.toFixed(2)}, Short: ₱${shortAmount}`
        );
        document.getElementById("amount-tendered").focus();
        return;
      }

      // ── Shortfall Handler for existing buyers ──
      if (roundedTendered < roundedTotal && isExistingBuyer) {
        shortfallAmt = grandTotal - effectiveTendered;

        // Fetch live wallet balance
        let liveBalance = parseFloat(window.POS_BUYER.wallet_balance || 0);
        try {
          const wRes = await fetch(`../../api/buyers/get_wallet_balance.php?buyer_id=${window.POS_BUYER.buyer_id}`);
          const wData = await wRes.json();
          if (wData.success) liveBalance = parseFloat(wData.balance || 0);
        } catch (e) { /* use cached balance */ }

        // Show 3-choice dialog: Use Wallet / Record as Credit / Cancel
        const fmt = (v) => Math.abs(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const shortfallChoice = await this._showShortfallOptions({
          buyerName: window.POS_BUYER.buyer_name || 'Buyer',
          grandTotal, amountTendered: effectiveTendered,
          shortfall: shortfallAmt,
          walletBalance: liveBalance,
          fmt
        });

        if (!shortfallChoice) {
          // Cancelled — go back
          document.getElementById("amount-tendered").focus();
          return;
        }

        if (shortfallChoice === 'wallet') {
          // Show the detailed wallet debit confirmation
          const balanceAfter = liveBalance - shortfallAmt;
          const isNegative = balanceAfter < 0;
          const walletConfirmed = await this._showWalletConfirm({
            mode: 'shortfall',
            buyerName: window.POS_BUYER.buyer_name || 'Buyer',
            grandTotal, amountTendered: effectiveTendered,
            shortfall: shortfallAmt,
            currentBalance: liveBalance,
            balanceAfter, isNegative, fmt
          });
          if (!walletConfirmed) {
            document.getElementById("amount-tendered").focus();
            return;
          }
          // shortfallAmt is already set — backend will debit wallet
        } else if (shortfallChoice === 'credit') {
          // Mark the shortfall as a credit (receivable) — backend will create pending row
          shortfallAsCredit = true;
          // Note: we don't zero shortfallAmt here anymore so the receipt can display it
        }
      }

      changeDue = Math.max(effectiveTendered - grandTotal, 0);

      // ── Wallet Overpayment Confirmation ──
      const saveToWallet = document.getElementById('save-change-wallet')?.checked || false;
      if (changeDue > 0 && saveToWallet && isExistingBuyer) {
        // Fetch live wallet balance
        let liveBalance = parseFloat(window.POS_BUYER.wallet_balance || 0);
        try {
          const wRes = await fetch(`../../api/buyers/get_wallet_balance.php?buyer_id=${window.POS_BUYER.buyer_id}`);
          const wData = await wRes.json();
          if (wData.success) liveBalance = parseFloat(wData.balance || 0);
        } catch (e) { }

        const balanceAfter = liveBalance + changeDue;
        const fmt = (v) => Math.abs(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const walletConfirmed = await this._showWalletConfirm({
          mode: 'overpayment',
          buyerName: window.POS_BUYER.buyer_name || 'Buyer',
          changeDue,
          currentBalance: liveBalance,
          balanceAfter, fmt
        });

        if (!walletConfirmed) return;
      }
    }

    // ── Wallet Full Payment Confirmation ──
    if (paymentMethod === 'wallet' && isExistingBuyer) {
      // Fetch live wallet balance
      let liveBalance = parseFloat(window.POS_BUYER.wallet_balance || 0);
      try {
        const wRes = await fetch(`../../api/buyers/get_wallet_balance.php?buyer_id=${window.POS_BUYER.buyer_id}`);
        const wData = await wRes.json();
        if (wData.success) liveBalance = parseFloat(wData.balance || 0);
      } catch (e) { }

      const balanceAfter = Math.round((liveBalance - grandTotal) * 100) / 100;
      const isNegative = balanceAfter < -0.001; // Small threshold for float precision
      const fmt = (v) => Math.abs(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

      const walletConfirmed = await this._showWalletConfirm({
        mode: 'payment',
        buyerName: window.POS_BUYER.buyer_name || 'Buyer',
        grandTotal,
        currentBalance: liveBalance,
        balanceAfter, isNegative, fmt
      });

      if (!walletConfirmed) return;
      amountTendered = grandTotal;
      changeDue = 0;
    }

    const confirmed = await EllaConfirm.show({
      title: isPayLater ? 'Scheduled Payment' : 'Confirm Sale',
      message: isPayLater
        ? 'This sale will be recorded as "Pay Later". Customer must pay on the scheduled date.'
        : 'Process this sale transaction?',
      confirmText: isPayLater ? 'Confirm Schedule' : 'Confirm Sale',
      confirmClass: 'btn-success',
      icon: isPayLater ? 'fa-calendar-check' : 'fa-cash-register',
      iconColor: isPayLater ? 'text-warning' : 'text-success'
    });
    if (!confirmed) return;

    const btn = document.getElementById("btn-pay");
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    // Map cart items to the structure expected by save_sale.php
    const items = POS.cart.map((item) => ({
      variation_id: item.variation_id,
      unit_id: item.unit_id || null,
      multiplier: item.multiplier || 1,
      product_name: item.name,
      brand_name: item.brand,
      variation_name: item.variation,
      unit_type: item.unit_type,
      barcode: item.barcode,
      price: item.price,
      original_price: item.original_price, // New
      item_discount: item.item_discount,   // New
      quantity: item.qty,
    }));

    // Build payment object
    const saveToWallet = document.getElementById('save-change-wallet')?.checked || false;
    const payment = {
      method: paymentMethod,
      amount_tendered: amountTendered,
      change_due: changeDue,
      reference_no: null,
      save_to_wallet: saveToWallet,
      wallet_supplement_amount: walletSupplementAmt,
      shortfall_as_credit: shortfallAsCredit,
    };

    // Add pay later data if applicable
    if (isPayLater) {
      const payLaterData = PaymentMethodHandler.getPayLaterData();
      payment.pay_later = payLaterData;
    }

    // Add mix payment data
    if (isMix) {
      payment.mix_details = PaymentMethodHandler.getMixPaymentData();
    }

    // Add home credit data
    if (isHomeCredit) {
      const hcData = PaymentMethodHandler.getHomeCreditData();
      payment.financing = hcData;
      payment.reference_no = hcData.reference_no;
    }

    const payload = {
      items: items,
      buyer: {
        buyer_id: window.POS_BUYER.buyer_id,
        buyer_name: window.POS_BUYER.buyer_name,
        shop_name: window.POS_BUYER.shop || "",
        address: window.POS_BUYER.address || "",
        contact_number: window.POS_BUYER.contact_number || "",
        price_tier: window.POS_BUYER.price_tier || "retail",
        is_walkin: window.POS_BUYER.is_walkin ? 1 : (window.POS_BUYER.buyer_id ? 0 : 1),
      },
      subtotal: POS.totals ? POS.totals.subtotal : grandTotal,
      grand_total: POS.totals ? POS.totals.grand_total : grandTotal,
      tax_amount: 0,
      discount_amount: POS.totals ? POS.totals.discount_amount : 0,
      payment: payment,
      remarks: isPayLater ? "Pay Later" : null,
    };

    fetch("../../api/pos/save_sale.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          // Notify cashier if wallet balance was applied or credited
          const walletUsed = walletSupplementAmt || 0;
          const savedToWallet = saveToWallet && changeDue > 0;
          if (walletUsed > 0) {
            const buyerName = window.POS_BUYER?.buyer_name || 'customer';
            EllaToast.success(`\u20b1${walletUsed.toFixed(2)} deducted from ${buyerName}'s wallet balance.`);
          }
          if (savedToWallet) {
            const buyerName = window.POS_BUYER?.buyer_name || 'customer';
            setTimeout(() => {
              EllaToast.info(`\u20b1${changeDue.toFixed(2)} change saved to ${buyerName}'s wallet.`);
            }, 800);
          }

          // Show receipt with the actual sale reference and date from the database
          this.showReceiptWithRef(data.sale_ref, data.created_at, {
            wallet_supplement: walletSupplementAmt,
            saved_to_wallet: (saveToWallet && changeDue > 0) ? changeDue : 0,
            shortfall_deducted: !shortfallAsCredit ? (shortfallAmt || 0) : 0,
            shortfall_as_credit: shortfallAsCredit ? (shortfallAmt || 0) : 0,
            paid_by_wallet: (paymentMethod === 'wallet') ? grandTotal : 0,
          });
          POS.cart = [];
          if (typeof POS.clearState === 'function') POS.clearState();
          document.getElementById("amount-tendered").value = "";

          // Reset wallet supplement panel for next transaction
          if (window.WalletSupplement) WalletSupplement._reset();

          if (isMix) {
            document.querySelectorAll('.mix-amount-input').forEach(input => input.value = '');
          }

          // Refresh page to update stock levels
          setTimeout(() => {
            window.location.reload();
          }, 500);
        } else {
          EllaToast.error('Transaction Failed: ' + (data.message || 'Unknown Error'));
        }
      })
      .catch((err) => {
        console.error(err);
        // Queue sale for offline retry if OfflineQueue is available
        if (window.OfflineQueue && (err.name === 'TypeError' || !navigator.onLine)) {
          OfflineQueue.enqueue(payload);
        } else {
          EllaToast.error('Server Connection Error');
        }
      })
      .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
      });
  },

  /**
   * Show receipt with the actual sale reference from the database
   */
  showReceiptWithRef(saleRef, createdAt = null, walletOpts = {}) {
    if (!POS.cart.length && !saleRef) return;

    const buyer = window.POS_BUYER;
    const select = document.getElementById("paper-size-select");

    if (typeof ReceiptPreview === "undefined") {
      EllaToast.error('Receipt module not loaded');
      return;
    }

    const paymentMethod = document.getElementById("payment-method")?.value || "cash";
    let mix_details = [];
    let financing_provider = null;

    if (paymentMethod === 'mix') {
      mix_details = PaymentMethodHandler.getMixPaymentData() || [];
    } else if (['financing', 'home_credit'].includes(paymentMethod)) {
      const hcData = PaymentMethodHandler.getHomeCreditData();
      if (hcData) {
        financing_provider = hcData.provider || 'Home Credit';
        if (hcData.down_payment > 0) {
          if (hcData.dp_method === 'mix' && hcData.mix_details) {
            hcData.mix_details.forEach(mixItem => {
              mix_details.push({
                method: mixItem.method,
                amount: parseFloat(mixItem.amount),
                ref: 'DP-' + (hcData.reference_no || saleRef),
                provider: financing_provider
              });
            });
          } else {
            mix_details.push({
              method: hcData.dp_method || 'cash',
              amount: parseFloat(hcData.down_payment),
              ref: 'DP-' + (hcData.reference_no || saleRef),
              provider: financing_provider
            });
          }
        }
        const cartTotalActual = POS.totals ? POS.totals.grand_total : POS.cart.reduce((s, i) => s + i.qty * i.price, 0);
        const financedAmount = cartTotalActual - parseFloat(hcData.down_payment);
        if (financedAmount > 0) {
          mix_details.push({
            method: 'financing',
            amount: financedAmount,
            ref: hcData.reference_no || saleRef,
            provider: financing_provider
          });
        }
      }
    }

    const receiptData = {
      cart: POS.cart,
      buyer: {
        name: buyer.buyer_name,
        shop: buyer.shop || "",
        price_tier: buyer.price_tier || "retail",
        address: buyer.address || "",
      },
      payment: {
        method: paymentMethod,
        amount: parseFloat(
          document.getElementById("amount-tendered")?.value || 0,
        ),
        reference: saleRef,
        mix_details: mix_details,
        financing_provider: financing_provider,
        // Wallet transaction details for receipt
        wallet_supplement: walletOpts.wallet_supplement || 0,
        saved_to_wallet: walletOpts.saved_to_wallet || 0,
        paid_by_wallet: walletOpts.paid_by_wallet || 0,
        shortfall_deducted: walletOpts.shortfall_deducted || 0,
        shortfall_as_credit: walletOpts.shortfall_as_credit || 0,
      },
      user: window.CURRENT_USER_NAME || "Staff",
      date: createdAt,
      globalDiscount: POS.totals ? POS.totals.transaction_discount : 0,
    };

    ReceiptPreview.openWindow(receiptData, select ? select.value : "thermal80");
  },

  /**
   * Show a styled wallet shortfall confirmation dialog.
   * Returns a Promise that resolves to true (confirmed) or false (cancelled).
   */
  /**
   * Show a 3-choice shortfall dialog: Use Wallet / Record as Credit / Cancel.
   * Returns: 'wallet', 'credit', or null (cancelled).
   */
  _showShortfallOptions({ buyerName, grandTotal, amountTendered, shortfall, walletBalance, fmt }) {
    return new Promise((resolve) => {
      const existing = document.getElementById('shortfallOptionsModal');
      if (existing) existing.remove();

      const hasPositiveWallet = walletBalance > 0;
      const walletCoversAll = walletBalance >= shortfall;

      const modalEl = document.createElement('div');
      modalEl.className = 'modal fade';
      modalEl.id = 'shortfallOptionsModal';
      modalEl.setAttribute('tabindex', '-1');
      modalEl.setAttribute('data-bs-backdrop', 'static');
      modalEl.innerHTML = `
        <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
          <div class="modal-content border-0 shadow-lg" style="border-radius:18px; overflow:hidden;">
            <div class="modal-body p-0">
              <!-- Header -->
              <div style="background:linear-gradient(135deg,#fff7ed,#ffedd5); padding:20px 24px 16px; text-align:center;">
                <div style="width:56px; height:56px; background:#f97316; border-radius:16px; display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;">
                  <i class="fa-solid fa-triangle-exclamation" style="font-size:1.6rem; color:#fff;"></i>
                </div>
                <h5 style="font-weight:800; color:#1e293b; margin:0 0 4px;">Insufficient Payment</h5>
                <p style="font-size:0.82rem; color:#64748b; margin:0;">How would you like to handle the shortfall?</p>
              </div>
              <!-- Summary -->
              <div style="padding:16px 24px 8px; border-bottom:1px solid #f1f5f9;">
                <div style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:0.82rem;">
                  <span style="color:#64748b;">Buyer</span>
                  <span style="font-weight:700; color:#1e293b;">${this._escHtml(buyerName)}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:0.82rem;">
                  <span style="color:#64748b;">Total</span>
                  <span style="font-weight:600;">₱${fmt(grandTotal)}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:0.82rem;">
                  <span style="color:#64748b;">Tendered</span>
                  <span style="font-weight:600;">₱${fmt(amountTendered)}</span>
                </div>
                <div style="display:flex; justify-content:space-between; padding-top:8px; border-top:2px dashed #fee2e2;">
                  <span style="font-weight:700; color:#dc2626;">Shortfall</span>
                  <span style="font-weight:800; color:#dc2626; font-size:1.05rem;">₱${fmt(shortfall)}</span>
                </div>
              </div>
              <!-- Choices -->
              <div style="padding:16px 24px 8px;">
                <p style="font-size:0.78rem; color:#94a3b8; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:10px;">Choose an Option</p>

                ${hasPositiveWallet ? `
                <!-- Option 1: Use Wallet -->
                <button id="sfo-wallet" style="
                  width:100%; border:2px solid ${walletCoversAll ? '#16a34a' : '#f59e0b'};
                  background:${walletCoversAll ? '#f0fdf4' : '#fffbeb'};
                  border-radius:12px; padding:12px 16px; margin-bottom:10px;
                  cursor:pointer; text-align:left; transition:all 0.2s;"
                  onmouseover="this.style.filter='brightness(0.97)'" onmouseout="this.style.filter=''">
                  <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; align-items:center; gap:10px;">
                      <i class="fa-solid fa-wallet" style="color:${walletCoversAll ? '#16a34a' : '#f59e0b'}; font-size:1.1rem;"></i>
                      <div>
                        <div style="font-weight:700; color:#1e293b; font-size:0.88rem;">Debit from Wallet</div>
                        <div style="font-size:0.75rem; color:#64748b;">
                          Wallet Balance: <strong style="color:${walletCoversAll ? '#16a34a' : '#f59e0b'};">₱${fmt(walletBalance)}</strong>
                          ${!walletCoversAll ? '<br><span style="color:#f59e0b;">⚠ Balance is not enough — will go negative</span>' : ''}
                        </div>
                      </div>
                    </div>
                    <i class="fa-solid fa-chevron-right" style="color:#94a3b8;"></i>
                  </div>
                </button>` : `
                <!-- Wallet disabled -->
                <div style="
                  width:100%; border:2px solid #e2e8f0;
                  background:#f8fafc;
                  border-radius:12px; padding:12px 16px; margin-bottom:10px;
                  opacity:0.55; text-align:left;">
                  <div style="display:flex; align-items:center; gap:10px;">
                    <i class="fa-solid fa-wallet" style="color:#94a3b8; font-size:1.1rem;"></i>
                    <div>
                      <div style="font-weight:700; color:#94a3b8; font-size:0.88rem;">Debit from Wallet</div>
                      <div style="font-size:0.75rem; color:#94a3b8;">Wallet Balance: ₱${fmt(walletBalance)} — not available</div>
                    </div>
                  </div>
                </div>`}

                <!-- Option 2: Record as Credit -->
                <button id="sfo-credit" style="
                  width:100%; border:2px solid #6366f1;
                  background:#eef2ff;
                  border-radius:12px; padding:12px 16px; margin-bottom:10px;
                  cursor:pointer; text-align:left; transition:all 0.2s;"
                  onmouseover="this.style.filter='brightness(0.97)'" onmouseout="this.style.filter=''">
                  <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="display:flex; align-items:center; gap:10px;">
                      <i class="fa-solid fa-file-invoice-dollar" style="color:#6366f1; font-size:1.1rem;"></i>
                      <div>
                        <div style="font-weight:700; color:#1e293b; font-size:0.88rem;">Record as Credit (Receivable)</div>
                        <div style="font-size:0.75rem; color:#64748b;">₱${fmt(shortfall)} will be added to outstanding balance</div>
                      </div>
                    </div>
                    <i class="fa-solid fa-chevron-right" style="color:#94a3b8;"></i>
                  </div>
                </button>

                <!-- Cancel -->
                <button id="sfo-cancel" style="
                  width:100%; border:2px solid #e2e8f0;
                  background:#fff;
                  border-radius:12px; padding:10px 16px;
                  cursor:pointer; text-align:center; color:#64748b; font-size:0.85rem; font-weight:600;
                  transition:all 0.2s;"
                  onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                  <i class="fa-solid fa-xmark me-1"></i>Cancel — Go Back & Fix Amount
                </button>
              </div>
            </div>
          </div>
        </div>
      `;

      document.body.appendChild(modalEl);
      const bsModal = new bootstrap.Modal(modalEl);

      let resolved = null;

      if (hasPositiveWallet) {
        modalEl.querySelector('#sfo-wallet').addEventListener('click', () => {
          resolved = 'wallet'; bsModal.hide();
        });
      }
      modalEl.querySelector('#sfo-credit').addEventListener('click', () => {
        resolved = 'credit'; bsModal.hide();
      });
      modalEl.querySelector('#sfo-cancel').addEventListener('click', () => {
        resolved = null; bsModal.hide();
      });
      modalEl.addEventListener('hidden.bs.modal', () => {
        resolve(resolved);
        modalEl.remove();
      });

      bsModal.show();
    });
  },

  /**
   * Show a styled wallet confirmation dialog (Shortfall, Overpayment, or Payment).
   * Returns a Promise that resolves to true (confirmed) or false (cancelled).
   */
  _showWalletConfirm({ mode, buyerName, grandTotal, amountTendered, shortfall, changeDue, currentBalance, balanceAfter, isNegative, fmt }) {
    return new Promise((resolve) => {
      // Remove any existing modal
      const existing = document.getElementById('walletConfirmModal');
      if (existing) existing.remove();

      let title = "Wallet Transaction";
      let subtitle = "Confirm wallet activity for this buyer.";
      let icon = "fa-wallet";
      let iconBg = "#fcd34d"; // Default amber
      let highlightLabel = "Shortfall";
      let highlightValue = shortfall;
      let highlightColor = "#dc2626"; // Default red
      let highlightBg = "#fef2f2";
      let highlightBorder = "#fca5a5";
      let highlightText = "This amount will be debited from the buyer's wallet.";

      if (mode === 'shortfall') {
        title = "Wallet Shortfall";
        subtitle = "Payment is short — the difference will be debited from wallet.";
        highlightLabel = "SHORTFALL DEBIT";
      } else if (mode === 'overpayment') {
        title = "Save to Wallet";
        subtitle = "Overpayment detected — save change to buyer wallet?";
        icon = "fa-coins";
        iconBg = "#22c55e"; // Green
        highlightLabel = "CHANGE TO SAVE";
        highlightValue = changeDue;
        highlightColor = "#16a34a"; // Green
        highlightBg = "#f0fdf4";
        highlightBorder = "#bbf7d0";
        highlightText = "This amount will be added to the buyer's wallet balance.";
      } else if (mode === 'payment') {
        title = "Wallet Payment";
        subtitle = "Using wallet balance as the primary payment method.";
        icon = "fa-credit-card";
        iconBg = "#6366f1"; // Indigo
        highlightLabel = "PAYMENT AMOUNT";
        highlightValue = grandTotal;
        highlightColor = "#4338ca";
        highlightBg = "#eef2ff";
        highlightBorder = "#c7d2fe";
        highlightText = "This amount will be deducted from the buyer's wallet.";
      }

      const modalEl = document.createElement('div');
      modalEl.className = 'modal fade';
      modalEl.id = 'walletConfirmModal';
      modalEl.setAttribute('tabindex', '-1');
      modalEl.setAttribute('data-bs-backdrop', 'static');
      modalEl.innerHTML = `
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
          <div class="modal-content border-0 shadow-lg" style="border-radius:18px; overflow:hidden;">
            <div class="modal-body p-0">
              <!-- Header -->
              <div style="background:linear-gradient(135deg, ${highlightBg}, #fff); padding:20px 24px 16px; text-align:center;">
                <div style="width:56px; height:56px; background:${iconBg}; border-radius:16px; display:inline-flex; align-items:center; justify-content:center; margin-bottom:12px;">
                  <i class="fa-solid ${icon}" style="font-size:1.6rem; color:#fff;"></i>
                </div>
                <h5 style="font-weight:800; color:#1e293b; margin:0 0 4px;">${title}</h5>
                <p style="font-size:0.82rem; color:#64748b; margin:0;">${subtitle}</p>
              </div>
              <!-- Details -->
              <div style="padding:20px 24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                  <span style="font-size:0.82rem; color:#64748b;">Buyer</span>
                  <span style="font-weight:700; color:#1e293b;">${this._escHtml(buyerName)}</span>
                </div>
                
                ${grandTotal && mode !== 'overpayment' ? `
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                  <span style="font-size:0.82rem; color:#64748b;">Transaction Total</span>
                  <span style="font-weight:600;">₱${fmt(grandTotal)}</span>
                </div>` : ''}

                <!-- Highlight Box -->
                <div style="background:${highlightBg}; border:2px solid ${highlightBorder}; border-radius:12px; padding:12px 16px; margin-bottom:14px;">
                  <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-weight:700; color:${highlightColor}; font-size:0.9rem;">
                      ${highlightLabel}
                    </span>
                    <span style="font-weight:800; color:${highlightColor}; font-size:1.15rem;">₱${fmt(highlightValue)}</span>
                  </div>
                  <div style="font-size:0.75rem; color:${highlightColor}; margin-top:4px; opacity:0.8;">
                    ${highlightText}
                  </div>
                </div>

                <!-- Wallet balance info -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                  <span style="font-size:0.82rem; color:#64748b;">Current Wallet Balance</span>
                  <span style="font-weight:600; color:${currentBalance >= 0 ? '#16a34a' : '#dc2626'};">
                    ${currentBalance < 0 ? '-' : ''}₱${fmt(currentBalance)}
                  </span>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:1px dashed #e2e8f0;">
                  <span style="font-weight:700; font-size:0.88rem; color:#1e293b;">Wallet After</span>
                  <span style="font-weight:800; font-size:1.05rem; color:${balanceAfter < 0 ? '#dc2626' : '#16a34a'};">
                    ${balanceAfter < 0 ? '-' : ''}₱${fmt(balanceAfter)}
                  </span>
                </div>
                ${isNegative ? `
                <div style="background:#fef2f2; border-radius:8px; padding:8px 12px; margin-top:10px; display:flex; align-items:center; gap:8px;">
                  <i class="fa-solid fa-circle-exclamation" style="color:#dc2626; font-size:1rem;"></i>
                  <span style="font-size:0.78rem; color:#991b1b; font-weight:600;">
                    Warning: Balance will go negative (₱${fmt(balanceAfter)})
                  </span>
                </div>` : ''}
              </div>
            </div>
            <div class="modal-footer border-0 justify-content-center gap-2 pb-4 pt-0 px-4">
              <button type="button" class="btn btn-outline-secondary px-4 flex-grow-1" id="wf-cancel">
                <i class="fa-solid fa-xmark me-1"></i>Cancel
              </button>
              <button type="button" class="btn btn-warning text-dark px-4 flex-grow-1 fw-bold" id="wf-confirm" style="background:${iconBg}; border-color:${iconBg}; color:#fff !important;">
                Proceed
              </button>
            </div>
          </div>
        </div>
      `;

      document.body.appendChild(modalEl);
      const bsModal = new bootstrap.Modal(modalEl);

      let resolved = false;
      modalEl.querySelector('#wf-confirm').addEventListener('click', () => {
        resolved = true;
        bsModal.hide();
      });
      modalEl.querySelector('#wf-cancel').addEventListener('click', () => {
        resolved = false;
        bsModal.hide();
      });
      modalEl.addEventListener('hidden.bs.modal', () => {
        resolve(resolved);
        modalEl.remove();
      });

      bsModal.show();
    });
  },

  /** Escape HTML for safe injection into templates */
  _escHtml(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
  },
};

// Expose ReceiptFunctions globally
window.ReceiptFunctions = ReceiptFunctions;
