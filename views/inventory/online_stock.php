<?php
// views/inventory/online_stock.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    denyAccess("You do not have permission to access online stock management.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    /* Simplified & Clean Admin Dashboard UI */
    :root {
        --shopee-primary: #ee4d2d;
        --shopee-hover: #d73a1c;
        --shopee-light: rgba(238, 77, 45, 0.15); /* Dark mode default */
        
        --radius-md: 12px;
        --radius-lg: 16px;
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.2);
        
        /* Status Colors - Dark */
        --status-success: #34d399;
        --status-success-bg: rgba(16, 185, 129, 0.2);
        --status-warning: #fbbf24;
        --status-warning-bg: rgba(245, 158, 11, 0.2);
        --status-danger: #f87171;
        --status-danger-bg: rgba(239, 68, 68, 0.2);
        --status-neutral-bg: rgba(255, 255, 255, 0.1);
        --status-neutral-text: #cbd5e1;
        
        --test-banner-bg: rgba(59, 130, 246, 0.15);
        --test-banner-border: rgba(59, 130, 246, 0.3);
        --test-banner-text: #60a5fa;
    }

    [data-theme="light"] {
        --shopee-light: #fff5f3;
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);

        /* Status Colors - Light */
        --status-success: #10b981;
        --status-success-bg: #d1fae5;
        --status-warning: #f59e0b;
        --status-warning-bg: #fef3c7;
        --status-danger: #ef4444;
        --status-danger-bg: #fee2e2;
        --status-neutral-bg: #f3f4f6;
        --status-neutral-text: #4b5563;

        --test-banner-bg: #eff6ff;
        --test-banner-border: #bfdbfe;
        --test-banner-text: #1e40af;
    }

    body {
        /* Handled by global styles.css */
    }

    /* Typography */
    .h-title {
        font-weight: 700;
        color: var(--text-primary);
        font-size: 1.5rem;
        letter-spacing: -0.025em;
    }

    .sub-title {
        color: var(--text-secondary);
        font-size: 0.875rem;
    }

    /* Clean Buttons */
    .btn-shopee {
        background-color: var(--shopee-primary);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        padding: 0.5rem 1.25rem;
        transition: all 0.2s ease;
    }

    .btn-shopee:hover {
        background-color: var(--shopee-hover);
        color: white;
    }

    .btn-outline-shopee {
        background-color: transparent;
        color: var(--shopee-primary);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-weight: 600;
        padding: 0.5rem 1.25rem;
        transition: all 0.2s ease;
    }

    .btn-outline-shopee:hover {
        background-color: var(--shopee-light);
        border-color: var(--shopee-primary);
    }

    /* Minimal Cards */
    .stat-card {
        background: var(--bg-surface);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-color);
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: var(--shadow-sm);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    /* Simple Tabs */
    .clean-tabs {
        display: flex;
        gap: 1rem;
        border-bottom: 1px solid var(--border-color);
        margin-bottom: 1.5rem;
    }

    .clean-tab {
        padding: 0.75rem 0;
        font-weight: 600;
        color: var(--text-secondary);
        background: transparent;
        border: none;
        border-bottom: 2px solid transparent;
        transition: all 0.2s;
        cursor: pointer;
        font-size: 0.95rem;
    }

    .clean-tab:hover {
        color: var(--shopee-primary);
    }

    .clean-tab.active {
        color: var(--shopee-primary);
        border-bottom-color: var(--shopee-primary);
    }

    .tab-content {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    .tab-content.active {
        display: block;
    }

    /* Minimal Table */
    .clean-table-container {
        background: var(--bg-surface);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-color);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .clean-table {
        width: 100%;
        border-collapse: collapse;
    }

    .clean-table th {
        background: var(--bg-body);
        color: var(--text-secondary);
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
        text-align: left;
    }

    .clean-table td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
        vertical-align: middle;
    }

    .clean-table tr:last-child td {
        border-bottom: none;
    }

    .clean-table tbody tr:hover {
        background-color: var(--bg-surface-hover);
    }

    /* Status Badges */
    .badge-soft {
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
    }

    .badge-success {
        background: var(--status-success-bg);
        color: var(--status-success);
    }

    .badge-warning {
        background: var(--status-warning-bg);
        color: var(--status-warning);
    }

    .badge-danger {
        background: var(--status-danger-bg);
        color: var(--status-danger);
    }

    .badge-neutral {
        background: var(--status-neutral-bg);
        color: var(--status-neutral-text);
    }

    /* Modal Inputs */
    .qty-control {
        display: flex;
        align-items: center;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        overflow: hidden;
        background: transparent;
    }

    .qty-btn {
        border: none;
        background: transparent;
        padding: 0.5rem 1rem;
        font-weight: bold;
        color: var(--text-secondary);
    }

    .qty-btn:hover {
        background: var(--bg-surface-hover);
        color: var(--text-primary);
    }

    .qty-input {
        border: none;
        width: 60px;
        text-align: center;
        font-weight: 600;
        font-size: 1rem;
        background: transparent;
        color: var(--text-primary);
    }

    .qty-input:focus {
        outline: none;
    }

    /* Test Banner */
    .test-banner {
        background: var(--test-banner-bg);
        border: 1px solid var(--test-banner-border);
        color: var(--test-banner-text);
        padding: 0.75rem 1rem;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        font-weight: 500;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    /* Utility */
    .text-shopee {
        color: var(--shopee-primary);
    }
</style>

<div class="container-fluid p-4" style="max-width: 1400px; margin: 0 auto;">

    <!-- TEST MODE BANNER -->
    <div id="testModeBanner" class="test-banner">
        <i class="fa-solid fa-flask"></i>
        <div class="flex-grow-1">
            <strong>Test Mode is Active.</strong> Changes made here are simulated and will not affect your live Shopee
            store.
        </div>
        <button class="btn btn-sm btn-link text-decoration-none" onclick="toggleTestMode()">Turn Off</button>
    </div>

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h-title mb-1">Shopee Stock Management</h1>
            <p class="sub-title mb-0">Control how much stock is synced to your Shopee store.</p>
        </div>
        <div class="d-flex gap-3">
            <button class="btn btn-outline-shopee" onclick="switchTab('settings')"><i
                    class="fa-solid fa-gear me-2"></i>Settings</button>
            <button class="btn btn-shopee" onclick="triggerSync()"><i class="fa-solid fa-rotate me-2"></i>Sync All
                Now</button>
        </div>
    </div>

    <!-- SUMMARY CARDS -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--shopee-light); color: var(--shopee-primary);">
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
                <div>
                    <div class="sub-title">Connected Products</div>
                    <div class="fs-4 fw-bold" id="statConnected">1,240</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: #e0f2fe; color: #0369a1;">
                    <i class="fa-solid fa-cubes"></i>
                </div>
                <div>
                    <div class="sub-title">Total Online Stock</div>
                    <div class="fs-4 fw-bold" id="statOnlineStock">15,300</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--status-warning-bg); color: var(--status-warning);">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <div class="sub-title">Low Stock / Warnings</div>
                    <div class="fs-4 fw-bold text-warning" id="statWarnings">8</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--status-danger-bg); color: var(--status-danger);">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
                <div>
                    <div class="sub-title">Sync Errors</div>
                    <div class="fs-4 fw-bold text-danger" id="statErrors">2</div>
                </div>
            </div>
        </div>
    </div>

    <!-- NAVIGATION TABS -->
    <div class="clean-tabs">
        <button class="clean-tab active" onclick="switchTab('allocation', this)">Inventory Allocation</button>
        <button class="clean-tab" onclick="switchTab('mapping', this)">Product Mapping</button>
        <button class="clean-tab" onclick="switchTab('logs', this)">Sync Logs</button>
        <button class="clean-tab" onclick="switchTab('settings', this)" style="display:none;"
            id="btnSettingsTab">Settings</button>
    </div>

    <!-- ========================================== -->
    <!-- TAB: ALLOCATION (MAIN TABLE) -->
    <!-- ========================================== -->
    <div id="tab-allocation" class="tab-content active">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="input-group" style="max-width: 350px;">
                <span class="input-group-text border-end-0 text-secondary" style="background: var(--bg-surface);"><i
                        class="fa-solid fa-search"></i></span>
                <input type="text" class="form-control border-start-0 ps-0"
                    placeholder="Search by product name or SKU..." style="background: var(--bg-surface);">
            </div>
            <select class="form-select" style="max-width: 200px;">
                <option>All Statuses</option>
                <option>Warnings Only</option>
                <option>Not Connected</option>
            </select>
        </div>

        <div class="clean-table-container">
            <table class="clean-table">
                <thead>
                    <tr>
                        <th>Product & SKU</th>
                        <th class="text-center">Physical Stock<br><span
                                class="text-muted fw-normal text-capitalize">Total in POS</span></th>
                        <th class="text-center">Shopee Stock<br><span
                                class="text-shopee fw-normal text-capitalize">Allocated Online</span></th>
                        <th class="text-center">Remaining<br><span class="text-muted fw-normal text-capitalize">Store
                                Use</span></th>
                        <th>Sync Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="inventoryBody">
                    <!-- Populated by JS -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB: PRODUCT MAPPING -->
    <!-- ========================================== -->
    <div id="tab-mapping" class="tab-content">
        <div class="clean-table-container p-4 text-center">
            <div class="mb-4">
                <i class="fa-solid fa-link text-secondary" style="font-size: 3rem;"></i>
                <h4 class="mt-3">Link POS Items to Shopee</h4>
                <p class="text-secondary">Ensure your local products match the ones listed on your Shopee store.</p>
            </div>

            <table class="clean-table text-left mb-4">
                <thead>
                    <tr>
                        <th>POS Product</th>
                        <th>Shopee Product Link</th>
                        <th>Match Confidence</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="mappingBody">
                    <!-- Populated by JS -->
                </tbody>
            </table>

            <button class="btn btn-outline-shopee"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Auto-Match
                Unlinked Products</button>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB: SYNC LOGS -->
    <!-- ========================================== -->
    <div id="tab-logs" class="tab-content">
        <div class="clean-table-container">
            <table class="clean-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Event</th>
                        <th>Details</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="logsBody">
                    <!-- Populated by JS -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TAB: SETTINGS -->
    <!-- ========================================== -->
    <div id="tab-settings" class="tab-content">
        <div class="row">
            <div class="col-md-6">
                <div class="clean-table-container p-4 mb-4">
                    <h5 class="mb-4">Store Connection</h5>
                    <div class="d-flex align-items-center gap-3 p-3 rounded mb-4 border" style="background: var(--bg-body);">
                        <div class="stat-icon text-shopee border" style="background: var(--bg-surface);"><i class="fa-solid fa-shop"></i></div>
                        <div class="flex-grow-1">
                            <div class="fw-bold">Ella Motor Parts Official</div>
                            <div class="text-success small"><i class="fa-solid fa-circle me-1"
                                    style="font-size: 8px;"></i>Connected and Active</div>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary">Disconnect</button>
                    </div>
                    <button class="btn btn-outline-shopee w-100">Refresh API Tokens</button>
                </div>
            </div>
            <div class="col-md-6">
                <div class="clean-table-container p-4">
                    <h5 class="mb-4">Preferences</h5>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="fw-bold">Test Mode</div>
                            <div class="small text-secondary">Simulate API calls safely</div>
                        </div>
                        <div class="form-check form-switch fs-4">
                            <input class="form-check-input" type="checkbox" id="settingTestMode" checked
                                onchange="toggleTestMode()">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="fw-bold">Auto-Sync on Sale</div>
                            <div class="small text-secondary">Update Shopee instantly when POS sells an item</div>
                        </div>
                        <div class="form-check form-switch fs-4">
                            <input class="form-check-input" type="checkbox" checked>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ========================================== -->
