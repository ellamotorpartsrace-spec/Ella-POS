/* =====================================================
   SCANNER MODE MODULE
   Barcode Scanner Functionality with Auto-Add
===================================================== */

const ScannerMode = {
    enabled: false, // Default OFF
    isScanning: false, // Prevents double submission (race condition)
    scanTimer: null,

    // --- MOBILE RELAY PROPERTIES ---
    terminalId: null,      // Unique ID for this POS session
    pollInterval: null,    // setInterval handle
    pollRate: 1500,        // Poll every 1.5s for relay scans

    init() {
        const toggle = document.getElementById('barcode-mode-toggle');
        const input = document.getElementById('product-search');
        if (!toggle || !input) return;

        // Initialize Terminal ID (persists for the browser session)
        this.terminalId = sessionStorage.getItem('pos_terminal_id');
        if (!this.terminalId) {
            this.terminalId = 'TERM-' + Math.random().toString(36).substring(2, 9).toUpperCase();
            sessionStorage.setItem('pos_terminal_id', this.terminalId);
        }

        // Sync toggle state with checkbox
        this.enabled = toggle.checked;
        if (this.enabled) this.startMobileRelay();

        toggle.addEventListener('change', () => {
            this.enabled = toggle.checked;

            // Clear search input only (NOT the cart)
            input.value = '';

            // Adapt the catalog view to the new mode
            if (this.enabled) {
                this.startMobileRelay();
                if (typeof ProductSearch !== 'undefined' && ProductSearch.renderScannerPlaceholder) {
                    ProductSearch.renderScannerPlaceholder();
                }
            } else {
                this.stopMobileRelay();
                // Restore catalog when scanner mode is turned off
                if (typeof ProductSearch !== 'undefined' && ProductSearch.performSearch) {
                    ProductSearch.performSearch('', false, false);
                }
            }

            input.focus();
            input.placeholder = this.enabled ? `Ready (ID: ${this.terminalId})...` : "Type product name...";
        });

        // input.addEventListener('keydown'...
        input.addEventListener('keydown', e => {
            if (!this.enabled) return;

            // Prevent Tab from moving focus away
            if (e.key === 'Tab') {
                e.preventDefault();
                input.focus();
            }

            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();

                if (this.isScanning) return; // Already processing

                // Clear any pending auto-trigger
                if (this.scanTimer) clearTimeout(this.scanTimer);

                const barcode = input.value.trim();
                if (barcode) {
                    this.processScan(barcode);
                }
            }
        });

        // Auto-Trigger: Debounce input to detect end of scan (for physical keyboard scanners)
        input.addEventListener('input', (e) => {
            if (!this.enabled || this.isScanning) return;

            if (this.scanTimer) clearTimeout(this.scanTimer);

            // Wait 200ms after last character (typical scanner burst is very fast)
            this.scanTimer = setTimeout(() => {
                const barcode = input.value.trim();
                // Only trigger if we have a substantial string (avoid tiny accidental keystrokes)
                if (barcode && barcode.length >= 3) {
                    console.log('⚡ Auto-triggering scan for:', barcode);
                    this.processScan(barcode);
                }
            }, 200);
        });

        // LOCK FOCUS: Keep focus on search input when scanner mode is active
        input.addEventListener('blur', (e) => {
            if (this.enabled) {
                setTimeout(() => {
                    const active = document.activeElement;
                    // Allowed interactive elements that shouldn't steal focus permanently
                    const allowedTags = ['INPUT', 'SELECT', 'BUTTON', 'TEXTAREA', 'A'];

                    // If focus moved to body (clicked empty space) or it's NOT an allowed interactive element
                    if (!active || active === document.body || !allowedTags.includes(active.tagName)) {
                        input.focus();
                    }
                }, 100);
            }
        });

        // Refocus on background click
        document.addEventListener('click', (e) => {
            if (this.enabled && document.activeElement === document.body) {
                input.focus();
            }
        });
    },

    // --- MOBILE RELAY POLLING ---
    startMobileRelay() {
        if (this.pollInterval) return;
        console.log(`📱 Mobile Relay Polling Active for Terminal: ${this.terminalId}`);
        this.pollInterval = setInterval(() => this.pollRelay(), this.pollRate);
        
        // Update UI Badge if exists
        const badge = document.getElementById('mobile-relay-badge');
        if (badge) {
            badge.classList.remove('d-none');
            badge.title = `Terminal ID: ${this.terminalId}`;
        }
    },

    stopMobileRelay() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
        const badge = document.getElementById('mobile-relay-badge');
        if (badge) badge.classList.add('d-none');
    },

    async pollRelay() {
        if (this.isScanning || !this.enabled) return;

        try {
            const res = await fetch(`../../api/pos/scanner_relay.php?action=poll&terminal_id=${this.terminalId}`);
            const data = await res.json();

            if (data.success && data.barcode) {
                console.log('📡 Scan received from mobile relay:', data.barcode);
                this.processScan(data.barcode);
            }
        } catch (e) {
            console.warn('Relay poll failed:', e);
        }
    },

    processScan(barcode) {
        if (this.isScanning) return;
        this.isScanning = true;

        console.log('🔍 Scanner: Processing barcode:', barcode);
        const input = document.getElementById('product-search');

        // Immediately clear input to prepare for next scan (and visually indicate processing)
        if (input) {
            input.value = '';
            input.focus();
        }

        // Search for product and auto-add
        fetch(`../../api/pos/simple_search.php?q=${encodeURIComponent(barcode)}`)
            .then(res => res.json())
            .then(items => {
                if (!items || items.length === 0) {
                    console.warn('⚠️ Scanner: No products found');
                    this.showScanFeedback('❌ Product not found', 'danger');
                    return;
                }

                // Check for EXACT barcode match first, then fallback to first result
                let validItem = items.find(item => item.barcode === barcode && parseInt(item.stock) > 0);

                // If no exact match with stock, find any result with stock
                if (!validItem) {
                    validItem = items.find(item => parseInt(item.stock) > 0);
                }

                if (validItem) {
                    // Auto-add to cart
                    if (typeof window.CartManager !== 'undefined' && window.CartManager.addToCart) {
                        window.CartManager.addToCart(validItem, true);
                        this.showScanFeedback(`✓ Added: ${validItem.product_name}`, 'success');
                    } else {
                        console.error('❌ CartManager is not available!');
                        this.showScanFeedback('❌ System error', 'danger');
                    }
                } else {
                    console.warn('⚠️ Scanner: No items with stock');
                    this.showScanFeedback('⚠️ Item out of stock', 'warning');
                }
            })
            .catch(err => {
                console.error('❌ Scanner: Fetch error:', err);
                this.showScanFeedback('❌ Scan failed', 'danger');
            })
            .finally(() => {
                this.isScanning = false;
                // Re-focus input just in case
                if (input) input.focus();
            });
    },

    showScanFeedback(message, type) {
        const container = document.getElementById('search-results');
        if (!container) return;

        const colors = {
            success: '#198754',
            warning: '#ffc107',
            danger: '#dc3545'
        };

        container.innerHTML = `
            <div class="d-flex align-items-center justify-content-center w-100 h-100" style="grid-column: 1 / -1;">
                <div class="text-center py-4">
                    <div class="fs-4 fw-bold animated pulse" style="color: ${colors[type] || colors.success}">
                        ${message}
                    </div>
                </div>
            </div>
        `;

        // Restore placeholder after delay
        setTimeout(() => {
            // Only restore if we haven't started a NEW scan/search (check if content is still the feedback)
            if (container.innerText.includes(message)) {
                if (typeof ProductSearch !== 'undefined' && ProductSearch.renderScannerPlaceholder) {
                    ProductSearch.renderScannerPlaceholder();
                }
            }
        }, 1200);
    }
};
