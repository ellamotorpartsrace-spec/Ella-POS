/* =====================================================
   PAYMENT METHOD HANDLER
   Toggle Pay Later and Mix Payment fields visibility
===================================================== */

const PaymentMethodHandler = {
    init() {
        this.els = {
            paymentMethod: document.getElementById('payment-method'),
            tenderedWrapper: document.getElementById('tendered-wrapper'),
            changeWrapper: document.getElementById('change-wrapper'),
            payLaterWrapper: document.getElementById('pay-later-wrapper'),
            payLaterContainer: document.getElementById('pay-later-rows-container'),
            payLaterTotalDisplay: document.getElementById('pay-later-total-display'),
            payLaterRemainingDisplay: document.getElementById('pay-later-remaining-display'),
            mixPaymentWrapper: document.getElementById('mix-payment-wrapper'),
            mixInputs: document.querySelectorAll('.mix-amount-input'),
            mixTotalDisplay: document.getElementById('mix-total-display'),
            mixRemainingDisplay: document.getElementById('mix-remaining-display'),
            homeCreditWrapper: document.getElementById('home-credit-wrapper'),
            hcDownpayment: document.getElementById('hc-downpayment'),
            hcReference: document.getElementById('hc-reference'),
            hcGrandtotalDisplay: document.getElementById('hc-grandtotal-display'),
            hcFinancedDisplay: document.getElementById('hc-financed-display'),
            hcDpMethod: document.getElementById('hc-dp-method'),
            hcProvider: document.getElementById('hc-provider'),
            hcMixWrapper: document.getElementById('hc-mix-wrapper'),
            hcMixInputs: document.querySelectorAll('.hc-mix-amount-input')
        };

        if (!this.els.paymentMethod) return;

        // Initialize empty state for pay later rows
        this.payLaterSchedules = [];

        this.bindEvents();

        // Initialize field visibility based on current payment method selection
        this.toggleFields();
    },

    bindEvents() {
        this.els.paymentMethod.addEventListener('change', () => this.toggleFields());

        // Delegate event listener for dynamic Pay Later inputs
        if (this.els.payLaterContainer) {
            this.els.payLaterContainer.addEventListener('input', (e) => {
                if (e.target.classList.contains('pay-later-amount-input') || e.target.classList.contains('pay-later-date-input')) {
                    this.updatePayLaterStateFromDOM();
                }
            });
        }

        // Mix Payment calculations
        this.els.mixInputs?.forEach(input => {
            input.addEventListener('input', () => this.updateMixPaymentDisplay());
        });

        // Financing calculations
        if (this.els.hcDownpayment) {
            this.els.hcDownpayment.addEventListener('input', () => this.updateHomeCreditDisplay());
        }
        if (this.els.hcDpMethod) {
            this.els.hcDpMethod.addEventListener('change', () => this.updateHomeCreditDisplay());
        }
        this.els.hcMixInputs?.forEach(input => {
            input.addEventListener('input', () => this.updateHomeCreditDisplay());
        });
    },

    toggleFields() {
        const method = this.els.paymentMethod.value;
        const isPayLater = method === 'pay_later';
        const isMix = method === 'mix';
        const isHomeCredit = method === 'financing';

        if (isPayLater) {
            // Hide tendered, change, mix, hc fields, show pay later fields
            this.els.tenderedWrapper?.classList.add('d-none');
            this.els.changeWrapper?.classList.add('d-none');
            this.els.mixPaymentWrapper?.classList.add('d-none');
            this.els.homeCreditWrapper?.classList.add('d-none');
            this.els.payLaterWrapper?.classList.remove('d-none');

            // Initialize with one row pointing to 1 month with the full grand total if empty
            if (this.payLaterSchedules.length === 0) {
                const total = POS.totals ? POS.totals.grand_total : POS.cart.reduce((s, i) => s + (i.qty * i.price), 0);
                const defaultDate = new Date();
                defaultDate.setDate(defaultDate.getDate() + 30);
                this.payLaterSchedules.push({
                    id: Date.now().toString(),
                    date: defaultDate.toISOString().split('T')[0],
                    amount: total.toFixed(2)
                });
            }
            this.renderPayLaterRows();
            this.updatePayLaterDisplay();
        } else if (isMix) {
            // Show mix fields, hide others
            this.els.tenderedWrapper?.classList.add('d-none');
            this.els.changeWrapper?.classList.add('d-none');
            this.els.payLaterWrapper?.classList.add('d-none');
            this.els.homeCreditWrapper?.classList.add('d-none');
            this.els.mixPaymentWrapper?.classList.remove('d-none');

            // Reset and calculate mix
            this.updateMixPaymentDisplay();
        } else if (isHomeCredit) {
            // Show home credit fields, hide others
            this.els.tenderedWrapper?.classList.add('d-none');
            this.els.changeWrapper?.classList.add('d-none');
            this.els.payLaterWrapper?.classList.add('d-none');
            this.els.mixPaymentWrapper?.classList.add('d-none');
            this.els.homeCreditWrapper?.classList.remove('d-none');

            this.updateHomeCreditDisplay();
        } else {
            // Show tendered and change fields, hide pay later, mix, and hc fields
            this.els.tenderedWrapper?.classList.remove('d-none');
            this.els.changeWrapper?.classList.remove('d-none');
            this.els.payLaterWrapper?.classList.add('d-none');
            this.els.mixPaymentWrapper?.classList.add('d-none');
            this.els.homeCreditWrapper?.classList.add('d-none');
        }
    },

    // === HOME CREDIT LOGIC ===
    updateHomeCreditDisplay() {
        if (!this.els.homeCreditWrapper) return;

        const isMixedDp = this.els.hcDpMethod?.value === 'mix';

        if (isMixedDp) {
            this.els.hcMixWrapper?.classList.remove('d-none');
            if (this.els.hcDownpayment) this.els.hcDownpayment.readOnly = true;

            let mixDpTotal = 0;
            this.els.hcMixInputs?.forEach(input => {
                mixDpTotal += parseFloat(input.value || 0);
            });
            if (this.els.hcDownpayment) this.els.hcDownpayment.value = mixDpTotal;
        } else {
            this.els.hcMixWrapper?.classList.add('d-none');
            if (this.els.hcDownpayment) this.els.hcDownpayment.readOnly = false;
        }

        const grandTotal = POS.totals ? POS.totals.grand_total : (POS.cart ? POS.cart.reduce((s, i) => s + (i.qty * i.price), 0) : 0);
        let downPayment = parseFloat(this.els.hcDownpayment?.value || 0);

        if (downPayment > grandTotal) {
            downPayment = grandTotal; // Cap it visually and logically at grand total
        }

        const financed = grandTotal - downPayment;

        if (this.els.hcGrandtotalDisplay) {
            this.els.hcGrandtotalDisplay.textContent = POS.config.currency + grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
        }

        if (this.els.hcFinancedDisplay) {
            this.els.hcFinancedDisplay.textContent = POS.config.currency + financed.toLocaleString(undefined, { minimumFractionDigits: 2 });
        }
    },

    // === MULTIPLE PAY LATER LOGIC ===
    setQuickDate(type) {
        if (this.payLaterSchedules.length > 0) {
            const date = new Date();
            if (type === '1month') {
                date.setDate(date.getDate() + 30);
            } else if (typeof type === 'number') {
                date.setDate(date.getDate() + type);
            }
            this.payLaterSchedules[0].date = date.toISOString().split('T')[0];
            this.renderPayLaterRows();
            this.updatePayLaterDisplay();
        }
    },

    addPayLaterRow() {
        const nextDate = new Date();
        nextDate.setDate(nextDate.getDate() + 30);

        let remaining = this.getPayLaterRemaining();
        if (remaining < 0) remaining = 0;

        this.payLaterSchedules.push({
            id: Date.now().toString(),
            date: nextDate.toISOString().split('T')[0],
            amount: remaining > 0 ? remaining.toFixed(2) : ''
        });

        this.renderPayLaterRows();
        this.updatePayLaterDisplay();
    },

    removePayLaterRow(id) {
        this.payLaterSchedules = this.payLaterSchedules.filter(s => s.id !== id);
        this.renderPayLaterRows();
        this.updatePayLaterDisplay();
    },

    updatePayLaterStateFromDOM() {
        if (!this.els.payLaterContainer) return;

        const rows = this.els.payLaterContainer.querySelectorAll('.pay-later-row');
        this.payLaterSchedules = Array.from(rows).map(row => {
            return {
                id: row.dataset.id,
                date: row.querySelector('.pay-later-date-input').value,
                amount: row.querySelector('.pay-later-amount-input').value
            };
        });

        this.updatePayLaterDisplay();
    },

    renderPayLaterRows() {
        if (!this.els.payLaterContainer) return;

        const todayStr = new Date().toISOString().split('T')[0];

        this.els.payLaterContainer.innerHTML = this.payLaterSchedules.map((schedule, index) => `
            <div class="row g-2 mb-2 pay-later-row align-items-center" data-id="${schedule.id}">
                <div class="col-5">
                    <input type="date" class="form-control form-control-sm fw-bold pay-later-date-input" 
                           value="${schedule.date}" min="${todayStr}">
                </div>
                <div class="col-5">
                    <input type="number" class="form-control form-control-sm fw-bold text-end pay-later-amount-input" 
                           value="${schedule.amount}" placeholder="0.00" step="0.01">
                </div>
                <div class="col-2 text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="PaymentMethodHandler.removePayLaterRow('${schedule.id}')" ${this.payLaterSchedules.length === 1 ? 'disabled' : ''}>
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');
    },

    getPayLaterRemaining() {
        const grandTotal = POS.totals ? POS.totals.grand_total : (POS.cart ? POS.cart.reduce((s, i) => s + (i.qty * i.price), 0) : 0);
        let scheduledTotal = 0;

        this.payLaterSchedules.forEach(s => {
            scheduledTotal += parseFloat(s.amount || 0);
        });

        return grandTotal - scheduledTotal;
    },

    updatePayLaterDisplay() {
        if (!this.els.payLaterTotalDisplay || !this.els.payLaterRemainingDisplay) return;

        let scheduledTotal = 0;
        this.payLaterSchedules.forEach(s => {
            scheduledTotal += parseFloat(s.amount || 0);
        });

        const remaining = this.getPayLaterRemaining();

        this.els.payLaterTotalDisplay.textContent = POS.config.currency + scheduledTotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
        this.els.payLaterRemainingDisplay.textContent = POS.config.currency + Math.abs(remaining).toLocaleString(undefined, { minimumFractionDigits: 2 });

        const el = this.els.payLaterRemainingDisplay;
        if (remaining > 0) {
            el.className = 'text-danger'; // Short
            el.previousElementSibling.textContent = 'Remaining:';
        } else if (remaining < 0) {
            el.className = 'text-warning'; // Over
            el.previousElementSibling.textContent = 'Over Scheduled:';
        } else {
            el.className = 'text-success'; // Exact
            el.previousElementSibling.textContent = 'Remaining:';
        }
    },

    updateMixPaymentDisplay() {
        if (!this.els.mixPaymentWrapper) return;

        let mixTotal = 0;
        this.els.mixInputs?.forEach(input => {
            mixTotal += parseFloat(input.value || 0);
        });

        const grandTotal = POS.totals ? POS.totals.grand_total : (POS.cart ? POS.cart.reduce((s, i) => s + (i.qty * i.price), 0) : 0);
        const remaining = grandTotal - mixTotal;

        if (this.els.mixTotalDisplay) {
            this.els.mixTotalDisplay.textContent = POS.config.currency + mixTotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
        }

        if (this.els.mixRemainingDisplay) {
            const el = this.els.mixRemainingDisplay;
            el.textContent = POS.config.currency + Math.abs(remaining).toLocaleString(undefined, { minimumFractionDigits: 2 });

            if (remaining > 0) {
                el.className = 'text-danger'; // Short
                el.previousElementSibling.textContent = 'Remaining:';
            } else if (remaining < 0) {
                el.className = 'text-success'; // Change
                el.previousElementSibling.textContent = 'Change:';
            } else {
                el.className = 'text-success'; // Exact
                el.previousElementSibling.textContent = 'Remaining:';
            }
        }
    },

    // Get pay later data for payment processing (Array of schedules)
    getPayLaterData() {
        if (this.els.paymentMethod?.value !== 'pay_later') return null;

        // Ensure we fetch latest from DOM in case it wasn't caught
        this.updatePayLaterStateFromDOM();

        return this.payLaterSchedules.map(s => ({
            due_date: s.date || null,
            amount_due: parseFloat(s.amount || 0)
        })).filter(s => s.amount_due > 0 && s.due_date);
    },

    getMixPaymentData() {
        if (this.els.paymentMethod?.value !== 'mix') return null;

        return [
            { method: 'cash', amount: parseFloat(document.getElementById('mix-cash-amount')?.value || 0) },
            { method: 'gcash', amount: parseFloat(document.getElementById('mix-gcash-amount')?.value || 0) },
            { method: 'bank', amount: parseFloat(document.getElementById('mix-bank-amount')?.value || 0) },
            { method: 'financing', amount: parseFloat(document.getElementById('mix-hc-amount')?.value || 0) }
        ].filter(item => item.amount > 0);
    },

    getFinancingData() {
        if (this.els.paymentMethod?.value !== 'financing') return null;

        const dpMethod = this.els.hcDpMethod?.value || 'cash';
        const data = {
            down_payment: parseFloat(this.els.hcDownpayment?.value || 0),
            dp_method: dpMethod,
            reference_no: this.els.hcReference?.value?.trim() || null,
            provider: this.els.hcProvider?.value || 'Home Credit'
        };

        if (dpMethod === 'mix') {
            data.mix_details = [
                { method: 'cash', amount: parseFloat(document.getElementById('hc-mix-cash')?.value || 0) },
                { method: 'gcash', amount: parseFloat(document.getElementById('hc-mix-gcash')?.value || 0) },
                { method: 'bank', amount: parseFloat(document.getElementById('hc-mix-bank')?.value || 0) }
            ].filter(item => item.amount > 0);
        }

        return data;
    },

    // Backward compat alias
    getHomeCreditData() {
        return this.getFinancingData();
    },

    // Validate pay later fields
    validate() {
        const method = this.els.paymentMethod?.value;
        const grandTotal = POS.totals ? POS.totals.grand_total : POS.cart.reduce((s, i) => s + (i.qty * i.price), 0);

        if (method === 'pay_later') {
            this.updatePayLaterStateFromDOM();

            let scheduledTotal = 0;
            let missingDate = false;

            this.payLaterSchedules.forEach(s => {
                const amount = parseFloat(s.amount || 0);
                if (amount > 0 && !s.date) missingDate = true;
                scheduledTotal += amount;
            });

            if (this.payLaterSchedules.length === 0 || scheduledTotal === 0) {
                EllaToast.error('Please add at least one scheduled payment amount.');
                return false;
            }

            if (missingDate) {
                EllaToast.error('Please select a due date for all scheduled amounts.');
                return false;
            }

            // Must match Grand Total exactly
            const remaining = grandTotal - scheduledTotal;
            if (Math.abs(remaining) > 0.01) { // Floating point tolerance
                EllaToast.error(`The scheduled total (₱${scheduledTotal.toFixed(2)}) must exactly match the Grand Total (₱${grandTotal.toFixed(2)}).`);
                return false;
            }
        } else if (method === 'mix') {
            let mixTotal = 0;
            this.els.mixInputs?.forEach(input => {
                mixTotal += parseFloat(input.value || 0);
            });

            const roundedMix = Math.round(mixTotal * 100);
            const roundedTotal = Math.round(grandTotal * 100);

            if (roundedMix < roundedTotal) {
                const shortAmount = (grandTotal - mixTotal).toFixed(2);
                EllaToast.error(`Insufficient Mix payments! Total: ₱${grandTotal.toFixed(2)}, Mix Total: ₱${mixTotal.toFixed(2)}, Short: ₱${shortAmount}`);
                return false;
            }

            const mixData = this.getMixPaymentData();
            if (mixData.length === 0) {
                EllaToast.error('Please enter amounts for Mix Payment methods.');
                return false;
            }
        } else if (method === 'financing') {
            const downPayment = parseFloat(this.els.hcDownpayment?.value || 0);
            const dpMethod = this.els.hcDpMethod?.value;

            if (downPayment < 0) {
                EllaToast.error('Down payment cannot be negative.');
                return false;
            }
            if (downPayment > grandTotal) {
                EllaToast.error('Down payment cannot exceed the Grand Total.');
                return false;
            }

            if (dpMethod === 'mix') {
                const mixData = this.getFinancingData()?.mix_details || [];
                if (mixData.length === 0 && downPayment > 0) {
                    EllaToast.error('Please enter amounts for Mix Downpayment methods.');
                    return false;
                }
            }
        }

        return true;
    },

    reset() {
        if (this.els.paymentMethod) this.els.paymentMethod.value = 'cash';
        if (document.getElementById('amount-tendered')) document.getElementById('amount-tendered').value = '';
        if (document.getElementById('change-display')) document.getElementById('change-display').innerText = '₱0.00';

        // Clear Mix Payment fields
        this.els.mixInputs?.forEach(input => input.value = '');

        // Clear Pay Later schedules
        this.payLaterSchedules = [];

        // Clear Financing fields
        if (this.els.hcDownpayment) this.els.hcDownpayment.value = '0';
        if (this.els.hcReference) this.els.hcReference.value = '';
        this.els.hcMixInputs?.forEach(input => input.value = '');

        // Toggle visibility back to default (Cash)
        this.toggleFields();
        this.updateMixPaymentDisplay();
        this.updateHomeCreditDisplay();
    }
};
