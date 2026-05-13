<?php
// views/payables/index.php
// Accounts Payable - Supplier Payments Dashboard

require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
requirePermission('view_payables');

$page_title = 'Accounts Payable - Ella POS';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    /* Payables Dashboard - Premium Modernization */
    .payables-page {
        animation: ellaFadeIn 0.5s ease-out;
    }

    /* Status Badges - Refined */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .status-pending {
        background: rgba(107, 114, 128, 0.1);
        color: #64748b;
        border: 1px solid rgba(107, 114, 128, 0.2);
    }

    .status-partial {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
        border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .status-paid {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .status-overdue {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.3);
        box-shadow: 0 0 10px rgba(239, 68, 68, 0.2);
        animation: status-glow-red 2s infinite;
    }

    .status-due-today {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
        border: 1px solid rgba(59, 130, 246, 0.2);
    }

    @keyframes status-glow-red {

        0%,
        100% {
            box-shadow: 0 0 5px rgba(239, 68, 68, 0.2);
        }

        50% {
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.4);
        }
    }

    /* Stats Cards - Glassmorphism */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
    }

    .stat-card {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        padding: 24px;
        display: flex;
        align-items: center;
        gap: 20px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .stat-card::after {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        background: radial-gradient(circle at top right, var(--primary-light), transparent 70%);
        opacity: 0.3;
        pointer-events: none;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.08);
        border-color: var(--primary-color);
    }

    .stat-card .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.05);
    }

    .stat-card .stat-content {
        flex: 1;
    }

    .stat-card .stat-value {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--text-primary);
        line-height: 1.2;
    }

    .stat-card .stat-label {
        font-size: 0.85rem;
        color: var(--text-secondary);
        font-weight: 600;
        margin-top: 2px;
    }

    .icon-total {
        background: rgba(139, 92, 246, 0.1);
        color: #8b5cf6;
    }

    .icon-pending {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }

    .icon-overdue {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    .icon-paid {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }

    /* Table Design */
    .card.payables-card {
        border-radius: 28px;
        border: 1px solid var(--border-color);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }

    .table-payables thead th {
        background: var(--bg-surface);
        padding: 20px 24px;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-secondary);
        border-bottom: 1px solid var(--border-color);
    }

    .table-payables tbody td {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
    }

    .table-payables tbody tr:last-child td {
        border-bottom: none;
    }

    .supplier-info .supplier-name {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 2px;
    }

    .supplier-info .supplier-contact {
        font-size: 0.8rem;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .po-ref-badge {
        font-family: 'JetBrains Mono', 'Roboto Mono', monospace;
        font-size: 0.8rem;
        padding: 4px 10px;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        border-radius: 8px;
        font-weight: 600;
    }

    .amount-cell {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .balance-cell {
        color: #ef4444;
        background: rgba(239, 68, 68, 0.05);
        display: inline-block;
        padding: 4px 10px;
        border-radius: 8px;
    }

    /* Modal Styling */
    .modal-content {
        border-radius: 32px;
        border: none;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 24px 32px;
    }

    .payment-summary {
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 24px;
        margin-bottom: 24px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px dashed var(--border-color);
    }

    .summary-row:last-child {
        border-bottom: none;
    }

    .summary-label {
        font-size: 0.85rem;
        color: var(--text-secondary);
        font-weight: 600;
    }

    .summary-value {
        font-size: 1rem;
        font-weight: 800;
        color: var(--text-primary);
    }

    .balance-highlight {
        font-size: 1.4rem;
        color: #ef4444;
    }

    /* Timeline */
    .history-timeline {
        position: relative;
        padding-left: 20px;
    }

    .history-timeline::before {
        content: '';
        position: absolute;
        left: 36px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: var(--border-color);
    }

    .history-item {
        position: relative;
        padding: 20px 0 20px 48px;
        border-bottom: none;
    }

    .history-icon {
        position: absolute;
        left: 20px;
        top: 22px;
        width: 32px;
        height: 32px;
        z-index: 2;
        box-shadow: 0 0 0 4px var(--card-bg);
    }

    /* Forms */
    .form-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 800;
        color: var(--text-secondary);
        margin-bottom: 8px;
    }

    .form-control,
    .form-select {
        border-radius: 14px;
        padding: 12px 16px;
        border-color: var(--border-color);
        background: var(--bg-surface);
        transition: all 0.2s;
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px var(--primary-light);
    }

    /* Upload Area */
    .upload-area {
        border: 2px dashed var(--border-color);
        border-radius: 20px;
        padding: 32px;
        text-align: center;
        background: var(--bg-surface);
        transition: all 0.3s;
    }

    .upload-area:hover {
        border-color: var(--primary-color);
        background: var(--primary-light);
    }

    /* Action Buttons */
    .btn-pay-action {
        padding: 8px 16px;
        border-radius: 12px;
        font-weight: 700;
        transition: all 0.3s;
    }

    .btn-pay-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    /* Modal Layout Separation */
    .modal-body .row.g-4 {
        position: relative;
    }

    @media (min-width: 992px) {
        .modal-column-right {
            border-left: 1px solid var(--border-color);
            padding-left: 40px !important;
        }

        .modal-column-left {
            padding-right: 40px !important;
        }
    }

    .reference-gallery {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        max-height: 500px;
        overflow-y: auto;
        padding: 12px;
        background: var(--bg-surface);
        border-radius: 20px;
        border: 1px solid var(--border-color);
    }

    .reference-gallery .img-container {
        width: calc(33.33% - 11px);
        aspect-ratio: 1;
    }

    .reference-gallery .img-container.large-ref {
        width: 100%;
        aspect-ratio: auto;
        max-height: 600px;
    }

    .img-container {
        position: relative;
        overflow: hidden;
        border-radius: 12px;
        cursor: pointer;
    }

    .img-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: white;
        opacity: 0;
        transition: all 0.3s;
        backdrop-filter: blur(2px);
    }

    .img-container:hover .img-overlay {
        opacity: 1;
    }

    .img-overlay i {
        font-size: 1.5rem;
        margin-bottom: 8px;
        transform: translateY(10px);
        transition: all 0.3s;
    }

    .img-container:hover .img-overlay i {
        transform: translateY(0);
    }

    .reference-gallery img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s;
    }

    .img-container:hover img {
        transform: scale(1.1);
    }

    /* Lightbox Enhanced */
    .lightbox-toolbar {
        position: absolute;
        top: -50px;
        right: 0;
        display: flex;
        gap: 12px;
    }

    .btn-lightbox {
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 8px 16px;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.2s;
    }

    .btn-lightbox:hover {
        background: white;
        color: black;
    }

    /* Date Edit Input */
    .date-edit-input {
        background: transparent;
        transition: all 0.2s;
    }

    .date-edit-input:hover,
    .date-edit-input:focus {
        background: var(--bg-surface);
        border-color: var(--primary-color) !important;
    }