<!-- SIMPLE ALLOCATION MODAL -->
<!-- ========================================== -->
<div class="modal fade" id="allocationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: var(--radius-lg); background: var(--bg-surface);">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Update Stock Allocation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: var(--btn-close-filter);"></button>
            </div>
            <div class="modal-body">
                
                <div class="mb-4">
                    <div class="fw-bold fs-5" id="modalName">Product Name</div>
                    <div class="text-secondary small">SKU: <span id="modalSku">...</span></div>
                </div>

                <!-- Simple Visual Formula -->
                <div class="d-flex justify-content-between align-items-center p-3 rounded mb-4 border" style="background: var(--bg-body);">
                    <div class="text-center">
                        <div class="text-secondary small mb-1">Physical Stock</div>
                        <div class="fw-bold fs-4" id="modalPhysical">0</div>
                    </div>
                    <div class="text-secondary"><i class="fa-solid fa-minus"></i></div>
                    <div class="text-center">
                        <div class="text-shopee small mb-1">Shopee Stock</div>
                        <div class="qty-control">
                            <button class="qty-btn" onclick="updateModalQty(-1)"><i
                                    class="fa-solid fa-minus"></i></button>
                            <input type="number" class="qty-input" id="modalOnlineInput" value="0"
                                oninput="calculateModal()">
                            <button class="qty-btn" onclick="updateModalQty(1)"><i
                                    class="fa-solid fa-plus"></i></button>
                        </div>
                    </div>
                    <div class="text-secondary"><i class="fa-solid fa-equals"></i></div>
                    <div class="text-center">
                        <div class="text-secondary small mb-1">Remaining</div>
                        <div class="fw-bold fs-4" id="modalRemaining">0</div>
                    </div>
                </div>

                <div id="modalWarning" class="alert alert-warning py-2 small d-none mb-0">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><span id="modalWarningText">Warning text</span>
                </div>

            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-shopee" onclick="saveAllocation()">Save & Sync</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Mock Data System
    let isTestMode = true;
    let currentEditId = null;

    const MOCK_INVENTORY = [
        { id: 1, name: "Motorcycle Helmet DOT Black", sku: "HELM-01", physical: 150, shopee: 20, status: "Synced" },
        { id: 2, name: "Premium Brake Pads", sku: "BRK-P02", physical: 80, shopee: 80, status: "Warning" },
        { id: 3, name: "Synthetic Engine Oil 10W-40", sku: "OIL-10W", physical: 200, shopee: 0, status: "Unlinked" },
        { id: 4, name: "LED Headlight Bulb H4", sku: "LED-H4", physical: 12, shopee: 10, status: "Low Stock" },
        { id: 5, name: "Custom Exhaust Pipe", sku: "EXH-C1", physical: 45, shopee: 15, status: "Error" }
    ];

    const MOCK_MAPPING = [
        { pos: "Motorcycle Helmet DOT Black", sku: "HELM-01", shopee: "Helmet DOT (Black)", conf: "High (100%)" },
        { pos: "Premium Brake Pads", sku: "BRK-P02", shopee: "Brake Pads Pro Series", conf: "High (95%)" },
        { pos: "Synthetic Engine Oil 10W-40", sku: "OIL-10W", shopee: "Not mapped", conf: "N/A" }
    ];

    const MOCK_LOGS = [
        { time: "Today, 10:30 AM", event: "Stock Updated", details: "Motorcycle Helmet DOT Black (20 → 18)", status: "Success" },
        { time: "Today, 09:15 AM", event: "API Sync", details: "Custom Exhaust Pipe", status: "Failed" },
        { time: "Yesterday, 04:00 PM", event: "Manual Allocation", details: "Premium Brake Pads (Set to 80)", status: "Success" }
    ];

    function getBadge(status) {
        switch (status) {
            case 'Synced': case 'Success': return `<span class="badge-soft badge-success"><i class="fa-solid fa-check"></i> ${status}</span>`;
            case 'Warning': case 'Low Stock': return `<span class="badge-soft badge-warning"><i class="fa-solid fa-triangle-exclamation"></i> ${status}</span>`;
            case 'Error': case 'Failed': return `<span class="badge-soft badge-danger"><i class="fa-solid fa-xmark"></i> ${status}</span>`;
            default: return `<span class="badge-soft badge-neutral"><i class="fa-solid fa-link-slash"></i> ${status}</span>`;
        }
    }

    function renderUI() {
        // Render Inventory
        const invBody = document.getElementById('inventoryBody');
        invBody.innerHTML = '';
        MOCK_INVENTORY.forEach(item => {
            const remaining = item.physical - item.shopee;
            let remColor = "text-main";
            if (remaining < 0) remColor = "text-danger";
            else if (remaining < 10) remColor = "text-warning";

            invBody.innerHTML += `
            <tr>
                <td>
                    <div class="fw-bold">${item.name}</div>
                    <div class="small text-secondary">${item.sku}</div>
                </td>
                <td class="text-center fw-bold fs-6">${item.physical}</td>
                <td class="text-center fw-bold fs-6 text-shopee">${item.shopee}</td>
                <td class="text-center fw-bold fs-6 ${remColor}">${remaining}</td>
                <td>${getBadge(item.status)}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-secondary px-3" onclick="openModal(${item.id})">Edit</button>
                </td>
            </tr>
        `;
        });

        // Render Mapping
        const mapBody = document.getElementById('mappingBody');
        mapBody.innerHTML = '';
        MOCK_MAPPING.forEach(item => {
            const isMapped = item.shopee !== "Not mapped";
            mapBody.innerHTML += `
            <tr>
                <td>
                    <div class="fw-bold">${item.pos}</div>
                    <div class="small text-secondary">${item.sku}</div>
                </td>
                <td>
                    ${isMapped ? `<span class="text-shopee fw-bold"><i class="fa-solid fa-bag-shopping me-2"></i>${item.shopee}</span>` : `<span class="text-secondary fst-italic">No link</span>`}
                </td>
                <td><span class="badge-soft ${isMapped ? 'badge-success' : 'badge-neutral'}">${item.conf}</span></td>
                <td class="text-end">
                    <button class="btn btn-sm ${isMapped ? 'btn-outline-secondary' : 'btn-outline-shopee'} px-3">${isMapped ? 'Unlink' : 'Connect'}</button>
                </td>
            </tr>
        `;
        });

        // Render Logs
        const logsBody = document.getElementById('logsBody');
        logsBody.innerHTML = '';
        MOCK_LOGS.forEach(log => {
            logsBody.innerHTML += `
            <tr>
                <td class="text-secondary small">${log.time}</td>
                <td class="fw-bold">${log.event}</td>
                <td>${log.details}</td>
                <td>${getBadge(log.status)}</td>
            </tr>
        `;
        });
    }

    // Modal Logic
    let allocModal;
    document.addEventListener('DOMContentLoaded', () => {
        allocModal = new bootstrap.Modal(document.getElementById('allocationModal'));
        renderUI();
    });

    function openModal(id) {
        const item = MOCK_INVENTORY.find(i => i.id === id);
        currentEditId = id;

        document.getElementById('modalName').innerText = item.name;
        document.getElementById('modalSku').innerText = item.sku;
        document.getElementById('modalPhysical').innerText = item.physical;
        document.getElementById('modalOnlineInput').value = item.shopee;

        calculateModal();
        allocModal.show();
    }

    function updateModalQty(change) {
        const input = document.getElementById('modalOnlineInput');
        let val = parseInt(input.value) || 0;
        val = Math.max(0, val + change);
        input.value = val;
        calculateModal();
    }

    function calculateModal() {
        const physical = parseInt(document.getElementById('modalPhysical').innerText);
        const shopee = parseInt(document.getElementById('modalOnlineInput').value) || 0;
        const remaining = physical - shopee;

        const remEl = document.getElementById('modalRemaining');
        const warning = document.getElementById('modalWarning');
        const warnText = document.getElementById('modalWarningText');

        remEl.innerText = remaining;

        if (remaining < 0) {
            remEl.className = "fw-bold fs-4 text-danger";
            warning.className = "alert alert-danger py-2 small d-block mb-0 mt-3";
            warnText.innerText = "Error: Cannot allocate more than physical stock.";
        } else if (remaining < 5) {
            remEl.className = "fw-bold fs-4 text-warning";
            warning.className = "alert alert-warning py-2 small d-block mb-0 mt-3";
            warnText.innerText = "Warning: Store will have very little stock left.";
        } else {
            remEl.className = "fw-bold fs-4 text-success";
            warning.className = "d-none";
        }
    }

    function saveAllocation() {
        const shopee = parseInt(document.getElementById('modalOnlineInput').value) || 0;
        const item = MOCK_INVENTORY.find(i => i.id === currentEditId);

        if ((item.physical - shopee) < 0) {
            alert("Invalid allocation: Exceeds physical stock.");
            return;
        }

        item.shopee = shopee;
        item.status = "Synced";

        renderUI();
        allocModal.hide();

        if (typeof EllaToast !== 'undefined') {
            EllaToast.success("Stock allocated successfully (Test Mode)");
        }
    }

    // UI Helpers
    function switchTab(tabId, btn) {
        document.querySelectorAll('.clean-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

        if (btn) btn.classList.add('active');
        else {
            // If triggered programmatically (like settings button)
            document.querySelectorAll('.clean-tab').forEach(t => {
                if (t.innerText.toLowerCase() === tabId) t.classList.add('active');
            });
            document.getElementById('btnSettingsTab').classList.add('active');
        }

        document.getElementById('tab-' + tabId).classList.add('active');
    }

    function toggleTestMode() {
        isTestMode = !isTestMode;
        document.getElementById('settingTestMode').checked = isTestMode;
        const banner = document.getElementById('testModeBanner');
        banner.style.display = isTestMode ? 'flex' : 'none';
    }

    function triggerSync() {
        alert(isTestMode ? "Simulating sync process..." : "Live sync started!");
    }

</script>

<?php require_once '../../includes/footer.php'; ?>