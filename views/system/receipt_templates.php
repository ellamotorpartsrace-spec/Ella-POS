<?php
// views/system/receipt_templates.php - Receipt Template Manager
require_once '../../config/config.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('manage_settings')) {
    denyAccess("You do not have permission to configure receipts.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    .template-card {
        border: none;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    }

    .template-card .card-header {
        border-bottom: 1px solid var(--border-color);
        padding: 16px 20px;
    }

    .template-card .card-body {
        padding: 20px;
    }

    .section-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    /* Toggle Switch */
    .toggle-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-color);
    }

    .toggle-row:last-child {
        border-bottom: none;
    }

    .toggle-label {
        font-weight: 500;
        font-size: 0.9rem;
    }

    .toggle-desc {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin-top: 2px;
    }

    /* Receipt Preview */
    .preview-container {
        position: sticky;
        top: 80px;
    }

    .receipt-live-preview {
        background: #fff;
        border: 2px dashed #dee2e6;
        border-radius: 12px;
        padding: 20px 15px;
        max-width: 320px;
        margin: 0 auto;
        font-family: 'Courier New', Courier, monospace;
        font-size: 12px;
        color: #000;
        line-height: 1.4;
        min-height: 300px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
    }

    .receipt-live-preview .r-center {
        text-align: center;
    }

    .receipt-live-preview .r-bold {
        font-weight: bold;
    }

    .receipt-live-preview .r-divider {
        border: none;
        border-top: 1px dashed #000;
        margin: 6px 0;
    }

    .receipt-live-preview .r-row {
        display: flex;
        justify-content: space-between;
    }

    .receipt-live-preview .r-item {
        margin-bottom: 6px;
    }

    .receipt-live-preview .r-total {
        font-size: 14px;
        font-weight: bold;
    }

    .receipt-live-preview .r-footer {
        text-align: center;
        font-size: 10px;
        margin-top: 8px;
    }

    .receipt-live-preview .r-hidden {
        display: none;
    }

    .save-indicator {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Preview Tab Buttons */
    .preview-tab {
        transition: all 0.2s ease;
    }

    .preview-tab.active {
        pointer-events: none;
    }

    /* A4 Preview */
    .receipt-a4-preview {
        font-family: 'Segoe UI', Arial, sans-serif !important;
        font-size: 11px !important;
        max-width: 100% !important;
        padding: 25px !important;
        border: 1px solid #ddd !important;
        border-style: solid !important;
    }
</style>

<div class="container-fluid p-3 p-lg-4">

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h4 class="fw-bold mb-1">
                <i class="fa-solid fa-receipt text-primary me-2"></i>Receipt Template Manager
            </h4>
            <p class="text-muted mb-0 small">Customize what appears on your printed receipts with a live preview</p>
        </div>
        <div class="d-flex gap-2">
            <span class="save-indicator badge bg-success py-2 px-3" id="save-indicator">
                <i class="fa-solid fa-check me-1"></i> Saved!
            </span>
            <button class="btn btn-primary" onclick="ReceiptTemplate.save()" id="btn-save">
                <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
            </button>
        </div>
    </div>

    <div class="row g-4">

        <!-- Left: Toggle Controls -->
        <div class="col-12 col-lg-6">

            <!-- Header Section -->
            <div class="card template-card mb-4">
                <div class="card-header bg-transparent d-flex align-items-center gap-3">
                    <div class="section-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-heading"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0">Header Section</h6>
                        <small class="text-muted">Store identity at the top of the receipt</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="toggle-row">
                        <div>
                            <div class="toggle-label">Store Name</div>
                            <div class="toggle-desc">Display your business name prominently</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="receipt_show_store_name"
                                data-key="receipt_show_store_name" checked>
                        </div>
                    </div>
                    <div class="toggle-row">
                        <div>
                            <div class="toggle-label">Store Address</div>
                            <div class="toggle-desc">Show the store's physical address</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="receipt_show_address"
                                data-key="receipt_show_address" checked>
                        </div>
                    </div>
                    <div class="toggle-row">
                        <div>
                            <div class="toggle-label">Contact Number</div>
                            <div class="toggle-desc">Display store phone/mobile number</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="receipt_show_contact"
                                data-key="receipt_show_contact" checked>
                        </div>
                    </div>
                    <div class="toggle-row">
                        <div>
                            <div class="toggle-label">Facebook Page</div>
                            <div class="toggle-desc">Show your Facebook page link</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="receipt_show_facebook"
                                data-key="receipt_show_facebook" checked>
                        </div>
                    </div>
                    <div class="toggle-row">
                        <div>
                            <div class="toggle-label">Tax ID / VAT Info</div>
                            <div class="toggle-desc">Show tax registration information</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="receipt_show_tax_id"
                                data-key="receipt_show_tax_id" checked>
                        </div>
                    </div>

                    <!-- Custom Header Text -->
                    <div class="mt-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Custom Header Text</label>
                        <input type="text" class="form-control form-control-sm" id="receipt_header_text"
                            data-key="receipt_header_text" placeholder="e.g. 'Authorized Dealer of Honda Parts'"
                            maxlength="120">
                        <div class="form-text">Optional line displayed below the store name</div>
                    </div>
                </div>
            </div>

            <!-- Transaction Details Section -->
            <div class="card template-card mb-4">
                <div class="card-header bg-transparent d-flex align-items-center gap-3">
                    <div class="section-icon bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-list"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0">Transaction Details</h6>
                        <small class="text-muted">Information about the sale</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="toggle-row">
                        <div>
                            <div class="toggle-label">Cashier Name</div>
                            <div class="toggle-desc">Show who processed the sale</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="receipt_show_cashier"
                                data-key="receipt_show_cashier" checked>
                        </div>
                    </div>
                    <div class="toggle-row">
                        <div>
                            <div class="toggle-label">Customer Name</div>
                            <div class="toggle-desc">Show the buyer/customer name</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="receipt_show_customer"
                                data-key="receipt_show_customer" checked>
                        </div>
                    </div>
                    <div class="toggle-row">
                        <div>
                            <div class="toggle-label">Item Discounts</div>
                            <div class="toggle-desc">Show per-item discount details and original prices</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="receipt_show_item_discount"
                                data-key="receipt_show_item_discount" checked>
                        </div>
                    </div>
                    <div class="toggle-row">
                        <div>
                            <div class="toggle-label">Payment Method</div>
                            <div class="toggle-desc">Show Cash, GCash, Bank, or Pay Later indicator</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="receipt_show_payment_method"
                                data-key="receipt_show_payment_method" checked>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hardware Settings -->
            <div class="card template-card mb-4 border-primary">
                <div class="card-header bg-primary bg-opacity-10 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="section-icon bg-primary text-white">
                            <i class="fa-solid fa-print"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-primary mb-0">Hardware Configuration</h6>
                            <small class="text-primary opacity-75">Connect thermal printer directly</small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Print Mode</label>
                        <select class="form-select" id="printer_mode" data-key="printer_mode">
                            <option value="browser">Browser Print Dialog (A4 / Desktop)</option>
                            <option value="direct">Direct to Printer (Fast/Silent ESC/POS)</option>
                            <option value="rawbt">Android/RawBT App (Bluetooth)</option>
                        </select>
                        <div class="form-text">Direct Mode bypasses the print popup and automatically fires the cash
                            drawer/cutter. RawBT mode connects to the RawBT Android app for mobile Bluetooth printing.
                        </div>
                    </div>

                    <div id="direct_print_settings"
                        style="display:none; padding:15px; background: #f8f9fa; border-radius: 8px; border: 1px dashed #dee2e6;">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted text-uppercase">Connection
                                Protocol</label>
                            <select class="form-select border-white" id="printer_connection"
                                data-key="printer_connection">
                                <option value="network">Network (LAN/Wi-Fi) Printer</option>
                                <option value="usb_shared">USB Printer (Windows Shared)</option>
                            </select>
                        </div>

                        <div class="mb-0">
                            <label class="form-label small fw-bold text-muted text-uppercase">Printer Address</label>
                            <input type="text" class="form-control" id="printer_address" data-key="printer_address"
                                placeholder="e.g. 192.168.1.87 or smb://PC-NAME/Printer">
                            <div class="form-text" id="printer_address_help">Enter the IP Address of the printer.</div>
                        </div>

                        <div class="mt-3 text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                onclick="ReceiptTemplate.testPrint()">
                                <i class="fa-solid fa-plug me-1"></i> Test Connection
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Section -->
            <div class="card template-card mb-4">
                <div class="card-header bg-transparent d-flex align-items-center gap-3">
                    <div class="section-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-pen-fancy"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0">Footer Section</h6>
                        <small class="text-muted">Messages at the bottom of the receipt</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Additional Footer Note</label>
                        <textarea class="form-control form-control-sm" id="receipt_footer_note"
                            data-key="receipt_footer_note" rows="2"
                            placeholder="e.g. 'No return without receipt. Items valid for 7 days warranty.'"
                            maxlength="200"></textarea>
                        <div class="form-text">Shown above the main "Thank you" message (configured in System Settings)
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right: Live Preview -->
        <div class="col-12 col-lg-6">
            <div class="preview-container">
                <div class="card template-card">
                    <div class="card-header bg-transparent">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="section-icon bg-info bg-opacity-10 text-info">
                                    <i class="fa-solid fa-eye"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">Live Preview</h6>
                                    <small class="text-muted">Updates instantly as you change settings</small>
                                </div>
                            </div>
                        </div>
                        <!-- Format Tabs -->
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-sm btn-primary preview-tab active" data-format="thermal80">
                                <i class="fa-solid fa-receipt me-1"></i> Thermal 80mm
                            </button>
                            <button class="btn btn-sm btn-outline-secondary preview-tab" data-format="thermal80x3276">
                                <i class="fa-solid fa-scroll me-1"></i> Thermal Roll
                            </button>
                            <button class="btn btn-sm btn-outline-secondary preview-tab" data-format="a4">
                                <i class="fa-solid fa-file-lines me-1"></i> A4 Invoice
                            </button>
                        </div>
                    </div>
                    <div class="card-body" style="background: #f8f9fa;">
                        <!-- Thermal 80mm Preview -->
                        <div class="preview-pane active" id="preview-thermal80">
                            <div class="receipt-live-preview" id="receipt-preview-thermal80">
                            </div>
                        </div>
                        <!-- Thermal Roll Preview -->
                        <div class="preview-pane" id="preview-thermal80x3276" style="display:none;">
                            <div class="receipt-live-preview" id="receipt-preview-thermal80x3276">
                            </div>
                        </div>
                        <!-- A4 Invoice Preview -->
                        <div class="preview-pane" id="preview-a4" style="display:none;">
                            <div class="receipt-live-preview receipt-a4-preview" id="receipt-preview-a4">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    const ReceiptTemplate = {
        settings: {},

        init() {
            // Load current settings from the database
            this.loadSettings();

            // Bind live preview updates to all toggle switches and text fields
            document.querySelectorAll('[data-key]').forEach(el => {
                const event = el.type === 'checkbox' ? 'change' : 'input';
                el.addEventListener(event, () => this.updatePreview());
            });

            // Hardware settings UI logic
            const modeSelect = document.getElementById('printer_mode');
            const directSettings = document.getElementById('direct_print_settings');
            const connSelect = document.getElementById('printer_connection');
            const addrHelp = document.getElementById('printer_address_help');

            if (modeSelect) {
                modeSelect.addEventListener('change', (e) => {
                    directSettings.style.display = e.target.value === 'direct' ? 'block' : 'none';
                });
            }

            if (connSelect) {
                connSelect.addEventListener('change', (e) => {
                    if (e.target.value === 'network') {
                        addrHelp.textContent = "Enter the IP Address of the printer (e.g., 192.168.1.87)";
                    } else {
                        addrHelp.textContent = "Enter the Windows Share Path (e.g., smb://COMPUTER-NAME/ReceiptPrinter)";
                    }
                });
            }

            // Bind preview format tabs
            document.querySelectorAll('.preview-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('.preview-tab').forEach(t => {
                        t.classList.remove('active', 'btn-primary');
                        t.classList.add('btn-outline-secondary');
                    });
                    tab.classList.add('active', 'btn-primary');
                    tab.classList.remove('btn-outline-secondary');

                    document.querySelectorAll('.preview-pane').forEach(p => p.style.display = 'none');
                    document.getElementById('preview-' + tab.dataset.format).style.display = 'block';
                });
            });
        },

        async loadSettings() {
            try {
                const res = await fetch('../../api/system/get_settings.php');
                const data = await res.json();

                if (data.success) {
                    this.settings = data.settings || {};

                    // Apply loaded values to form controls
                    document.querySelectorAll('[data-key]').forEach(el => {
                        const key = el.dataset.key;
                        const settingObj = this.settings[key];
                        const val = settingObj ? settingObj.value : undefined;

                        if (el.type === 'checkbox') {
                            el.checked = val === '1' || val === undefined; // default to checked
                        } else {
                            el.value = val || '';
                        }
                    });

                    this.updatePreview();
                }
            } catch (err) {
                console.error('Failed to load settings:', err);
            }
        },

        getValues() {
            const values = {};
            document.querySelectorAll('[data-key]').forEach(el => {
                const key = el.dataset.key;
                if (el.type === 'checkbox') {
                    values[key] = el.checked ? '1' : '0';
                } else {
                    values[key] = el.value;
                }
            });
            return values;
        },

        async testPrint() {
            const v = this.getValues();
            if (v.printer_mode !== 'direct') {
                EllaToast.warning("Please select 'Direct to Printer' mode first.");
                return;
            }
            if (!v.printer_address) {
                EllaToast.warning("Please enter a Printer Address.");
                return;
            }

            const btn = document.querySelector('button[onclick="ReceiptTemplate.testPrint()"]');
            const oldHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Testing...';

            const testCmds = [
                { type: 'align', align: 'center' },
                { type: 'text', text: 'ELLA MOTOR PARTS', bold: true, size: 'tall' },
                { type: 'text', text: '================================================' },
                { type: 'text', text: 'DIRECT PRINT CONNECTION TEST SUCCESSFUL!' },
                { type: 'text', text: '================================================' },
                { type: 'text', text: 'Type: ' + v.printer_connection },
                { type: 'text', text: 'Address: ' + v.printer_address },
                { type: 'feed', lines: 3 },
                { type: 'cut' },
                { type: 'drawer' }
            ];

            try {
                const res = await fetch('../../api/pos/print_direct.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        commands: testCmds,
                        printer_connection: v.printer_connection,
                        printer_address: v.printer_address
                    })
                });

                const data = await res.json();
                if (data.success) {
                    EllaToast.success("Success! The test page was sent to the printer.");
                } else {
                    EllaToast.error("Print Failed:\n\n" + (data.error || "Could not connect to printer"));
                }
            } catch (err) {
                EllaToast.error("Network/Server Error:\n\nCould not reach the print service.");
            } finally {
                btn.disabled = false;
                btn.innerHTML = oldHtml;
            }
        },

        updatePreview() {
            const v = this.getValues();
            const S = window.STORE_SETTINGS || {};

            const storeName = S.store_name || 'ELLA MOTOR PARTS';
            const storeAddress = S.store_address || 'Cauayan City, Isabela';
            const storeContact = S.store_contact || '09XX-XXX-XXXX';
            const storeFacebook = S.store_facebook || 'Ella Motor Parts';
            const storeTaxId = S.store_tax_id || 'TIN: 000-000-000-000';
            const receiptFooter = S.receipt_footer || 'THANK YOU FOR YOUR PURCHASE!';

            // Sample data for preview
            const sampleItems = [
                { name: 'Brake Pad Set (Honda)', brand: 'TRW', qty: 2, price: 450.00, original_price: 500.00, discount: 50.00 },
                { name: 'Engine Oil 1L', brand: 'Motul', qty: 3, price: 380.00, original_price: 380.00, discount: 0 },
            ];

            let html = '';

            // ===== HEADER =====
            html += '<div class="r-center">';
            if (v.receipt_show_store_name === '1') {
                html += `<strong style="font-size:16px;">${this.esc(storeName)}</strong><br>`;
            }
            if (v.receipt_show_address === '1') {
                html += `${this.esc(storeAddress)}<br>`;
            }
            if (v.receipt_show_facebook === '1' && storeFacebook) {
                html += `Follow Us: ${this.esc(storeFacebook)}<br>`;
            }
            if (v.receipt_show_contact === '1' && storeContact) {
                html += `Contact: ${this.esc(storeContact)}<br>`;
            }
            if (v.receipt_show_tax_id === '1' && storeTaxId) {
                html += `Non-VAT Reg: ${this.esc(storeTaxId)}<br>`;
            }
            if (v.receipt_header_text) {
                html += `<em style="font-size:10px;">${this.esc(v.receipt_header_text)}</em><br>`;
            }
            html += '</div>';

            html += '<hr class="r-divider">';

            // ===== TRANSACTION META =====
            html += '<div>';
            html += `<div>Ref: <strong>ELLA-20260221-001</strong></div>`;
            html += `<div>Date: ${new Date().toLocaleString()}</div>`;
            if (v.receipt_show_customer === '1') {
                html += `<div>Customer: Walk-in Customer</div>`;
            }
            if (v.receipt_show_cashier === '1') {
                html += `<div>Cashier: Staff Member</div>`;
            }
            html += `<div>Items: 5</div>`;
            html += '</div>';

            html += '<hr class="r-divider">';

            // ===== ITEMS =====
            let subtotal = 0;
            let totalItemDiscount = 0;
            sampleItems.forEach(item => {
                const lineTotal = item.qty * item.price;
                const hasDiscount = item.discount > 0 && v.receipt_show_item_discount === '1';
                subtotal += item.qty * item.original_price;
                totalItemDiscount += item.discount * item.qty;

                html += '<div class="r-item">';
                html += `<strong>${this.esc(item.name)}</strong><br>`;
                html += `<span style="font-size:11px;">${this.esc(item.brand)}</span><br>`;
                html += `${item.qty} x `;
                if (hasDiscount) {
                    html += `<span style="text-decoration:line-through;">₱${item.original_price.toFixed(2)}</span> `;
                }
                html += `₱${item.price.toFixed(2)}`;
                html += `<span style="float:right;">₱${lineTotal.toFixed(2)}</span>`;
                if (hasDiscount) {
                    html += `<br><span style="font-size:10px;">  Disc: -₱${(item.discount * item.qty).toFixed(2)}</span>`;
                }
                html += '</div>';
            });

            html += '<hr class="r-divider">';

            // ===== TOTALS =====
            const grandTotal = subtotal - (v.receipt_show_item_discount === '1' ? totalItemDiscount : 0);
            if (v.receipt_show_item_discount === '1' && totalItemDiscount > 0) {
                html += `<div class="r-row"><span>Subtotal:</span><span>₱${subtotal.toFixed(2)}</span></div>`;
                html += `<div class="r-row"><span>Discounts:</span><span>-₱${totalItemDiscount.toFixed(2)}</span></div>`;
            }
            html += `<div class="r-row r-total"><span>TOTAL:</span><span>₱${grandTotal.toFixed(2)}</span></div>`;

            // ===== PAYMENT =====
            if (v.receipt_show_payment_method === '1') {
                html += `<div>Method: CASH</div>`;
            }

            html += '<hr class="r-divider">';

            // ===== FOOTER =====
            html += '<div class="r-footer">';
            if (v.receipt_footer_note) {
                html += `<div style="font-size:10px; margin-bottom:4px;">${this.esc(v.receipt_footer_note)}</div>`;
            }
            html += `<div>${this.esc(receiptFooter)}</div>`;
            html += '</div>';

            document.getElementById('receipt-preview-thermal80').innerHTML = html;

            // ===== THERMAL ROLL PREVIEW =====
            this.updateThermalRollPreview(v, S);

            // ===== A4 PREVIEW =====
            this.updateA4Preview(v, S);
        },

        updateThermalRollPreview(v, S) {
            const storeName = S.store_name || 'ELLA MOTOR PARTS';
            const receiptFooter = S.receipt_footer || 'THANK YOU FOR YOUR PURCHASE!';

            const sampleItems = [
                { name: 'Brake Pad Set (Honda)', brand: 'TRW', qty: 2, price: 450.00, original_price: 500.00, discount: 50.00 },
                { name: 'Engine Oil 1L', brand: 'Motul', qty: 3, price: 380.00, original_price: 380.00, discount: 0 },
            ];

            let html = '';

            // Header - Roll format shows all header fields
            html += '<div class="r-center">';
            if (v.receipt_show_store_name === '1') {
                html += `<strong style="font-size:16px;">${this.esc(storeName)}</strong><br>`;
            }
            if (v.receipt_show_address === '1') {
                html += `${this.esc(S.store_address || 'Cauayan City, Isabela')}<br>`;
            }
            if (v.receipt_show_facebook === '1' && S.store_facebook) {
                html += `Follow Us: ${this.esc(S.store_facebook)}<br>`;
            }
            if (v.receipt_show_contact === '1' && S.store_contact) {
                html += `Contact: ${this.esc(S.store_contact)}<br>`;
            }
            if (v.receipt_show_tax_id === '1' && S.store_tax_id) {
                html += `Non-VAT Reg: ${this.esc(S.store_tax_id)}<br>`;
            }
            if (v.receipt_header_text) {
                html += `<em style="font-size:10px;">${this.esc(v.receipt_header_text)}</em><br>`;
            }
            html += '</div>';

            html += '<hr class="r-divider">';

            // Meta
            html += '<div>';
            html += `<div>Ref: <strong>ELLA-20260221-001</strong></div>`;
            html += `<div>Date: ${new Date().toLocaleString()}</div>`;
            if (v.receipt_show_customer === '1') html += `<div>Customer: Walk-in Customer</div>`;
            if (v.receipt_show_cashier === '1') html += `<div>Cashier: Staff Member</div>`;
            html += `<div>Items: 5</div>`;
            html += '</div>';

            html += '<hr class="r-divider">';

            // Items
            let subtotal = 0, totalItemDiscount = 0;
            sampleItems.forEach(item => {
                const lineTotal = item.qty * item.price;
                const hasDiscount = item.discount > 0 && v.receipt_show_item_discount === '1';
                subtotal += item.qty * item.original_price;
                totalItemDiscount += item.discount * item.qty;

                html += '<div class="r-item">';
                html += `<strong>${this.esc(item.name)}</strong><br>`;
                html += `<span style="font-size:11px;">${this.esc(item.brand)}</span><br>`;
                html += `${item.qty} x `;
                if (hasDiscount) html += `<span style="text-decoration:line-through;">₱${item.original_price.toFixed(2)}</span> `;
                html += `₱${item.price.toFixed(2)}`;
                html += `<span style="float:right;">₱${lineTotal.toFixed(2)}</span>`;
                if (hasDiscount) html += `<br><span style="font-size:10px;">  Disc: -₱${(item.discount * item.qty).toFixed(2)}</span>`;
                html += '</div>';
            });

            html += '<hr class="r-divider">';

            const grandTotal = subtotal - (v.receipt_show_item_discount === '1' ? totalItemDiscount : 0);
            if (v.receipt_show_item_discount === '1' && totalItemDiscount > 0) {
                html += `<div class="r-row"><span>Subtotal:</span><span>₱${subtotal.toFixed(2)}</span></div>`;
                html += `<div class="r-row"><span>Discounts:</span><span>-₱${totalItemDiscount.toFixed(2)}</span></div>`;
            }
            html += `<div class="r-row r-total"><span>TOTAL:</span><span>₱${grandTotal.toFixed(2)}</span></div>`;

            if (v.receipt_show_payment_method === '1') html += `<div>Method: CASH</div>`;

            html += '<hr class="r-divider">';

            html += '<div class="r-footer">';
            if (v.receipt_footer_note) html += `<div style="font-size:10px; margin-bottom:4px;">${this.esc(v.receipt_footer_note)}</div>`;
            html += `<div>${this.esc(receiptFooter)}</div>`;
            html += '</div>';

            // End marker (roll specific)
            html += '<div style="height:15px;"></div>';
            html += '<div style="text-align:center; font-size:10px; font-weight:bold; border-top:2px dashed #000; padding-top:5px;">--- END OF RECEIPT ---</div>';

            document.getElementById('receipt-preview-thermal80x3276').innerHTML = html;
        },

        updateA4Preview(v, S) {
            const storeName = S.store_name || 'ELLA MOTOR PARTS';
            const storeAddress = S.store_address || 'Cauayan City, Isabela';
            const storeContact = S.store_contact || '09XX-XXX-XXXX';
            const storeFacebook = S.store_facebook || 'Ella Motor Parts';
            const storeTaxId = S.store_tax_id || 'TIN: 000-000-000-000';
            const receiptFooter = S.receipt_footer || 'THANK YOU FOR YOUR PURCHASE!';

            const sampleItems = [
                { name: 'Brake Pad Set (Honda)', brand: 'TRW', qty: 2, price: 450.00, original_price: 500.00, discount: 50.00 },
                { name: 'Engine Oil 1L', brand: 'Motul', qty: 3, price: 380.00, original_price: 380.00, discount: 0 },
            ];

            const fmt = (v) => Number(v).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            let subtotal = 0, totalItemDiscount = 0;
            let itemRows = sampleItems.map(item => {
                const lineTotal = item.qty * item.price;
                const hasDiscount = item.discount > 0 && v.receipt_show_item_discount === '1';
                subtotal += item.qty * item.original_price;
                totalItemDiscount += item.discount * item.qty;

                return `
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:4px 6px; text-align:center;">${item.qty}</td>
                    <td style="padding:4px 6px;">
                        <div style="font-weight:600;">${this.esc(item.name)}</div>
                        <div style="font-size:10px; color:#000;">
                            ${this.esc(item.brand)}
                            ${hasDiscount ? `<span style="color:#dc2626; margin-left:5px;">(Disc: -₱${fmt(item.discount)})</span>` : ''}
                        </div>
                    </td>
                    <td style="padding:4px 6px; text-align:right;">
                        ${hasDiscount ? `<span style="text-decoration:line-through;color:#999;font-size:10px;">₱${fmt(item.original_price)}</span> ` : ''}₱${fmt(item.price)}
                    </td>
                    <td style="padding:4px 6px; text-align:right; font-weight:600;">₱${fmt(lineTotal)}</td>
                </tr>`;
            }).join('');

            const grandTotal = subtotal - (v.receipt_show_item_discount === '1' ? totalItemDiscount : 0);

            let addressLine = '';
            if (v.receipt_show_address === '1') addressLine += this.esc(storeAddress) + '<br>';
            if (v.receipt_show_facebook === '1' && storeFacebook) addressLine += 'FB: ' + this.esc(storeFacebook) + '<br>';
            if (v.receipt_show_contact === '1' && storeContact) addressLine += 'Contact: ' + this.esc(storeContact);
            if (v.receipt_show_tax_id === '1' && storeTaxId) addressLine += (addressLine ? ' • ' : '') + 'Non-VAT: ' + this.esc(storeTaxId);
            if (v.receipt_header_text) addressLine += `<br><span style="font-size:9px;">${this.esc(v.receipt_header_text)}</span>`;

            let html = `
        <div style="border-bottom:2px solid #111; padding-bottom:8px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                ${v.receipt_show_store_name === '1' ? `<div style="font-size:15px; font-weight:700;">${this.esc(storeName)}</div>` : ''}
                <div style="font-size:10px; color:#555; line-height:1.3;">${addressLine}</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:15px; font-weight:700; color:#9ca3af;">INVOICE</div>
                <div style="font-size:10px; color:#555;">Ref: ELLA-20260221-001</div>
                <div style="margin-top:6px; font-size:10px; color:#333; text-align:right;">
                    ${v.receipt_show_customer === '1' ? '<div>BILL TO: <strong>Walk-in Customer</strong></div>' : ''}
                    ${v.receipt_show_cashier === '1' ? '<div>CASHIER: <strong>Staff Member</strong></div>' : ''}
                </div>
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; font-size:11px; border-bottom:1px solid #eee; padding-bottom:5px; margin-bottom:6px;">
            <div><strong>ITEMS:</strong> 5</div>
        </div>

        <table style="width:100%; border-collapse:collapse; font-size:11px;">
            <thead>
                <tr style="background:#111; color:#fff;">
                    <th style="padding:4px 6px; text-align:center; width:35px;">QTY</th>
                    <th style="padding:4px 6px; text-align:left;">DESCRIPTION</th>
                    <th style="padding:4px 6px; text-align:right; width:70px;">PRICE</th>
                    <th style="padding:4px 6px; text-align:right; width:70px;">AMOUNT</th>
                </tr>
            </thead>
            <tbody>${itemRows}</tbody>
        </table>

        <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:10px;">
            <div style="width:35%; text-align:center;">
                <div style="border-top:1px solid #111; height:15px;"></div>
                <div style="font-size:9px; font-weight:600;">Customer Signature</div>
            </div>
            <div style="width:180px;">
                <div style="display:flex; justify-content:space-between; font-size:10px;"><span>Subtotal</span><span>₱${fmt(subtotal)}</span></div>
                ${v.receipt_show_item_discount === '1' && totalItemDiscount > 0 ? `<div style="display:flex; justify-content:space-between; font-size:10px; color:#dc2626;"><span>Discounts</span><span>-₱${fmt(totalItemDiscount)}</span></div>` : ''}
                <div style="display:flex; justify-content:space-between; font-weight:700; font-size:14px; border-top:1px solid #111; padding-top:3px; margin-top:2px;"><span>TOTAL</span><span>₱${fmt(grandTotal)}</span></div>
                ${v.receipt_show_payment_method === '1' ? '<div style="font-size:9px; color:#555; text-align:right; margin-top:1px;">Method: CASH</div>' : ''}
            </div>
        </div>
        `;

            document.getElementById('receipt-preview-a4').innerHTML = html;
        },

        async save() {
            const btn = document.getElementById('btn-save');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';

            try {
                const values = this.getValues();

                const res = await fetch('../../api/system/save_settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(values)
                });

                const data = await res.json();

                if (data.success) {
                    // Update window.STORE_SETTINGS with new values
                    Object.assign(window.STORE_SETTINGS || {}, values);

                    // Show success indicator
                    const indicator = document.getElementById('save-indicator');
                    indicator.style.display = 'inline-block';
                    setTimeout(() => { indicator.style.display = 'none'; }, 2500);
                } else {
                    EllaToast.error('Failed to save: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                console.error('Save error:', err);
                EllaToast.error('Network error while saving settings.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        },

        esc(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    document.addEventListener('DOMContentLoaded', () => ReceiptTemplate.init());
</script>

<?php require_once '../../includes/footer.php'; ?>