</style>

<div class="payables-page">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="fw-800 mb-2">
                <i class="fas fa-money-bill-transfer me-3 text-primary"></i>Accounts Payable
            </h2>
            <p class="text-secondary mb-0 fw-500">Track and manage supplier debt and settlement history</p>
        </div>
        <div class="d-flex gap-3">
            <button class="btn btn-outline-secondary px-4 py-2" style="border-radius: 14px;" id="toggleAllBtn"
                onclick="toggleShowAll()">
                <i class="fas fa-filter me-2"></i> Show All
            </button>
            <button class="btn btn-primary px-4 py-2" style="border-radius: 14px;" onclick="loadPayables()">
                <i class="fas fa-sync-alt me-2"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="d-flex flex-wrap gap-3 mb-4 align-items-center justify-content-between">
        <div class="d-flex gap-3 flex-grow-1" style="max-width: 500px;">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0" style="border-radius: 14px 0 0 14px;">
                    <i class="fas fa-search text-secondary"></i>
                </span>
                <input type="text" id="searchInput" class="form-control border-start-0"
                    placeholder="Search supplier, PO ref, or contact..."
                    style="border-radius: 0 14px 14px 0; box-shadow: none;" oninput="applyFiltersAndSort()">
            </div>
        </div>
        <div class="d-flex gap-3 align-items-center">
            <label class="text-secondary fw-600 mb-0 text-nowrap"><i class="fas fa-sort me-2"></i>Sort by:</label>
            <select id="sortSelect" class="form-select" style="border-radius: 14px; min-width: 200px; box-shadow: none;"
                onchange="applyFiltersAndSort()">
                <option value="due_date_asc">Due Date (Nearest)</option>
                <option value="due_date_desc">Due Date (Farthest)</option>
                <option value="amount_desc">Total Invoice (Highest)</option>
                <option value="amount_asc">Total Invoice (Lowest)</option>
                <option value="supplier_asc">Supplier Name (A-Z)</option>
                <option value="status_due">Status (Requires Attention)</option>
            </select>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="stats-row" id="statsRow">
        <div class="stat-card">
            <div class="stat-icon icon-total"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="stat-content">
                <div class="stat-value" id="statTotal">0</div>
                <div class="stat-label">Total Invoices</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-pending"><i class="fas fa-clock-rotate-left"></i></div>
            <div class="stat-content">
                <div class="stat-value text-warning" id="statPending">₱0</div>
                <div class="stat-label">Total Outstanding</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-overdue"><i class="fas fa-circle-exclamation"></i></div>
            <div class="stat-content">
                <div class="stat-value text-danger" id="statOverdue">0</div>
                <div class="stat-label">Overdue Items</div>
            </div>
        </div>
    </div>

    <!-- Payables Table -->
    <div class="card payables-card mb-5">
        <div class="table-responsive">
            <table class="table table-hover align-middle table-payables mb-0" id="payablesTable">
                <thead>
                    <tr>
                        <th>Supplier Info</th>
                        <th>PO Reference</th>
                        <th>Due Date</th>
                        <th>Inv. Amount</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="spinner-border text-primary me-2"></div> Loading dashboard data...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-800">
                        <i class="fas fa-wallet me-3"></i>Payment Settlement
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <!-- Left Side: Summary & Items -->
                        <div class="col-lg-7 modal-column-left">
                            <div class="payment-summary mb-4">
                                <div class="row align-items-center">
                                    <div class="col-md-7">
                                        <h4 class="fw-800 mb-1" id="modal-supplier">Supplier Name</h4>
                                        <span class="po-ref-badge" id="modal-po-ref">REF-000</span>
                                    </div>
                                    <div class="col-md-5 text-end">
                                        <div class="summary-row">
                                            <span class="summary-label">Total Invoice:</span>
                                            <span class="summary-value">₱<span id="modal-total">0.00</span></span>
                                        </div>
                                        <div class="summary-row">
                                            <span class="summary-label">Settled:</span>
                                            <span class="summary-value text-success">₱<span
                                                    id="modal-paid">0.00</span></span>
                                        </div>
                                        <div class="summary-row border-0">
                                            <span class="summary-label">Outstanding:</span>
                                            <span class="summary-value balance-highlight">₱<span
                                                    id="modal-balance">0.00</span></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Reference Gallery -->
                            <div class="mb-4" id="reference-images-section" style="display: none;">
                                <h6 class="form-label mb-3"><i class="fas fa-images me-2"></i>Reference Attachments</h6>
                                <div class="reference-gallery" id="modal-reference-images"></div>
                            </div>

                            <!-- Receipt Items -->
                            <div id="receipt-items-section" style="display: none;">
                                <h6 class="form-label mb-3"><i class="fas fa-list-check me-2"></i>Invoice Breakdown</h6>
                                <div class="table-responsive rounded-4 border">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3">Item Description</th>
                                                <th class="text-center">Qty</th>
                                                <th class="text-end">Cost</th>
                                                <th class="text-end pe-3">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody id="modal-receipt-items"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side: Forms & History -->
                        <div class="col-lg-5 modal-column-right">
                            <!-- Tabs for Action vs History -->
                            <ul class="nav nav-pills mb-4 gap-2" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active rounded-pill px-4" data-bs-toggle="pill"
                                        data-bs-target="#tab-pay">Add Payment</button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link rounded-pill px-4" data-bs-toggle="pill"
                                        data-bs-target="#tab-history">History</button>
                                </li>
                            </ul>

                            <div class="tab-content">
                                <!-- Payment Form -->
                                <div class="tab-pane fade show active" id="tab-pay">
                                    <form id="add-payment-form" class="bg-light p-4 rounded-4 border">
                                        <input type="hidden" id="modal-payment-id">

                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Amount</label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-white border-end-0">₱</span>
                                                    <input type="number" class="form-control border-start-0"
                                                        id="payment-amount" step="0.01" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Method</label>
                                                <select class="form-select" id="payment-method">
                                                    <option value="cash">💵 Cash</option>
                                                    <option value="gcash">💳 GCash</option>
                                                    <option value="bank">🏦 Bank Transfer</option>
                                                    <option value="check">✍️ Check</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Reference / OR Number</label>
                                                <input type="text" class="form-control" id="payment-reference"
                                                    placeholder="Optional reference code">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Notes</label>
                                                <textarea class="form-control" id="payment-notes" rows="2"
                                                    placeholder="Internal notes..."></textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Proof of Payment</label>
                                                <div class="upload-area"
                                                    onclick="document.getElementById('payment-images').click()">
                                                    <i class="fas fa-camera text-primary mb-2 d-inline-block"></i>
                                                    <p class="small text-secondary mb-0">Upload screenshots or receipts
                                                    </p>
                                                    <input type="file" id="payment-images" class="d-none" multiple
                                                        accept="image/*">
                                                </div>
                                                <div class="upload-preview" id="image-preview"></div>
                                            </div>
                                            <div class="col-12 mt-4">
                                                <button type="button"
                                                    class="btn btn-primary w-100 py-3 rounded-4 fw-800"
                                                    onclick="submitPayment()" id="submit-payment-btn">
                                                    Confirm Settlement
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- History Tab -->
                                <div class="tab-pane fade" id="tab-history">
                                    <div class="history-timeline" id="modal-history">
                                        <div class="text-center py-5">
                                            <div class="spinner-border text-primary"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Lightbox Modal -->
