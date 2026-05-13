<?php
// views/receivables/index.php — Accounts Receivable Ledger Overview
require_once '../../config/config.php';
require_once '../../includes/auth.php';

requirePermission('view_receivables');

const page_title = 'Accounts Receivable — Ella POS';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    :root {
        --primary-color: #3b82f6;
        --primary-light: rgba(59, 130, 246, 0.1);
        --success-color: #10b981;
        --warning-color: #f59e0b;
        --danger-color: #ef4444;
        --text-primary: #1e293b;
        --text-secondary: #64748b;
        --bg-surface: #ffffff;
        --border-color: #e2e8f0;
        --card-bg: rgba(255, 255, 255, 0.8);
    }

    /* Page Entrance Animation */
    @keyframes ellaFadeIn {
        from {
            opacity: 0;
            transform: translateY(15px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    body {
        background-color: #f8fafc;
        color: var(--text-primary);
        font-family: 'Inter', -apple-system, system-ui, sans-serif;
    }

    /* Stats Cards - Premium Glassmorphism */
    .stat-card {
        background: var(--card-bg);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 24px;
        padding: 24px;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        height: 100%;
    }

    .stat-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
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

    .stat-icon {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        margin-bottom: 16px;
        transition: transform 0.3s;
    }

    .stat-card:hover .stat-icon {
        transform: scale(1.1) rotate(-5deg);
    }

    .stat-label {
        color: var(--text-secondary);
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        margin-bottom: 4px;
    }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--text-primary);
        letter-spacing: -0.02em;
    }

    .icon-total {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }

    .icon-overdue {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    .icon-buyers {
        background: rgba(139, 92, 246, 0.1);
        color: #8b5cf6;
    }

    .icon-limit {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }

    /* Modern Refresh Animation */
    @keyframes status-glow-red {

        0%,
        100% {
            box-shadow: 0 0 5px rgba(239, 68, 68, 0.2);
        }

        50% {
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.4);
        }
    }

    .status-overdue {
        background: rgba(239, 68, 68, 0.1) !important;
        color: #ef4444 !important;
        animation: status-glow-red 2s infinite;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    /* Segmented Control / View Switcher */
    .segmented-control {
        background: #e2e8f0;
        padding: 4px;
        border-radius: 16px;
        display: inline-flex;
        gap: 4px;
    }

    .segmented-btn {
        border: none;
        background: transparent;
        padding: 8px 20px;
        border-radius: 12px;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-secondary);
        transition: all 0.2s;
    }

    .segmented-btn.active {
        background: white;
        color: var(--primary-color);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    /* Table Improvements */
    .card.receivables-card {
        border-radius: 28px;
        border: 1px solid var(--border-color);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .table thead th {
        background: #f8fafc;
        border-bottom: 2px solid #f1f5f9;
        color: var(--text-secondary);
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.05em;
        padding: 12px 10px;
    }

    .table tbody td {
        padding: 12px 10px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.85rem;
    }

    /* Aging Pills */
    .aging-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.8rem;
    }

    .aging-zero {
        color: var(--text-secondary);
        opacity: 0.5;
        font-weight: 500;
    }

    .aging-warn {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-color);
    }

    .aging-danger {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-color);
    }

    .aging-severe {
        background: var(--danger-color);
        color: white;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    /* Credit Progress Bar */
    .credit-progress {
        height: 6px;
        border-radius: 3px;
        background: #e2e8f0;
        overflow: hidden;
        margin-top: 8px;
    }

    .progress-fill {
        height: 100%;
        transition: width 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    /* Modals */
    .modal-content {
        border-radius: 32px;
        border: none;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
    }

    .modal-header {
        border-bottom: 1px solid var(--border-color);
        padding: 24px 32px;
    }

    .btn-action {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        border: 1px solid var(--border-color);
        background: white;
        color: var(--text-secondary);
    }

    .btn-action:hover {
        background: var(--primary-light);
        color: var(--primary-color);
        border-color: var(--primary-color);
        transform: translateY(-2px);
    }

    /* Hover Effects for rows */
    .table-hover tbody tr:hover {
        background-color: rgba(59, 130, 246, 0.02) !important;
    }
</style>

<!-- Page Header -->
<div class="row align-items-center mb-5 animate__animated animate__fadeIn">
    <div class="col-md-7">
        <h1 class="display-6 fw-800 mb-2" style="letter-spacing: -2px;">Accounts Receivable</h1>
        <p class="text-secondary mb-0 fw-500">Wholesale & dealer credit accounts management</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0">
        <div class="d-flex gap-3 justify-content-md-end">
            <button class="btn btn-white shadow-sm px-4 py-2 rounded-4 fw-600 border" onclick="AR.load()">
                <i class="fa-solid fa-rotate-right me-2 text-primary"></i>Refresh
            </button>
        </div>
    </div>
</div>

<!-- Stats Bar -->
<div class="row g-4 mb-5" id="ar-stats-row">
    <div class="col-6 col-md-3">
        <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s;">
            <div class="stat-icon icon-total">
                <i class="fa-solid fa-file-invoice-dollar text-primary"></i>
            </div>
            <div class="stat-label text-primary">Total Outstanding</div>
            <div class="stat-value" id="stat-total">₱0.00</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s;">
            <div class="stat-icon icon-overdue">
                <i class="fa-solid fa-clock text-danger"></i>
            </div>
            <div class="stat-label text-danger">Total Overdue</div>
            <div class="stat-value text-danger" id="stat-overdue">₱0.00</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.3s;">
            <div class="stat-icon icon-buyers">
                <i class="fa-solid fa-users text-purple"></i>
            </div>
            <div class="stat-label text-purple">Active Buyers</div>
            <div class="stat-value" id="stat-buyers">0</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.4s;">
            <div class="stat-icon icon-limit">
                <i class="fa-solid fa-shield-halved text-warning"></i>
            </div>
            <div class="stat-label text-warning">Over Credit Limit</div>
            <div class="stat-value text-warning" id="stat-overlimit">0</div>
        </div>
    </div>
</div>

<!-- View Switcher & Search Bar Row -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
    <div class="segmented-control shadow-sm">
        <button class="segmented-btn active view-btn" data-view="buyers">
            <i class="fa-solid fa-users me-2"></i>By Buyer
        </button>
        <button class="segmented-btn view-btn" data-view="transactions">
            <i class="fa-solid fa-list-check me-2"></i>By Transaction
        </button>
    </div>

    <!-- Search Input -->
    <div class="flex-grow-1" style="max-width: 450px;">
        <div class="input-group bg-white rounded-pill shadow-sm border p-1" style="border-radius: 20px !important;">
            <span class="input-group-text bg-transparent border-0 text-secondary ps-3">
                <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input type="text" id="receivables-search" class="form-control border-0 bg-transparent fw-600"
                placeholder="Search buyer, shop, or reference..." oninput="AR.handleSearch(this.value)"
                style="box-shadow: none; font-size: 0.9rem;">
            <button class="btn btn-link text-secondary border-0 p-0 me-3"
                onclick="document.getElementById('receivables-search').value=''; AR.handleSearch('')"
                title="Clear search">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>
</div>

<!-- Filter Groups Row -->
<div class="mb-4">
    <div class="d-flex gap-2 flex-wrap" id="buyer-filters">
        <button class="btn btn-sm btn-white border px-3 rounded-pill filter-tab active shadow-sm"
            data-filter="active">Active Accounts</button>
        <button class="btn btn-sm btn-white border px-3 rounded-pill filter-tab shadow-sm" data-filter="partial">
            <i class="fa-solid fa-circle text-warning me-1 small"></i>Partial
        </button>
        <button class="btn btn-sm btn-white border px-3 rounded-pill filter-tab shadow-sm" data-filter="overdue">
            <i class="fa-solid fa-circle text-danger me-1 small"></i>Overdue
        </button>
        <button class="btn btn-sm btn-white border px-3 rounded-pill filter-tab shadow-sm" data-filter="completed">
            <i class="fa-solid fa-circle text-success me-1 small"></i>Settled
        </button>
        <button class="btn btn-sm btn-white border px-3 rounded-pill filter-tab shadow-sm" data-filter="all">All
            Records</button>
        <button class="btn btn-sm btn-white border px-3 rounded-pill filter-tab shadow-sm" data-filter="due_soon">
            <i class="fa-solid fa-circle text-warning me-1 small"></i>Due Soon
        </button>
        <button class="btn btn-sm btn-white border px-3 rounded-pill filter-tab shadow-sm" data-filter="over_limit">
            <i class="fa-solid fa-circle text-primary me-1 small"></i>Over Limit
        </button>
    </div>

    <div class="d-flex gap-2 flex-wrap d-none" id="transaction-filters">
        <button class="btn btn-sm btn-white border px-3 rounded-pill tx-filter-tab active shadow-sm"
            data-tx-filter="all">All Transactions</button>
        <button class="btn btn-sm btn-white border px-3 rounded-pill tx-filter-tab shadow-sm"
            data-tx-filter="pending">Pending</button>
        <button class="btn btn-sm btn-white border px-3 rounded-pill tx-filter-tab shadow-sm"
            data-tx-filter="partial">Partial</button>
        <button class="btn btn-sm btn-white border px-3 rounded-pill tx-filter-tab shadow-sm"
            data-tx-filter="overdue">Overdue</button>
        <button class="btn btn-sm btn-white border px-3 rounded-pill tx-filter-tab shadow-sm"
            data-tx-filter="completed">Completed</button>
    </div>
</div>

<!-- Buyer Table -->
<div class="card receivables-card border-0 shadow-lg mb-5" id="buyer-view">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="ar-table" style="min-width: 1400px;">
                <thead>
                    <tr>
                        <th class="ps-4" style="min-width: 250px;">Buyer Entity</th>
                        <th>Active Invoices</th>
                        <th class="text-end">Current Balance</th>
                        <th class="text-end">1–30 Days</th>
                        <th class="text-end">31–60 Days</th>
                        <th class="text-end">61–90 Days</th>
                        <th class="text-end">91+ Days</th>
                        <th class="text-end">Total Due</th>
                        <th class="ps-4">Credit Utilization</th>
                        <th class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="ar-tbody">
                    <tr>
                        <td colspan="10" class="text-center py-5">
                            <div class="spinner-border text-primary border-4" style="width: 3rem; height: 3rem;"></div>
                            <p class="mt-3 text-secondary fw-500">Synchronizing ledger data...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Transaction Table -->
<div class="card receivables-card border-0 shadow-lg d-none mb-5" id="transaction-view">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tx-table" style="min-width: 1300px;">
                <thead>
                    <tr>
                        <th class="ps-4" style="min-width: 120px;">Date</th>
                        <th>Reference</th>
                        <th>Customer / Entity</th>
                        <th class="text-end">Total Amount</th>
                        <th class="text-end">Amount Paid</th>
                        <th class="text-end">Outstanding</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="tx-tbody">
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="spinner-border text-primary border-4" style="width: 3rem; height: 3rem;"></div>
                            <p class="mt-3 text-secondary fw-500">Fetching transaction history...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payment History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header bg-success text-white py-4 px-4 rounded-top-5">
                <h5 class="modal-title d-flex align-items-center fw-800">
                    <div class="bg-white text-success rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                        style="width: 40px; height: 40px;">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    Settlement History
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-4 bg-light border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-secondary fw-700 text-uppercase letter-spacing-1 mb-1">Sale Reference
                        </div>
                        <div class="fw-800 fs-5 text-dark" id="hist-ref">---</div>
                    </div>
                    <div class="text-end">
                        <div class="small text-secondary fw-700 text-uppercase letter-spacing-1 mb-1">Total Settled
                        </div>
                        <div class="fw-800 fs-5 text-success" id="hist-total-paid">₱0.00</div>
                    </div>
                </div>
                <div id="hist-content" class="p-2">
                    <!-- History items will be injected here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const BASE_URL = "<?= BASE_URL ?>";
    const AR = {
        allData: [],
        txData: [],
        activeFilter: 'active',
        activeView: 'buyers',
        activeTxFilter: 'all',
        currentGrandTotal: 0,
        currentTerms: [],
        searchQuery: '',
        searchTimeout: null,

        async load() {
            if (this.activeView === 'buyers') {
                this.loadBuyers();
            } else {
                this.loadTransactions();
            }
        },

        async loadBuyers() {
            document.getElementById('ar-tbody').innerHTML = `<tr><td colspan="10" class="text-center py-5">
            <div class="spinner-border text-primary border-4" style="width: 2.5rem; height: 2.5rem;"></div></td></tr>`;
            try {
                const res = await fetch(`${BASE_URL}api/receivables/ar_summary.php?filter=${this.activeFilter}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'Unknown error');

                this.allData = data.data;
                this.renderStats(data.stats);
                this.renderTable(this.filter(this.allData));
            } catch (e) {
                document.getElementById('ar-tbody').innerHTML =
                    `<tr><td colspan="10" class="text-center text-danger py-4">${e.message}</td></tr>`;
            }
        },

        async loadTransactions() {
            document.getElementById('tx-tbody').innerHTML = `<tr><td colspan="10" class="text-center py-5">
            <div class="spinner-border text-primary border-4" style="width: 2.5rem; height: 2.5rem;"></div></td></tr>`;
            try {
                const res = await fetch(`${BASE_URL}api/receivables/ar_transactions.php?filter=${this.activeTxFilter}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'Unknown error');

                this.txData = data.data;
                this.renderTxTable(this.txData);
            } catch (e) {
                document.getElementById('tx-tbody').innerHTML =
                    `<tr><td colspan="10" class="text-center text-danger py-4 fs-6 fw-600">${e.message}</td></tr>`;
            }
        },

        filter(rows) {
            const now = new Date();
            const oneWk = new Date(now.getTime() + 7 * 86400000);
            switch (this.activeFilter) {
                case 'due_soon': return rows.filter(r => r.oldest_due_date && new Date(r.oldest_due_date) <= oneWk);
                case 'over_limit': return rows.filter(r => r.over_limit);
                default: return rows;
            }
        },

        renderStats(s) {
            document.getElementById('stat-total').textContent = '₱' + this.m(s.total_balance);
            document.getElementById('stat-overdue').textContent = '₱' + this.m(s.total_overdue);
            document.getElementById('stat-buyers').textContent = s.buyer_count;
            document.getElementById('stat-overlimit').textContent = s.over_limit_count;
        },

        renderTable(rows) {
            const tbody = document.getElementById('ar-tbody');

            // Apply Search Filter
            if (this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                rows = rows.filter(r =>
                    (r.buyer_name || '').toLowerCase().includes(q) ||
                    (r.shop_name || '').toLowerCase().includes(q)
                );
            }

            if (!rows.length) {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted py-5 animate__animated animate__fadeIn">
                <i class="fa-solid fa-circle-check fa-3x mb-3 d-block text-success opacity-50"></i>
                <div class="fw-800 fs-4">All Clear!</div>
                <div class="text-secondary">No outstanding receivables found for this filter.</div></td></tr>`;
                return;
            }

            tbody.innerHTML = rows.map((r, idx) => {
                const overLimitBadge = r.over_limit
                    ? `<span class="badge bg-danger rounded-pill ms-2" style="font-size:10px; padding: 4px 8px;">OVER LIMIT</span>` : '';
                const tierBadge = {
                    retail: 'bg-light text-dark border',
                    wholesale: 'bg-info text-dark',
                    dealer: 'bg-primary text-white',
                }[r.price_tier] || 'bg-light';

                // Credit Utilization
                const creditUtilization = r.credit_limit !== null ? (() => {
                    const pct = Math.min(100, (r.total_balance / r.credit_limit) * 100).toFixed(1);
                    const barColor = pct >= 100 ? 'var(--danger-color)' : pct >= 80 ? 'var(--warning-color)' : 'var(--success-color)';
                    return `
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="small fw-700 text-secondary">${pct}% used</span>
                        <span class="small fw-800 text-primary">₱${this.m(r.available_credit ?? 0)} left</span>
                    </div>
                    <div class="credit-progress">
                        <div class="progress-fill" style="width:${pct}%; background:${barColor};"></div>
                    </div>
                    <div class="mt-1 small text-secondary opacity-75">Limit: ₱${this.m(r.credit_limit)}</div>`;
                })() : `<span class="badge bg-light text-secondary rounded-pill fw-500">Unrestricted Credit</span>`;

                return `
                <tr style="animation: ellaFadeIn 0.3s ease-out forwards ${idx * 0.05}s; opacity:0;">
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary-light text-primary rounded-circle me-3 d-flex align-items-center justify-content-center fw-800" style="width: 40px; height: 40px; font-size: 0.9rem;">
                                ${r.buyer_name.charAt(0)}
                            </div>
                            <div>
                                <div class="fw-800 text-dark" style="font-size: 1.05rem;">${this.esc(r.buyer_name)}</div>
                                <div class="small text-secondary fw-500 d-flex align-items-center">
                                    ${this.esc(r.shop_name || 'Individual Buyer')}
                                    <span class="badge ${tierBadge} ms-2 px-2 py-1 rounded-pill" style="font-size: 9px; letter-spacing: 0.5px;">${r.price_tier.toUpperCase()}</span>
                                    ${overLimitBadge}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge bg-white shadow-sm border text-dark px-3 py-2 rounded-3 fw-800" style="font-size: 0.9rem;">${r.open_invoices}</span></td>
                    <td class="text-end fw-600">₱${this.m(r.aging_current)}</td>
                    <td class="text-end">${this.agingBadge(r.aging_30, 'warn')}</td>
                    <td class="text-end">${this.agingBadge(r.aging_60, 'danger')}</td>
                    <td class="text-end">${this.agingBadge(r.aging_90, 'danger')}</td>
                    <td class="text-end">${this.agingBadge(r.aging_over90, 'severe')}</td>
                    <td class="text-end fw-800 text-primary" style="font-size: 1.1rem;">₱${this.m(r.total_balance)}</td>
                    <td class="ps-3" style="min-width:160px;">${creditUtilization}</td>
                    <td class="pe-3 text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="view.php?${r.buyer_id ? 'buyer_id=' + r.buyer_id : 'walkin_name=' + encodeURIComponent(r.walkin_name || r.buyer_name)}" 
                               class="btn btn-sm btn-primary rounded-3 px-2 py-1 fw-700 shadow-sm" style="font-size: 0.8rem;">
                                <i class="fa-solid fa-book-open me-1"></i>Ledger
                            </a>
                            <button class="btn-action js-credit-btn btn-sm"
                                data-buyer-id="${r.buyer_id}"
                                data-buyer-name="${encodeURIComponent(r.buyer_name || '')}"
                                data-limit="${r.credit_limit ?? ''}"
                                data-notes="${encodeURIComponent(r.credit_notes || '')}"
                                style="width:28px;height:28px;">
                                <i class="fa-solid fa-sliders"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            }).join('');

            // Wire up credit buttons
            tbody.querySelectorAll('.js-credit-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const limit = this.dataset.limit !== '' ? parseFloat(this.dataset.limit) : null;
                    AR.openCreditModal(
                        parseInt(this.dataset.buyerId),
                        decodeURIComponent(this.dataset.buyerName),
                        limit,
                        decodeURIComponent(this.dataset.notes)
                    );
                });
            });
        },

        renderTxTable(rows) {
            const tbody = document.getElementById('tx-tbody');

            // Apply Search Filter
            if (this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                rows = rows.filter(r =>
                    (r.customer_name || '').toLowerCase().includes(q) ||
                    (r.sale_ref || '').toLowerCase().includes(q)
                );
            }

            if (!rows.length) {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center text-muted py-5 animate__animated animate__fadeIn">
                <i class="fa-solid fa-receipt fa-3x mb-3 d-block opacity-25"></i>
                <div class="fw-800 fs-4 text-secondary">No matching transactions.</div></td></tr>`;
                return;
            }

            tbody.innerHTML = rows.map((t, idx) => {
                const isPaid = t.overall_status === 'paid';
                const isOverdue = t.overall_status === 'overdue';
                const statusBadge = isPaid ? 'bg-success text-white' : (isOverdue ? 'bg-danger text-white' : 'bg-light text-secondary border');
                const statusText = isPaid ? 'PAID' : (isOverdue ? 'OVERDUE' : 'PENDING');

                // Build mini term list for the Due Date column
                const termList = (t.terms || []).map((term, i) => {
                    const termPaid = term.payment_status === 'paid';
                    const termOverdue = !termPaid && term.due_date && new Date(term.due_date) < new Date(new Date().toDateString());
                    const termColor = termPaid ? 'bg-success' : (termOverdue ? 'bg-danger' : 'bg-warning text-dark');
                    const dd = new Date(term.due_date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
                    return `<div class="d-flex align-items-center gap-1 mb-1">
                        <span class="badge ${termColor}" style="font-size:9px;min-width:14px;">${i + 1}</span>
                        <span class="small fw-600" style="font-size:0.78rem;">${dd}</span>
                        <span class="text-muted" style="font-size:0.72rem;">₱${this.m(term.amount)}</span>
                    </div>`;
                }).join('');

                return `
                <tr style="animation: ellaFadeIn 0.3s ease-out forwards ${idx * 0.05}s; opacity:0;">
                    <td class="ps-4">
                        <div class="fw-700">${new Date(t.sale_date).toLocaleDateString()}</div>
                        <small class="text-secondary fw-500">${new Date(t.sale_date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</small>
                    </td>
                    <td>
                        <span class="badge bg-secondary-light text-secondary rounded-3 px-3 py-2 fw-800 border">${t.sale_ref}</span>
                        ${t.remarks ? `<div class="x-small text-secondary mt-1 opacity-75" style="font-size: 0.7rem; line-height: 1.1; max-width: 150px;"><i class="fa-solid fa-note-sticky me-1 small"></i>${this.esc(t.remarks)}</div>` : ''}
                    </td>
                    <td><div class="fw-800 text-dark">${this.esc(t.customer_name)}</div></td>
                    <td class="text-end fw-600">₱${this.m(t.grand_total)}</td>
                    <td class="text-end text-success fw-600">₱${this.m(t.total_paid)}</td>
                    <td class="text-end fw-800 ${isPaid ? 'text-success' : 'text-danger'}" style="font-size: 1rem;">₱${this.m(t.total_balance)}</td>
                    <td style="min-width:170px;">
                        <div>${termList}</div>
                        ${!isPaid ? `<button class="btn btn-xs btn-outline-warning mt-1 rounded-3 fw-700 js-manage-terms-btn"
                            data-sale-id="${t.sale_id}" data-sale-ref="${encodeURIComponent(t.sale_ref || '')}" data-grand-total="${t.grand_total}"
                            style="font-size:0.72rem;padding:2px 8px;">
                            <i class="fa-solid fa-pen-to-square me-1"></i>Manage Terms
                        </button>` : ''}
                    </td>
                    <td><span class="badge ${statusBadge} px-2 py-1 rounded-pill fw-700" style="font-size: 0.7rem;">${statusText} ${t.term_count > 1 ? `<span class="opacity-75">(${t.term_count} terms)</span>` : ''}</span></td>
                    <td class="tx-note-cell" data-payment-id="${t.payment_id}" style="min-width:130px;">
                        <div class="d-flex align-items-center gap-2">
                            <span class="tx-note-text flex-grow-1 small ${t.tx_note ? 'text-dark fw-500' : 'text-secondary fst-italic'}" style="${t.tx_note ? 'white-space:pre-wrap;word-break:break-word;max-width:130px;' : ''}">${t.tx_note ? this.esc(t.tx_note) : 'Add note…'}</span>
                            <button class="js-note-btn btn-action flex-shrink-0" data-id="${t.payment_id}" data-note="${encodeURIComponent(t.tx_note || '')}" style="width:26px;height:26px;border-radius:6px;" title="${t.tx_note ? 'Edit note' : 'Add note'}">
                                <i class="fa-solid fa-${t.tx_note ? 'pen-to-square' : 'plus'} x-small"></i>
                            </button>
                        </div>
                    </td>
                    <td class="pe-3 text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <button class="btn btn-sm btn-white shadow-sm border rounded-3" onclick="AR.viewHistory(${t.payment_id}, '${encodeURIComponent(t.sale_ref || '').replace(/'/g, '%27')}', ${t.total_paid})">
                                <i class="fa-solid fa-clock-rotate-left text-info"></i>
                            </button>
                            ${!isPaid ? `<a href="view.php?${t.buyer_id ? 'buyer_id=' + t.buyer_id : 'walkin_name=' + encodeURIComponent(t.walkin_name || t.customer_name)}" class="btn btn-sm btn-primary rounded-3"><i class="fa-solid fa-money-bill"></i></a>` : ''}
                        </div>
                    </td>
                </tr>`;
            }).join('');

            // Wire up note buttons
            tbody.querySelectorAll('.js-note-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    AR.openNoteModal(parseInt(this.dataset.id), decodeURIComponent(this.dataset.note || ''));
                });
            });

            // Wire up manage terms buttons
            tbody.querySelectorAll('.js-manage-terms-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    AR.openManageTerms(
                        parseInt(this.dataset.saleId),
                        decodeURIComponent(this.dataset.saleRef),
                        parseFloat(this.dataset.grandTotal)
                    );
                });
            });
        },

        async viewHistory(paymentId, refEncoded, totalPaid) {
            const ref = decodeURIComponent(refEncoded || '');
            document.getElementById('hist-ref').textContent = ref;
            document.getElementById('hist-total-paid').textContent = '₱' + this.m(totalPaid);
            const content = document.getElementById('hist-content');
            content.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary border-4" style="width: 2.5rem; height: 2.5rem;"></div><p class="mt-3 text-secondary">Loading settlement history...</p></div>';

            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('historyModal'));
            modal.show();

            try {
                const res = await fetch(`${BASE_URL}api/receivables/receivables.php?payment_id=${paymentId}`);
                const data = await res.json();
                if (data.status === 'success' && data.data.length > 0) {
                    content.innerHTML = data.data.map((h, i) => `
                        <div class="d-flex align-items-center p-3 mx-2 my-1 rounded-4 transition-all hover-bg-light animate__animated animate__fadeInUp" style="animation-delay: ${i * 0.1}s;">
                            <div class="bg-success-light text-success rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; min-width: 48px;">
                                <i class="fa-solid fa-check"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="fw-800 text-success fs-5">+₱${this.m(h.amount)}</div>
                                    <div class="text-end">
                                        <div class="fw-700 text-dark small">${new Date(h.paid_at).toLocaleDateString()}</div>
                                        <div class="text-secondary" style="font-size: 0.75rem;">${new Date(h.paid_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center mt-1">
                                    <span class="badge bg-white border text-secondary px-2 py-1 rounded-3 small fw-700 me-2">${h.payment_method.toUpperCase()}</span>
                                    <span class="text-secondary small fw-500"><i class="fa-regular fa-user me-1"></i>${h.collector_name || 'System Staff'}</span>
                                </div>
                                ${h.notes ? `<div class="mt-2 p-2 bg-light border-start border-4 border-success rounded-end small text-secondary">"${this.esc(h.notes)}"</div>` : ''}
                            </div>
                        </div>
                    `).join('');
                } else {
                    content.innerHTML = `
                        <div class="text-center py-5 opacity-40">
                            <i class="fa-solid fa-box-open fa-3x mb-3 text-secondary"></i>
                            <div class="fw-800 text-secondary fs-5">No payments recorded.</div>
                        </div>`;
                }
            } catch (e) {
                content.innerHTML = `<div class="p-4"><div class="alert alert-danger rounded-4">${e.message}</div></div>`;
            }
        },

        agingBadge(val, severity) {
            if (!val || val <= 0) return `<span class="aging-zero">—</span>`;
            return `<span class="aging-pill aging-${severity}">₱${this.m(val)}</span>`;
        },

        openNoteModal(paymentId, currentNote) {
            document.getElementById('note-payment-id').value = paymentId;
            document.getElementById('note-textarea').value = currentNote || '';
            document.getElementById('note-char-count').textContent = (currentNote || '').length;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('txNoteModal')).show();
            setTimeout(() => document.getElementById('note-textarea').focus(), 300);
        },

        async saveNote() {
            const paymentId = parseInt(document.getElementById('note-payment-id').value);
            const note = document.getElementById('note-textarea').value.trim();

            const btn = document.getElementById('note-save-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Saving…';

            try {
                const res = await fetch(`${BASE_URL}api/receivables/ar_note.php`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ payment_id: paymentId, note }),
                });
                const data = await res.json();

                if (data.success) {
                    EllaToast.success('Note saved successfully');
                    // Update cell inline
                    const cell = document.querySelector(`.tx-note-cell[data-payment-id="${paymentId}"]`);
                    if (cell) {
                        const span = cell.querySelector('.tx-note-text');
                        const icon = cell.querySelector('i');
                        const noteBtn = cell.querySelector('button');
                        if (note) {
                            span.textContent = note;
                            span.className = 'tx-note-text flex-grow-1 small text-dark fw-500';
                            span.style.cssText = 'white-space:pre-wrap;word-break:break-word;max-width:160px;';
                            icon.className = 'fa-solid fa-pen-to-square small';
                            noteBtn.title = 'Edit note';
                            noteBtn.dataset.note = note;
                        } else {
                            span.textContent = 'Add note…';
                            span.className = 'tx-note-text flex-grow-1 small text-secondary fst-italic';
                            span.style.cssText = '';
                            icon.className = 'fa-solid fa-plus small';
                            noteBtn.title = 'Add note';
                            noteBtn.dataset.note = '';
                        }
                    }
                    // Update in-memory data
                    const tx = this.txData.find(t => t.payment_id === paymentId);
                    if (tx) tx.tx_note = note || null;

                    bootstrap.Modal.getInstance(document.getElementById('txNoteModal'))?.hide();
                } else {
                    EllaToast.error(data.error || 'Failed to save note');
                }
            } catch (e) {
                EllaToast.error('Network error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Save Note';
            }
        },

        openManageTerms(saleId, saleRef, grandTotal) {
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('manageTermsModal'));
            document.getElementById('mt-sale-ref').textContent = saleRef;
            document.getElementById('mt-sale-id').value = saleId;
            document.getElementById('mt-grand-total').textContent = '₱' + this.m(grandTotal);
            document.getElementById('mt-terms-list').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-warning"></div></div>';

            // Store state for validation and auto-suggest
            this.currentGrandTotal = parseFloat(grandTotal);
            this.currentTerms = [];

            // Reset add-new-form
            document.getElementById('mt-new-date').value = '';
            document.getElementById('mt-new-amount').value = '';
            document.getElementById('mt-add-section').classList.add('d-none');
            modal.show();
            this._loadTerms(saleId);
        },

        async _loadTerms(saleId) {
            try {
                const res = await fetch(`${BASE_URL}api/receivables/ar_manage_terms.php?sale_id=${saleId}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.error);

                this.currentTerms = data.terms; // Store current terms state

                const today = new Date(); today.setHours(0, 0, 0, 0);
                const list = document.getElementById('mt-terms-list');
                list.innerHTML = data.terms.map((term, i) => {
                    const isPaid = term.payment_status === 'paid';
                    const isOverdue = !isPaid && new Date(term.due_date) < today;
                    const badgeCls = isPaid ? 'bg-success text-white' : (isOverdue ? 'bg-danger text-white' : 'bg-warning text-dark');
                    const status = isPaid ? 'Paid' : (isOverdue ? 'Overdue' : 'Pending');
                    return `
                    <div class="mt-term-row p-3 mb-2 rounded-4 border ${isPaid ? 'bg-light opacity-75' : 'bg-white'}" data-payment-id="${term.payment_id}">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-secondary" style="font-size:10px;">Term ${i + 1}</span>
                            <span class="badge ${badgeCls}" style="font-size:10px;">${status}</span>
                            <span class="ms-auto text-muted small">Balance: <strong class="text-${isPaid ? 'success' : 'danger'}">₱${this.m(term.balance)}</strong></span>
                        </div>
                        <div class="row g-2">
                            <div class="col-7">
                                <label class="form-label x-small text-secondary fw-700 text-uppercase mb-1">Due Date</label>
                                <input type="date" class="form-control form-control-sm rounded-3 mt-date-input" value="${term.due_date || ''}" ${isPaid ? 'disabled' : ''} data-pid="${term.payment_id}">
                            </div>
                            <div class="col-5">
                                <label class="form-label x-small text-secondary fw-700 text-uppercase mb-1">Amount (₱)</label>
                                <input type="number" class="form-control form-control-sm rounded-3 mt-amount-input" value="${term.amount.toFixed(2)}" step="0.01" min="0.01" ${isPaid ? 'disabled' : ''} data-pid="${term.payment_id}">
                            </div>
                        </div>
                    </div>`;
                }).join('');

                // No auto-balancing listeners added per user request
            } catch (e) {
                document.getElementById('mt-terms-list').innerHTML = `<div class="alert alert-danger">${e.message}</div>`;
            }
        },

        async saveAllTerms() {
            const saleId = parseInt(document.getElementById('mt-sale-id').value);
            const rows = Array.from(document.querySelectorAll('.mt-term-row'));
            const btn = document.getElementById('mt-save-all-btn');

            // 1. Gather EXISTING terms
            const updTerms = rows.map(r => ({
                payment_id: parseInt(r.dataset.paymentId),
                due_date: r.querySelector('.mt-date-input').value,
                amount: parseFloat(r.querySelector('.mt-amount-input').value) || 0
            }));

            // 2. Gather NEW term (if Add section is open and has an amount)
            const addSection = document.getElementById('mt-add-section');
            const newAmountStr = document.getElementById('mt-new-amount').value;
            const newAmount = parseFloat(newAmountStr) || 0;
            const newDate = document.getElementById('mt-new-date').value;

            const newTerms = [];
            if (!addSection.classList.contains('d-none') && newAmount > 0) {
                if (!newDate) { EllaToast.error('Please select a Due Date for your new term first.'); return; }
                newTerms.push({ amount: newAmount, due_date: newDate });
            }

            // 3. Client-side total check
            const uiTotal = this._calculateCurrentUITotal();
            if (Math.abs(uiTotal - this.currentGrandTotal) > 0.05) {
                const addSection = document.getElementById('mt-add-section');
                if (!addSection.classList.contains('d-none')) {
                    EllaToast.error(`Total (₱${this.m(uiTotal)}) must match Grand Total (₱${this.m(this.currentGrandTotal)}) before saving.`);
                } else {
                    EllaToast.error(`Term total (₱${this.m(uiTotal)}) does not match Grand Total (₱${this.m(this.currentGrandTotal)}).`);
                }
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Synchronizing…';

            try {
                const res = await fetch(`${BASE_URL}api/receivables/ar_manage_terms.php`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sale_id: saleId, terms: updTerms, new_terms: newTerms }),
                });
                const data = await res.json();
                if (data.success) {
                    EllaToast.success('Terms balanced and saved successfully.');
                    this.loadTransactions(); // Refresh main table
                    bootstrap.Modal.getInstance(document.getElementById('manageTermsModal'))?.hide();
                } else {
                    EllaToast.error(data.error || 'Sync failed.');
                }
            } catch (e) {
                EllaToast.error('Network error during sync.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Save All Changes';
            }
        },

        async addNewTerm() {
            const saleId = parseInt(document.getElementById('mt-sale-id').value);
            const newDate = document.getElementById('mt-new-date').value;
            const newAmount = parseFloat(document.getElementById('mt-new-amount').value);
            const btn = document.getElementById('mt-add-btn');

            if (!newDate) { EllaToast.error('Please select a due date for the new term.'); return; }
            if (!newAmount || newAmount <= 0) { EllaToast.error('Please enter a valid amount for the new term.'); return; }

            // Validation: Check if new total exceeds grand total
            const currentTotal = this._calculateCurrentUITotal();
            const predictedTotal = currentTotal + newAmount;

            if (predictedTotal > (this.currentGrandTotal + 0.01)) {
                EllaToast.error(`Adding ₱${this.m(newAmount)} would make the total (₱${this.m(predictedTotal)}) exceed the transaction amount (₱${this.m(this.currentGrandTotal)})`);
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Adding…';
            try {
                const res = await fetch(`${BASE_URL}api/receivables/ar_manage_terms.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sale_id: saleId, due_date: newDate, amount: newAmount }),
                });
                const data = await res.json();
                if (data.success) {
                    EllaToast.success('New term added successfully.');
                    document.getElementById('mt-new-date').value = '';
                    document.getElementById('mt-new-amount').value = '';
                    document.getElementById('mt-add-section').classList.add('d-none');
                    await this._loadTerms(saleId);
                    this.loadTransactions();
                    return true;
                } else {
                    EllaToast.error(data.error || 'Failed to add term.');
                    return false;
                }
            } catch (e) {
                EllaToast.error('Network error.');
                return false;
            }
            finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-plus me-1"></i>Add Term';
            }
        },

        _calculateCurrentUITotal() {
            const rows = Array.from(document.querySelectorAll('.mt-term-row'));
            const baseTotal = rows.reduce((s, r) => s + (parseFloat(r.querySelector('.mt-amount-input').value) || 0), 0);

            const addSection = document.getElementById('mt-add-section');
            const newAmount = !addSection.classList.contains('d-none') ? (parseFloat(document.getElementById('mt-new-amount').value) || 0) : 0;

            return baseTotal + newAmount;
        },

        _calculateRemainingBalance() {
            const currentTotal = this._calculateCurrentUITotal();
            return Math.max(0, this.currentGrandTotal - currentTotal);
        },

        showAddTermForm() {
            const section = document.getElementById('mt-add-section');
            const input = document.getElementById('mt-new-amount');
            const dateInput = document.getElementById('mt-new-date');

            section.classList.toggle('d-none');

            if (!section.classList.contains('d-none')) {
                const remaining = this._calculateRemainingBalance();
                if (remaining > 0) {
                    input.value = remaining.toFixed(2);
                } else {
                    input.value = '';
                }
                dateInput.focus();
            }
        },

        openCreditModal(buyerId, name, limit, notes) {
            document.getElementById('cl-buyer-id').value = buyerId;
            document.getElementById('cl-buyer-name').textContent = name;
            document.getElementById('cl-limit').value = limit !== null ? limit : '';
            document.getElementById('cl-notes').value = notes || '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('creditLimitModal')).show();
        },

        async saveCreditLimit() {
            const buyerId = parseInt(document.getElementById('cl-buyer-id').value);
            const limit = document.getElementById('cl-limit').value;
            const notes = document.getElementById('cl-notes').value;

            const btn = document.getElementById('cl-save-btn');
            btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Processing...';

            try {
                const res = await fetch(`${BASE_URL}api/receivables/ar_ledger.php`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ buyer_id: buyerId, credit_limit: limit !== '' ? parseFloat(limit) : '', credit_notes: notes }),
                });
                const data = await res.json();
                bootstrap.Modal.getInstance(document.getElementById('creditLimitModal'))?.hide();
                if (data.success) { EllaToast.success('Credit account updated successfully'); this.load(); }
                else AR.showToast('Error: ' + data.error, 'danger');
            } catch (e) { AR.showToast('Network error', 'danger'); }
            finally { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Apply Changes'; }
        },

        m: (v) => parseFloat(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
        esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; },

        showToast(msg, type) {
            if (typeof EllaToast !== 'undefined') {
                EllaToast[type === 'danger' ? 'error' : type](msg);
                return;
            }
            let c = document.getElementById('toast-container');
            if (!c) {
                c = document.createElement('div'); c.id = 'toast-container';
                c.className = 'position-fixed bottom-0 end-0 p-3'; c.style.zIndex = 1100; document.body.appendChild(c);
            }
            const el = document.createElement('div'); el.className = `toast align-items-center text-bg-${type} border-0 shadow`;
            el.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div>
            <button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
            c.appendChild(el); new bootstrap.Toast(el, { delay: 3500 }).show();
            el.addEventListener('hidden.bs.toast', () => el.remove());
        },
        handleSearch(val) {
            this.searchQuery = val.trim();
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                if (this.activeView === 'buyers') {
                    if (this.allData && this.allData.length > 0) {
                        this.renderTable(this.filter(this.allData));
                    }
                } else {
                    if (this.txData && this.txData.length > 0) {
                        this.renderTxTable(this.txData);
                    }
                }
            }, 250);
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        AR.load();

        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const view = this.dataset.view;
                AR.activeView = view;

                if (view === 'buyers') {
                    document.getElementById('buyer-view').classList.remove('d-none');
                    document.getElementById('buyer-filters').classList.remove('d-none');
                    document.getElementById('transaction-view').classList.add('d-none');
                    document.getElementById('transaction-filters').classList.add('d-none');
                    AR.loadBuyers();
                } else {
                    document.getElementById('buyer-view').classList.add('d-none');
                    document.getElementById('buyer-filters').classList.add('d-none');
                    document.getElementById('transaction-view').classList.remove('d-none');
                    document.getElementById('transaction-filters').classList.remove('d-none');
                    AR.loadTransactions();
                }
            });
        });

        document.querySelectorAll('.filter-tab').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                AR.activeFilter = this.dataset.filter;

                // If it's a "remote" filter, reload from API. If "local" (due_soon, over_limit), filter existing allData
                if (['due_soon', 'over_limit'].includes(AR.activeFilter)) {
                    AR.renderTable(AR.filter(AR.allData));
                } else {
                    AR.loadBuyers();
                }
            });
        });

        document.querySelectorAll('.tx-filter-tab').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.tx-filter-tab').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                AR.activeTxFilter = this.dataset.txFilter;
                AR.loadTransactions();
            });
        });

        const clSaveBtn = document.getElementById('cl-save-btn');
        if (clSaveBtn) clSaveBtn.addEventListener('click', () => AR.saveCreditLimit());

        const ddSaveBtn = document.getElementById('dd-save-btn');
        if (ddSaveBtn) ddSaveBtn.addEventListener('click', () => AR.saveDueDate());
    });
</script>

<!-- Manage Terms Modal -->
<div class="modal fade" id="manageTermsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header py-3 px-4 rounded-top-5"
                style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <h5 class="modal-title d-flex align-items-center fw-800 text-white">
                    <div class="bg-white rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                        style="width:38px;height:38px;color:#d97706;">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    Manage Pay Later Terms
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Sale Info Bar -->
                <div class="px-4 py-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <div class="x-small text-secondary fw-700 text-uppercase">Sale Reference</div>
                        <div class="fw-800 text-dark" id="mt-sale-ref">---</div>
                    </div>
                    <div class="text-end">
                        <div class="x-small text-secondary fw-700 text-uppercase">Grand Total</div>
                        <div class="fw-800 text-primary" id="mt-grand-total">₱0.00</div>
                    </div>
                </div>
                <input type="hidden" id="mt-sale-id">

                <!-- Terms List -->
                <div class="px-4 py-3">
                    <div class="small text-secondary fw-700 text-uppercase mb-2">Payment Terms</div>
                    <div id="mt-terms-list"></div>

                    <!-- Add New Term Section -->
                    <div id="mt-add-section"
                        class="d-none mt-3 p-3 bg-success bg-opacity-10 rounded-4 border border-success border-opacity-25">
                        <div class="small fw-800 text-success mb-2"><i class="fa-solid fa-plus-circle me-1"></i>New Term
                        </div>
                        <div class="row g-2">
                            <div class="col-7">
                                <label class="form-label x-small text-secondary fw-700 text-uppercase mb-1">Due
                                    Date</label>
                                <input type="date" id="mt-new-date" class="form-control form-control-sm rounded-3">
                            </div>
                            <div class="col-5">
                                <label class="form-label x-small text-secondary fw-700 text-uppercase mb-1">Amount
                                    (₱)</label>
                                <input type="number" id="mt-new-amount" class="form-control form-control-sm rounded-3"
                                    placeholder="0.00" step="0.01" min="0.01">
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <button id="mt-add-btn" class="btn btn-success rounded-3 fw-700 flex-grow-1"
                                style="font-size:0.85rem;" onclick="AR.addNewTerm()">
                                <i class="fa-solid fa-plus me-1"></i>Add Term
                            </button>
                            <button class="btn btn-outline-secondary rounded-3"
                                onclick="document.getElementById('mt-add-section').classList.add('d-none')">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Show/Hide Add Form Button -->
                    <button class="btn btn-outline-success rounded-4 fw-700 w-100 mt-3" style="font-size:0.85rem;"
                        onclick="AR.showAddTermForm()">
                        <i class="fa-solid fa-plus-circle me-1"></i>Add Another Term
                    </button>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4">
                <button type="button" class="btn btn-white border rounded-4 fw-600 px-4"
                    data-bs-dismiss="modal">Close</button>
                <button type="button" id="mt-save-all-btn" class="btn btn-primary rounded-4 fw-700 px-4"
                    onclick="AR.saveAllTerms()">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Save All Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Note Modal -->
<div class="modal fade" id="txNoteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header py-3 px-4 rounded-top-5"
                style="background: linear-gradient(135deg,#6366f1,#8b5cf6);">
                <h5 class="modal-title d-flex align-items-center fw-800 text-white">
                    <div class="bg-white rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                        style="width:38px;height:38px;color:#6366f1;">
                        <i class="fa-solid fa-note-sticky"></i>
                    </div>
                    Transaction Note
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="note-payment-id">
                <div class="mb-3">
                    <label class="small text-secondary fw-700 text-uppercase letter-spacing-1 mb-2 d-block"
                        for="note-textarea">Note</label>
                    <textarea id="note-textarea" class="form-control rounded-4 p-3" rows="5" maxlength="500"
                        placeholder="Type a note for this transaction…"
                        oninput="document.getElementById('note-char-count').textContent=this.value.length"></textarea>
                    <div class="d-flex justify-content-end mt-1">
                        <small class="text-secondary"><span id="note-char-count">0</span>\/500</small>
                    </div>
                </div>
                <div class="d-grid pt-1">
                    <button type="button" class="btn btn-primary btn-lg rounded-4 fw-800 py-3 shadow" id="note-save-btn"
                        onclick="AR.saveNote()">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Save Note
                    </button>
                    <button type="button" class="btn btn-white border rounded-4 fw-600 py-2 mt-2"
                        data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Credit Limit Modal -->
<div class="modal fade" id="creditLimitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header bg-dark text-white py-4 px-4 rounded-top-5">
                <h5 class="modal-title d-flex align-items-center fw-800">
                    <div class="bg-white text-dark rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                        style="width: 40px; height: 40px;">
                        <i class="fa-solid fa-sliders"></i>
                    </div>
                    Credit Account Settings
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="cl-buyer-id">
                <div class="mb-4">
                    <label class="small text-secondary fw-700 text-uppercase letter-spacing-1 mb-2 d-block">Buyer
                        Profile</label>
                    <div class="p-3 bg-light rounded-4 border">
                        <div class="fw-800 fs-5 text-dark" id="cl-buyer-name">---</div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="small text-secondary fw-700 text-uppercase letter-spacing-1 mb-2 d-block"
                        for="cl-limit">Credit Limit (PHP)</label>
                    <div class="input-group">
                        <span
                            class="input-group-text bg-white border-end-0 rounded-start-4 px-3 fw-800 text-primary">₱</span>
                        <input type="number" id="cl-limit"
                            class="form-control border-start-0 rounded-end-4 py-3 fw-800 fs-5"
                            placeholder="Enter limit amount">
                    </div>
                    <small class="text-secondary mt-2 d-block px-1">Leave empty or 0 for unlimited credit.</small>
                </div>

                <div class="mb-4">
                    <label class="small text-secondary fw-700 text-uppercase letter-spacing-1 mb-2 d-block"
                        for="cl-notes">Credit Policy Notes</label>
                    <textarea id="cl-notes" class="form-control rounded-4 p-3" rows="3"
                        placeholder="Any internal notes on this buyer's credit policy..."></textarea>
                </div>

                <div class="d-grid pt-2">
                    <button type="button" class="btn btn-primary btn-lg rounded-4 fw-800 py-3 shadow-lg"
                        id="cl-save-btn">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Apply Changes
                    </button>
                    <button type="button" class="btn btn-white border rounded-4 fw-600 py-3 mt-3"
                        data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>