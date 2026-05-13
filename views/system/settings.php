<?php
// views/system/settings.php - System Settings Page
require_once '../../config/config.php';
require_once '../../includes/auth.php';

requireLogin();
requirePermission('manage_settings');

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    .settings-card {
        border: none;
        border-radius: 16px;
        overflow: hidden;
        transition: all 0.2s ease;
    }

    .settings-card:hover {
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .settings-card .card-header {
        border-bottom: 1px solid var(--border-color);
        padding: 16px 20px;
    }

    .settings-card .card-body {
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

    .form-label {
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-secondary);
        margin-bottom: 6px;
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

    .setting-input {
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .setting-input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }

    .setting-input.saved {
        border-color: #22c55e;
        box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15);
    }
</style>

<div class="container-fluid p-3 p-lg-4">

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h4 class="fw-bold mb-1">
                <i class="fa-solid fa-gear text-primary me-2"></i>System Settings
            </h4>
            <p class="text-muted mb-0 small">Manage store information, receipt configuration, and system defaults</p>
        </div>
        <div class="d-flex gap-2">
            <span class="save-indicator badge bg-success py-2 px-3" id="save-indicator">
                <i class="fa-solid fa-check me-1"></i> Saved!
            </span>
            <button class="btn btn-primary" onclick="SystemSettings.saveAll()" id="btn-save-all">
                <i class="fa-solid fa-floppy-disk me-1"></i> Save All Changes
            </button>
        </div>
    </div>

    <div class="row g-4">

        <!-- Store Information -->
        <div class="col-12 col-lg-6">
            <div class="card settings-card shadow-sm">
                <div class="card-header bg-transparent d-flex align-items-center gap-3">
                    <div class="section-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-store"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0">Store Information</h6>
                        <small class="text-muted">Business name, address, and contact details</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Store Name</label>
                        <input type="text" class="form-control setting-input" id="set-store_name"
                            placeholder="e.g. ELLA MOTOR PARTS">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Store Address</label>
                        <textarea class="form-control setting-input" id="set-store_address" rows="2"
                            placeholder="Full store address"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                            <input type="text" class="form-control setting-input" id="set-store_contact"
                                placeholder="e.g. 0961-974-7449">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Facebook Page</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-brands fa-facebook"></i></span>
                            <input type="text" class="form-control setting-input" id="set-store_facebook"
                                placeholder="e.g. Ella Motor Parts">
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Tax Registration ID</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-id-card"></i></span>
                            <input type="text" class="form-control setting-input" id="set-store_tax_id"
                                placeholder="e.g. 406-825-947-00000">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Receipt Settings -->
        <div class="col-12 col-lg-6">
            <div class="card settings-card shadow-sm">
                <div class="card-header bg-transparent d-flex align-items-center gap-3">
                    <div class="section-icon bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0">Receipt Settings</h6>
                        <small class="text-muted">Customize printed receipt content</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Receipt Footer Message</label>
                        <input type="text" class="form-control setting-input" id="set-receipt_footer"
                            placeholder="e.g. THANK YOU FOR YOUR PURCHASE!">
                        <div class="form-text">Appears at the bottom of every receipt</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Currency Symbol</label>
                        <input type="text" class="form-control setting-input" id="set-currency_symbol" placeholder="₱"
                            style="max-width: 100px;">
                    </div>
                    <div class="mt-4 pt-3 border-top">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <label class="form-check-label fw-bold" for="set-enable_pos_preview">Enable Preview Receipt</label>
                                <div class="form-text mt-0">Global toggle for the preview button in POS checkout</div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input setting-input" type="checkbox" role="switch" 
                                    id="set-enable_pos_preview" style="width: 2.5em; height: 1.25em; cursor: pointer;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Defaults -->
            <div class="card settings-card shadow-sm mt-4">
                <div class="card-header bg-transparent d-flex align-items-center gap-3">
                    <div class="section-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0">Inventory Defaults</h6>
                        <small class="text-muted">Default values for new products</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Default Low Stock Threshold</label>
                        <input type="number" class="form-control setting-input" id="set-default_low_stock_threshold"
                            min="0" max="9999" placeholder="5" style="max-width: 120px;">
                        <div class="form-text">Products with stock at or below this will show as "Low Stock"</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Default Price Tier</label>
                        <select class="form-select setting-input" id="set-default_price_tier" style="max-width: 200px;">
                            <option value="retail">Retail (SRP)</option>
                            <option value="wholesale">Wholesale</option>
                            <option value="dealer">Dealer</option>
                        </select>
                        <div class="form-text">Default pricing tier for Walk-in customers</div>
                    </div>
                </div>
            </div>

            <!-- Maintenance Mode -->
            <div class="card settings-card shadow-sm mt-4 border-danger border-opacity-25">
                <div class="card-header bg-transparent d-flex align-items-center gap-3">
                    <div class="section-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0">System Status</h6>
                        <small class="text-muted">Manage site accessibility</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <label class="form-check-label fw-bold" for="set-maintenance_mode">Maintenance Mode</label>
                            <div class="form-text mt-0">Redirect all non-admin users to the maintenance page</div>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input setting-input" type="checkbox" role="switch" 
                                id="set-maintenance_mode" style="width: 3em; height: 1.5em; cursor: pointer;">
                        </div>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0 py-2 small border-0">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        <strong>Warning:</strong> Enabling this will immediately restrict access for all staff and buyers.
                    </div>
                </div>
            </div>
        </div>


    </div>

    <!-- Receipt Preview -->
    <div class="row g-4 mt-1">
        <div class="col-12">
            <div class="card settings-card shadow-sm">
                <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="section-icon bg-info bg-opacity-10 text-info">
                            <i class="fa-solid fa-eye"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">Receipt Preview</h6>
                            <small class="text-muted">Live preview of how your receipt header will look</small>
                        </div>
                    </div>
                </div>
                <div class="card-body d-flex justify-content-center">
                    <div id="receipt-preview" style="
                        font-family: 'Courier New', monospace;
                        font-size: 12px;
                        max-width: 300px;
                        width: 100%;
                        padding: 20px;
                        border: 2px dashed var(--border-color);
                        border-radius: 12px;
                        text-align: center;
                        color: var(--text-primary);
                    ">
                        <!-- Filled by JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const SystemSettings = {
        settings: {},

        async init() {
            await this.load();
            this.updatePreview();

            // Live preview updates
            document.querySelectorAll('.setting-input').forEach(el => {
                el.addEventListener('input', () => this.updatePreview());
            });
        },

        async load() {
            try {
                const res = await fetch('../../api/system/get_settings.php');
                const data = await res.json();

                if (data.success) {
                    this.settings = data.settings;
                    this.populateForm();
                }
            } catch (err) {
                console.error('Failed to load settings:', err);
            }
        },

        populateForm() {
            for (const [key, obj] of Object.entries(this.settings)) {
                const el = document.getElementById('set-' + key);
                if (el) {
                    if (el.type === 'checkbox') {
                        el.checked = obj.value === '1' || obj.value === true;
                    } else {
                        el.value = obj.value;
                    }
                }
            }
        },

        getFormValues() {
            const values = {};
            document.querySelectorAll('.setting-input').forEach(el => {
                const key = el.id.replace('set-', '');
                if (el.type === 'checkbox') {
                    values[key] = el.checked ? '1' : '0';
                } else {
                    values[key] = el.value;
                }
            });
            return values;
        },

        async saveAll() {
            const btn = document.getElementById('btn-save-all');
            const indicator = document.getElementById('save-indicator');
            const originalHTML = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';

            try {
                const res = await fetch('../../api/system/save_settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.getFormValues())
                });
                const data = await res.json();

                if (data.success) {
                    // Flash green on all inputs
                    document.querySelectorAll('.setting-input').forEach(el => {
                        el.classList.add('saved');
                        setTimeout(() => el.classList.remove('saved'), 1500);
                    });

                    // Show saved indicator
                    indicator.style.display = 'inline-block';
                    setTimeout(() => { indicator.style.display = 'none'; }, 3000);

                    // Update sidebar store name dynamically
                    const storeName = document.getElementById('set-store_name').value;
                    const sidebarTitle = document.getElementById('sidebar-store-name');
                    if (sidebarTitle) sidebarTitle.textContent = storeName;

                    this.showToast('All settings saved successfully!', 'success');
                } else {
                    this.showToast('Failed to save: ' + (data.error || 'Unknown error'), 'danger');
                }
            } catch (err) {
                console.error(err);
                this.showToast('Network error while saving', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        },

        updatePreview() {
            const name = document.getElementById('set-store_name')?.value || 'STORE NAME';
            const address = document.getElementById('set-store_address')?.value || 'Store Address';
            const contact = document.getElementById('set-store_contact')?.value || '';
            const facebook = document.getElementById('set-store_facebook')?.value || '';
            const taxId = document.getElementById('set-store_tax_id')?.value || '';
            const footer = document.getElementById('set-receipt_footer')?.value || '';

            document.getElementById('receipt-preview').innerHTML = `
            <strong style="font-size:16px;">${this.escapeHtml(name)}</strong><br>
            ${this.escapeHtml(address)}<br>
            ${facebook ? 'Follow Us On Facebook: ' + this.escapeHtml(facebook) + '<br>' : ''}
            ${contact ? 'Contact No: ' + this.escapeHtml(contact) + '<br>' : ''}
            ${taxId ? 'Non-VAT Registered: ' + this.escapeHtml(taxId) : ''}
            <hr style="border-style: dashed; margin: 10px 0;">
            <div>Ref: <strong>ELLA-XXXX</strong></div>
            <div>Date: ${new Date().toLocaleDateString()}</div>
            <div>Customer: Walk-in</div>
            <div>Cashier: Staff</div>
            <hr style="border-style: dashed; margin: 10px 0;">
            <div>Sample Item ×1 ........... ₱100.00</div>
            <hr style="border-style: dashed; margin: 10px 0;">
            <div style="font-weight:bold;">TOTAL: ₱100.00</div>
            <hr style="border-style: dashed; margin: 10px 0;">
            <div style="font-size:10px;">${this.escapeHtml(footer)}</div>
        `;
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        showToast(message, type = 'info') {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'position-fixed bottom-0 end-0 p-3';
                container.style.zIndex = '1100';
                document.body.appendChild(container);
            }
            const toastEl = document.createElement('div');
            toastEl.className = `toast align-items-center text-bg-${type} border-0 shadow-lg show`;
            toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
            container.appendChild(toastEl);
            setTimeout(() => toastEl.remove(), 4000);
        }
    };

    document.addEventListener('DOMContentLoaded', () => SystemSettings.init());
</script>

<?php require_once '../../includes/footer.php'; ?>