<div class="modal fade" id="lightboxModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-transparent shadow-none border-0">
            <div class="modal-body text-center p-0 position-relative">
                <div class="lightbox-toolbar mb-3">
                    <a id="lightbox-download" href="" download class="btn-lightbox text-decoration-none">
                        <i class="fas fa-download me-2"></i>Download Full Size
                    </a>
                    <button type="button" class="btn-lightbox" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                </div>
                <img id="lightbox-image" src="" class="img-fluid rounded-4 shadow-lg"
                    style="max-height: 85vh; border: 4px solid white;">
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/css/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js"></script>
<script>
    const BASE_URL = "<?php echo BASE_URL; ?>";
    let showAll = false;
    let currentPaymentId = null;
    let allPayablesData = []; // Store raw data for search & sort

    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    const lightboxModal = new bootstrap.Modal(document.getElementById('lightboxModal'));
    let selectedFiles = [];

    // Toggle show all/pending only
    function toggleShowAll() {
        showAll = !showAll;
        const btn = document.getElementById('toggleAllBtn');
        btn.innerHTML = showAll
            ? '<i class="fas fa-filter me-2"></i> Pending Only'
            : '<i class="fas fa-list me-2"></i> Show All';
        loadPayables();
    }

    // Load Payables
    async function loadPayables() {
        const tbody = document.querySelector('#payablesTable tbody');
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5"><div class="spinner-border text-primary me-2"></div> Synchronizing Data...</td></tr>';

        try {
            const url = showAll ? `${BASE_URL}api/payables/payables.php?all=1` : `${BASE_URL}api/payables/payables.php`;
            const response = await fetch(url);
            const result = await response.json();

            if (result.status === 'success') {
                allPayablesData = result.data;
                applyFiltersAndSort();
                updateStats(result.data);
            } else {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-5">
                    <i class="fas fa-info-circle me-2"></i> Error: ${result.message}
                </td></tr>`;
            }
        } catch (error) {
            console.error("Fetch error:", error);
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-5">
                <i class="fas fa-wifi-slash me-2"></i> Connection error. Data could not be loaded.
            </td></tr>`;
        }
    }

    // Render Table
    function renderTable(data) {
        const tbody = document.querySelector('#payablesTable tbody');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted">No outstanding supplier payments found at this time.</td></tr>';
            return;
        }

        data.forEach((item, index) => {
            const balance = parseFloat(item.balance);
            const statusBadge = getStatusBadge(item.status_label, item.payment_status);

            const tr = document.createElement('tr');
            tr.style.opacity = '0';
            tr.style.transform = 'translateY(10px)';
            tr.style.animation = `ellaFadeIn 0.4s ease-out forwards ${index * 0.05}s`;

            tr.innerHTML = `
                <td>
                    <div class="supplier-info">
                        <div class="supplier-name">${escapeHtml(item.supplier_name)}</div>
                        <div class="supplier-contact"><i class="fas fa-user-circle fa-xs text-primary opacity-50"></i> ${item.contact_person || 'No contact person'}</div>
                    </div>
                </td>
                <td><span class="po-ref-badge">${item.po_ref}</span></td>
                <td class="fw-600 text-secondary" style="font-size: 0.85rem; padding-right: 20px;">
                    <div class="d-flex align-items-center gap-2">
                        <input type="date" class="form-control form-control-sm border border-secondary border-opacity-25 rounded-3 shadow-none px-2 date-edit-input" 
                            value="${item.due_date || ''}"
                            onchange="updateDueDate(${item.payment_id}, this.value)"
                            style="min-width: 130px; font-size: 0.8rem; font-weight: 600; cursor: pointer;"
                            title="Click to change due date">
                    </div>
                </td>
                <td class="amount-cell">₱${formatMoney(item.amount_due)}</td>
                <td class="amount-cell text-success" style="font-size: 0.85rem;">₱${formatMoney(item.paid_amount)}</td>
                <td class="amount-cell"><span class="balance-cell">₱${formatMoney(balance)}</span></td>
                <td>${statusBadge}</td>
                <td class="text-end">
                    ${item.payment_status !== 'paid' ? `
                        <button class="btn btn-primary btn-pay-action btn-sm" onclick="openPaymentModal(${item.payment_id})">
                            <i class="fas fa-wallet me-2"></i> Settled
                        </button>
                    ` : `
                        <button class="btn btn-outline-secondary btn-pay-action btn-sm" onclick="openPaymentModal(${item.payment_id})">
                            <i class="fas fa-eye me-2"></i> History
                        </button>
                    `}
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    // Apply Filters & Sort
    function applyFiltersAndSort() {
        const query = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const sortMode = document.getElementById('sortSelect')?.value || 'due_date_asc';

        // Filter
        let filtered = allPayablesData.filter(item => {
            const searchStr = `${item.supplier_name} ${item.po_ref} ${item.contact_person || ''}`.toLowerCase();
            return searchStr.includes(query);
        });

        // Sort
        filtered.sort((a, b) => {
            const dateA = new Date(a.due_date || '9999-12-31');
            const dateB = new Date(b.due_date || '9999-12-31');
            const amtA = parseFloat(a.amount_due || 0);
            const amtB = parseFloat(b.amount_due || 0);

            switch (sortMode) {
                case 'due_date_asc':
                    return dateA - dateB;
                case 'due_date_desc':
                    return dateB - dateA;
                case 'amount_desc':
                    return amtB - amtA;
                case 'amount_asc':
                    return amtA - amtB;
                case 'supplier_asc':
                    return (a.supplier_name || '').localeCompare(b.supplier_name || '');
                case 'status_due':
                    const prioritize = (status) => {
                        if (status === 'overdue') return 0;
                        if (status === 'due_today') return 1;
                        if (status === 'partial') return 2;
                        if (status === 'pending') return 3;
                        return 4;
                    };
                    return prioritize(a.status_label) - prioritize(b.status_label) || dateA - dateB;
                default:
                    return 0;
            }
        });

        renderTable(filtered);
    }

    // Update Due Date
    async function updateDueDate(paymentId, newDate) {
        if (!newDate) return;

        try {
            const response = await fetch(`${BASE_URL}api/payables/payables.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_due_date',
                    payment_id: paymentId,
                    due_date: newDate
                })
            });

            const result = await response.json();
            if (result.status === 'success') {
                EllaToast.success('Due date updated successfully');
                const item = allPayablesData.find(p => p.payment_id == paymentId);
                if (item) {
                    item.due_date = newDate;
                    // Re-evaluate their status_label locally
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    const parsedDate = new Date(newDate);
                    parsedDate.setHours(0, 0, 0, 0);
                    if (item.payment_status === 'pending' || item.payment_status === 'partial') {
                        if (parsedDate < today) item.status_label = 'overdue';
                        else if (parsedDate.getTime() === today.getTime()) item.status_label = 'due_today';
                        else item.status_label = item.payment_status;
                    }
                    applyFiltersAndSort();
                    updateStats(allPayablesData); // Overdue counting might change
                }
            } else {
                EllaToast.error('Failed to update due date: ' + (result.message || 'Unknown error'));
            }
        } catch (e) {
            console.error(e);
            EllaToast.error('Network Error: Connectivity lost.');
        }
    }

    // Update Stats
    function updateStats(data) {
        let totalCount = data.length;
        let pendingAmount = 0;
        let overdueCount = 0;

        data.forEach(item => {
            if (item.payment_status !== 'paid') {
                pendingAmount += parseFloat(item.balance);
                if (item.status_label === 'overdue') {
                    overdueCount++;
                }
            }
        });

        document.getElementById('statTotal').textContent = totalCount;
        document.getElementById('statPending').textContent = '₱' + formatMoney(pendingAmount);
        document.getElementById('statOverdue').textContent = overdueCount;
    }

    // Status Badge
    function getStatusBadge(label, originalStatus) {
        let className = 'status-pending';
        let icon = '<i class="fas fa-clock"></i>';
        let text = label.replace('_', ' ').toUpperCase();

        switch (label) {
            case 'overdue':
                className = 'status-overdue';
                icon = '<i class="fas fa-circle-exclamation"></i>';
                break;
            case 'due_today':
                className = 'status-due-today';
                icon = '<i class="fas fa-calendar-day"></i>';
                break;
            case 'paid':
                className = 'status-paid';
                icon = '<i class="fas fa-check-circle"></i>';
                break;
            case 'partial':
                className = 'status-partial';
                icon = '<i class="fas fa-hourglass-half"></i>';
                break;
            default:
                if (originalStatus === 'partial') {
                    className = 'status-partial';
                    icon = '<i class="fas fa-hourglass-half"></i>';
                }
                break;
        }
        return `<span class="status-badge ${className}">${icon} ${text}</span>`;
    }

    // Open Payment Modal
    async function openPaymentModal(paymentId) {
        currentPaymentId = paymentId;
        document.getElementById('modal-payment-id').value = paymentId;
        selectedFiles = [];
        document.getElementById('image-preview').innerHTML = '';
        document.getElementById('payment-images').value = '';

        // Reset and switch to payment tab by default
        const firstTab = document.querySelector('[data-bs-target="#tab-pay"]');
        if (firstTab) bootstrap.Tab.getOrCreateInstance(firstTab).show();

        // Load payment details
        try {
            const detailRes = await fetch(`${BASE_URL}api/payables/payables.php?id=${paymentId}`);
            const detailResult = await detailRes.json();

            if (detailResult.status === 'success') {
                const p = detailResult.data;
                document.getElementById('modal-supplier').textContent = p.supplier_name;
                document.getElementById('modal-po-ref').textContent = p.po_ref;
                document.getElementById('modal-total').textContent = formatMoney(p.amount);
                document.getElementById('modal-paid').textContent = formatMoney(p.paid_amount);
                document.getElementById('modal-balance').textContent = formatMoney(p.balance);
                document.getElementById('payment-amount').value = parseFloat(p.balance).toFixed(2);
                document.getElementById('payment-amount').max = parseFloat(p.balance);

                // Display Reference Images
                const refSection = document.getElementById('reference-images-section');
                const refGallery = document.getElementById('modal-reference-images');

                if (p.reference_images && p.reference_images.length > 0) {
                    refSection.style.display = 'block';
                    const isSingle = p.reference_images.length === 1;
                    refGallery.innerHTML = p.reference_images.map(img => `
                        <div class="img-container ${isSingle ? 'large-ref' : ''} lightbox-trigger" data-src="${BASE_URL}${img}">
                            <img src="${BASE_URL}${img}" alt="Reference">
                            <div class="img-overlay">
                                <i class="fas fa-expand-arrows-alt"></i>
                                <span class="small fw-800">VIEW FULL</span>
                            </div>
                        </div>
                    `).join('');
                } else {
                    refSection.style.display = 'none';
                    refGallery.innerHTML = '';
                }

                // Display Receipt Items
                const itemsSection = document.getElementById('receipt-items-section');
                const itemsTbody = document.getElementById('modal-receipt-items');

                if (p.items && p.items.length > 0) {
                    itemsSection.style.display = 'block';
                    itemsTbody.innerHTML = p.items.map(item => {
                        const subtotal = item.quantity * item.current_cost;
                        return `
                            <tr>
                                <td class="ps-3 py-3">
                                    <div class="fw-700">${escapeHtml(item.product_name)}</div>
                                    <small class="text-secondary opacity-75">${escapeHtml(item.variation_name)}</small>
                                </td>
                                <td class="text-center">${item.quantity}</td>
                                <td class="text-end">₱${formatMoney(item.current_cost)}</td>
                                <td class="text-end pe-3 fw-800 text-primary">₱${formatMoney(subtotal)}</td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    itemsSection.style.display = 'block';
                    itemsTbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-muted fst-italic">No items recorded for this receipt.</td></tr>`;
                }
            }
        } catch (e) {
            console.error(e);
        }

        // Load payment history
        loadPaymentHistory(paymentId);
        paymentModal.show();
    }

    // Load Payment History
    async function loadPaymentHistory(paymentId) {
        const container = document.getElementById('modal-history');
        container.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary spinner-border-sm me-2"></div> Loading records...</div>';

        try {
            const response = await fetch(`${BASE_URL}api/payables/payables.php?payment_id=${paymentId}`);
            const result = await response.json();

            if (result.status === 'success' && result.data.length > 0) {
                container.innerHTML = '';
                result.data.forEach((row, idx) => {
                    const attachments = row.attachments ? row.attachments.split(',') : [];
                    const attachmentHtml = attachments.length > 0
                        ? `<div class="mt-3">
                            <p class="small text-secondary fw-800 mb-2">PROOF OF PAYMENT:</p>
                            <div class="d-flex flex-wrap gap-2">
                                ${attachments.map(a => `
                                    <div class="img-container lightbox-trigger" style="width: 80px; aspect-ratio: 1;" data-src="${BASE_URL}${a}">
                                        <img src="${BASE_URL}${a}" alt="Proof">
                                        <div class="img-overlay">
                                            <i class="fas fa-search-plus" style="font-size: 0.8rem;"></i>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                           </div>`
                        : '';

                    container.innerHTML += `
                        <div class="history-item" style="animation: ellaFadeIn 0.3s ease-out forwards ${idx * 0.1}s; opacity:0;">
                            <div class="history-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm">
                                <i class="fas fa-check fa-xs"></i>
                            </div>
                            <div class="history-content">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div class="fw-800 text-success" style="font-size: 1.1rem;">₱${parseFloat(row.amount).toFixed(2)}</div>
                                    <span class="badge bg-light text-dark border rounded-pill px-3">${row.payment_method.toUpperCase()}</span>
                                </div>
                                <div class="text-secondary small mb-2">
                                    ${row.reference_no ? `<span class="me-3 fw-600">REF: ${row.reference_no}</span>` : ''}
                                    <span><i class="far fa-user me-1"></i> ${row.paid_by_name || 'System'}</span>
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    <i class="far fa-clock me-1"></i> ${new Date(row.paid_at).toLocaleString()}
                                </div>
                                ${row.notes ? `<div class="mt-2 p-2 bg-light border-start border-4 border-primary rounded-end small text-secondary">"${row.notes}"</div>` : ''}
                                ${attachmentHtml}
                            </div>
                        </div>
                    `;
                });
            } else {
                container.innerHTML = '<div class="text-center py-5 text-secondary"><i class="fas fa-receipt mb-3 d-block fa-2x opacity-25"></i> No settlement records found.</div>';
            }
        } catch (e) {
            console.error(e);
            container.innerHTML = '<div class="text-center text-danger py-5">Error: Failed to fetch history</div>';
        }
    }

    // Submit Payment
    async function submitPayment() {
        const amount = parseFloat(document.getElementById('payment-amount').value);
        const method = document.getElementById('payment-method').value;
        const reference = document.getElementById('payment-reference').value;
        const notes = document.getElementById('payment-notes').value;
        const paymentId = currentPaymentId;

        if (!amount || amount <= 0) {
            EllaToast.warning('Payment amount must be greater than zero.');
            return;
        }

        const btn = document.getElementById('submit-payment-btn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i>Processing...';

        try {
            const response = await fetch(`${BASE_URL}api/payables/payables.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    payment_id: paymentId,
                    amount: amount,
                    payment_method: method,
                    reference_no: reference,
                    notes: notes
                })
            });

            const result = await response.json();

            if (result.status === 'success') {
                const fileInput = document.getElementById('payment-images');
                if (fileInput.files.length > 0) {
                    const formData = new FormData();
                    formData.append('supplier_payment_id', paymentId);
                    formData.append('history_id', result.history_id);
                    for (let i = 0; i < fileInput.files.length; i++) {
                        formData.append('images[]', fileInput.files[i]);
                    }

                    await fetch(`${BASE_URL}api/payables/upload_payment_proof.php`, {
                        method: 'POST',
                        body: formData
                    });
                }

                EllaToast.success('Settlement successful!');
                paymentModal.hide();
                loadPayables();
                document.getElementById('add-payment-form').reset();
            } else {
                EllaToast.error('Settlement Failed: ' + (result.message || 'Server error'));
            }
        } catch (e) {
            console.error(e);
            EllaToast.error('Network Error: Connectivity lost.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Confirm Settlement';
        }
    }

    // Image Preview
    document.getElementById('payment-images').addEventListener('change', function (e) {
        const preview = document.getElementById('image-preview');
        preview.innerHTML = '';

        Array.from(this.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function (e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'preview-thumb';
                img.style.width = '60px';
                img.style.height = '60px';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '10px';
                img.style.margin = '4px';
                img.style.border = '2px solid var(--border-color)';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        });
    });

    // Lightbox
    function showLightbox(imageSrc) {
        document.getElementById('lightbox-image').src = imageSrc;
        document.getElementById('lightbox-download').href = imageSrc;
        lightboxModal.show();
    }

    // Helpers
    function formatMoney(amount) {
        return parseFloat(amount).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatDate(dateStr) {
        if (!dateStr) return 'No due date';
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        return new Date(dateStr).toLocaleDateString('en-US', options);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Global Click Listener for Lightbox
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('.lightbox-trigger');
        if (trigger) {
            e.preventDefault();
            const src = trigger.getAttribute('data-src');
            if (src) showLightbox(src);
        }
    });

    // Init
    document.addEventListener('DOMContentLoaded', loadPayables);
</script>

<?php require_once '../../includes/footer.php'; ?>