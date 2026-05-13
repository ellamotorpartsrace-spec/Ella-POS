<?php
// views/receivables/view.php — Per-buyer AR Ledger Page
require_once '../../config/config.php';
require_once '../../includes/auth.php';

requirePermission('view_receivables');

$buyer_id = intval($_GET['buyer_id'] ?? 0);
$walkin_name = trim($_GET['walkin_name'] ?? '');

if (!$buyer_id && $walkin_name === '') {
    header('Location: index.php');
    exit;
}

$page_title = 'Buyer Ledger — Ella POS';
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
$isAdmin = ($_SESSION['role'] === 'admin' || hasPermission('view_profit'));
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
        --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
        --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        --glass-bg: rgba(255, 255, 255, 0.9);
        --glass-border: rgba(255, 255, 255, 0.4);
        --primary-color: #6366f1;
        --success-color: #10b981;
        --warning-color: #f59e0b;
        --danger-color: #ef4444;
        --text-main: #1e293b;
        --text-muted: #64748b;
    }

    body {
        background-color: #f8fafc;
        color: var(--text-main);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    .fw-800 {
        font-weight: 800;
    }

    .fw-700 {
        font-weight: 700;
    }

    .fw-600 {
        font-weight: 600;
    }

    .fw-500 {
        font-weight: 500;
    }

    .letter-spacing-1 {
        letter-spacing: -0.02em;
    }

    /* Glass Cards */
    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Stat Badges */
    .stat-stack {
        padding: 24px;
        position: relative;
        overflow: hidden;
    }

    .stat-stack::before {
        content: '';
        position: absolute;
        top: -20%;
        right: -10%;
        width: 120px;
        height: 120px;
        background: var(--primary-color);
        opacity: 0.05;
        border-radius: 50%;
    }

    .stat-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        margin-bottom: 8px;
    }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        margin-bottom: 4px;
    }

    /* Ledger Table */
    .ledger-table thead th {
        background: #f1f5f9;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        padding: 16px;
        border: none;
    }

    .ledger-table tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid #f1f5f9;
    }

    .ledger-table tbody tr:hover {
        background-color: rgba(99, 102, 241, 0.03) !important;
        transform: scale(1.002);
    }

    /* Quick Pay Drawer */
    .quick-pay-panel {
        position: fixed;
        right: 0;
        top: 0;
        width: 420px;
        height: 100vh;
        background: #fff;
        box-shadow: -20px 0 50px rgba(0, 0, 0, 0.1);
        z-index: 1060;
        transform: translateX(100%);
        transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
    }

    .quick-pay-panel.open {
        transform: translateX(0);
    }

    .panel-header {
        background: var(--primary-gradient);
        padding: 32px 24px;
        color: white;
    }

    .panel-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(4px);
        z-index: 1059;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .panel-overlay.open {
        opacity: 1;
        visibility: visible;
    }

    /* Status Pills */
    .status-pill {
        padding: 6px 14px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    .pill-paid {
        background: #dcfce7;
        color: #166534;
        box-shadow: 0 0 10px rgba(22, 101, 52, 0.1);
    }

    .pill-partial {
        background: #fef9c3;
        color: #854d0e;
    }

    .pill-pending {
        background: #f1f5f9;
        color: #475569;
    }

    .pill-overdue {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid rgba(153, 27, 27, 0.1);
    }

    /* Credit Bar */
    .credit-progress {
        height: 8px;
        background: #f1f5f9;
        border-radius: 10px;
        overflow: hidden;
        margin: 12px 0;
    }

    .progress-fill {
        height: 100%;
        border-radius: 10px;
        transition: width 1s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    /* Animations */
    @keyframes ellaFadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fade {
        animation: ellaFadeIn 0.4s ease-out forwards;
    }

    /* Modal Styling */
    .modal-content {
        border-radius: 32px;
        overflow: hidden;
    }

    .modal-header {
        border-bottom: none;
        padding: 32px 32px 16px;
    }

    .modal-body {
        padding: 16px 32px 32px;
    }

    .form-control,
    .form-select {
        border-radius: 14px;
        padding: 12px 16px;
        background: #f8fafc;
        border: 2px solid #f1f5f9;
        transition: all 0.2s ease;
    }

    .form-control:focus {
        background: #fff;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    }
</style>
<!-- Header -->
<div class="row align-items-center mb-5 animate-fade">
    <div class="col-md-7">
        <div class="d-flex align-items-center gap-3">
            <a href="index.php"
                class="btn btn-white shadow-sm rounded-circle d-flex align-items-center justify-content-center transition-all hover-scale"
                style="width: 48px; height: 48px; min-width: 48px; border: 1px solid #e2e8f0;">
                <i class="fa-solid fa-arrow-left text-primary"></i>
            </a>
            <div>
                <h1 class="display-6 fw-800 mb-0 letter-spacing-1" id="ldr-buyer-name" style="font-size: 2rem;">Buyer
                    Ledger</h1>
                <p class="text-secondary mb-0 fw-500" id="ldr-buyer-sub">Loading profile...</p>
            </div>
        </div>
    </div>
    <div class="col-md-5 text-md-end mt-4 mt-md-0">
        <div class="d-flex gap-2 justify-content-md-end">
            <a id="btn-export-csv" class="btn btn-white shadow-sm px-4 py-2 rounded-4 fw-600 border transition-all">
                <i class="fa-solid fa-file-csv me-2 text-success"></i>Export CSV
            </a>
            <?php if ($isAdmin): ?>
                <button class="btn btn-white shadow-sm px-4 py-2 rounded-4 fw-600 border transition-all"
                    id="btn-edit-limit">
                    <i class="fa-solid fa-sliders me-2 text-primary"></i>Credit Settings
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Profile + Stats Grid -->
<div class="row g-4 mb-5">
    <div class="col-lg-4">
        <div class="glass-card h-100 p-4 animate-fade" style="animation-delay: 0.1s;">
            <div class="d-flex align-items-center mb-4">
                <div class="bg-primary-light text-primary rounded-circle me-3 d-flex align-items-center justify-content-center fw-800 fs-4"
                    style="width: 60px; height: 60px;" id="ldr-initial">
                    ?
                </div>
                <div>
                    <div class="text-muted small fw-700 text-uppercase letter-spacing-1">Buyer Profile</div>
                    <div class="fw-800 fs-4 text-dark" id="ldr-name">—</div>
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-secondary fw-600 small">Shop / Entity</span>
                    <span class="fw-700 text-dark" id="ldr-shop">—</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-secondary fw-600 small">Account Tier</span>
                    <div id="ldr-tier"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-secondary fw-600 small">Contact No.</span>
                    <span class="fw-700 text-dark" id="ldr-contact">—</span>
                </div>
            </div>

            <div class="p-4 rounded-4 bg-light border border-white" id="ldr-credit-block">
                <div class="text-muted small fw-800 text-uppercase letter-spacing-1 mb-3">Credit Utilization</div>
                <div id="ldr-credit-info">
                    <div class="placeholder-glow">
                        <span class="placeholder col-12 rounded-pill mb-2"></span>
                        <span class="placeholder col-8 rounded-pill"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="row g-4 h-100">
            <div class="col-md-6">
                <div class="glass-card stat-stack h-100 animate-fade" style="animation-delay: 0.2s;">
                    <div class="stat-label">Total Billed Amt</div>
                    <div class="stat-value text-dark" id="ldr-total-due">₱0.00</div>
                    <div class="text-secondary small fw-500">Gross receivables since inception</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="glass-card stat-stack h-100 animate-fade" style="animation-delay: 0.3s;">
                    <div class="stat-label text-success">Total Recovered</div>
                    <div class="stat-value text-success" id="ldr-total-paid">₱0.00</div>
                    <div class="text-secondary small fw-500">Total payments successfully recorded</div>
                </div>
            </div>
            <div class="col-12">
                <div class="glass-card bg-primary text-white p-4 h-100 animate-fade border-0"
                    style="animation-delay: 0.4s; background: var(--primary-gradient) !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-white opacity-75 small fw-700 text-uppercase mb-1">Current Outstanding
                                Balance</div>
                            <div class="display-5 fw-800 mb-0 letter-spacing-1" id="ldr-total-balance">₱0.00</div>
                        </div>
                        <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-lg"
                            style="width: 80px; height: 80px;">
                            <i class="fa-solid fa-scale-balanced fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ledger View -->
<div class="glass-card border-0 overflow-hidden mb-5 animate-fade" style="animation-delay: 0.5s;">
    <div class="p-4 border-bottom bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-800 text-dark">Transaction Ledger</h5>
        <div class="text-secondary small fw-600"><i class="fa-solid fa-circle-info me-2 text-primary"></i>Showing all
            pending and settled invoices</div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 ledger-table">
            <thead>
                <tr>
                    <th class="ps-4">Sale Date</th>
                    <th>Invoice / Ref</th>
                    <th>Due Date</th>
                    <th>Aging Status</th>
                    <th class="text-end">Billed</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                    <th>Status</th>
                    <th>Comments</th>
                    <th class="pe-4 text-end">Action</th>
                </tr>
            </thead>
            <tbody id="ledger-tbody">
                <tr>
                    <td colspan="9" class="text-center py-5">
                        <div class="spinner-border text-primary border-4" style="width: 3rem; height: 3rem;"></div>
                        <p class="mt-3 text-secondary fw-600">Retrieving ledger entries...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Quick Pay Drawer ─────────────────────────────────────────── -->
<div class="panel-overlay" id="panel-overlay" onclick="LDR.closePayPanel()"></div>
<div class="quick-pay-panel" id="quick-pay-panel">
    <div class="panel-header">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0 fw-800"><i class="fa-solid fa-money-bill-transfer me-3"></i>Post Payment</h4>
            <button class="btn btn-link text-white p-0" onclick="LDR.closePayPanel()">
                <i class="fa-solid fa-xmark fs-4"></i>
            </button>
        </div>
        <div class="p-3 bg-white bg-opacity-10 rounded-4 border border-white border-opacity-20 backdrop-blur">
            <div class="text-white opacity-75 small fw-700 text-uppercase mb-1">Invoice Reference</div>
            <div class="fw-800 fs-4" id="pp-ref">—</div>
            <div class="d-flex justify-content-between mt-3 align-items-center">
                <span class="text-white opacity-75 fw-600">Pending Amount</span>
                <span class="badge bg-danger rounded-pill px-3 py-2 fw-800 fs-6 shadow-sm" id="pp-balance">₱0.00</span>
            </div>
        </div>
    </div>

    <div class="p-4 flex-grow-1 overflow-auto">
        <input type="hidden" id="pp-payment-id">
        <div class="mb-4">
            <label class="form-label small fw-800 text-uppercase letter-spacing-1 text-secondary mb-2">Payment
                Amount</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 rounded-start-4 px-3 fw-800 text-primary">₱</span>
                <input type="number" step="0.01" id="pp-amount" class="form-control border-start-0 py-3 fw-800 fs-5"
                    placeholder="0.00">
            </div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-6">
                <label
                    class="form-label small fw-800 text-uppercase letter-spacing-1 text-secondary mb-2">Method</label>
                <select id="pp-method" class="form-select py-3 fw-700">
                    <option value="cash">💵 Cash</option>
                    <option value="gcash">📱 GCash</option>
                    <option value="bank">🏦 Bank</option>
                    <option value="check">📄 Check</option>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label small fw-800 text-uppercase letter-spacing-1 text-secondary mb-2">Ref
                    No.</label>
                <input type="text" id="pp-ref-no" class="form-control py-3 fw-700" placeholder="Optional">
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label small fw-800 text-uppercase letter-spacing-1 text-secondary mb-2">Notes /
                Memo</label>
            <textarea id="pp-notes" class="form-control" rows="2" placeholder="Internal payment remarks..."></textarea>
        </div>

        <button class="btn btn-primary btn-lg w-100 rounded-4 fw-800 py-3 shadow-lg transition-all" id="pp-submit-btn"
            onclick="LDR.submitPayment()">
            <i class="fa-solid fa-check-double me-2"></i>Post Settlement
        </button>

        <hr class="my-5 opacity-10">

        <div class="text-center mb-4">
            <span
                class="bg-light px-3 py-1 rounded-pill small fw-800 text-muted text-uppercase letter-spacing-1">Payment
                Timeline</span>
        </div>
        <div id="pp-history" class="payment-timeline">
            <!-- Timeline items -->
        </div>
    </div>
</div>

<!-- Credit Limit Modal -->
<div class="modal fade" id="creditLimitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header bg-dark text-white py-4 px-4">
                <h5 class="modal-title d-flex align-items-center fw-800">
                    <div class="bg-white text-dark rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                        style="width: 40px; height: 40px;">
                        <i class="fa-solid fa-sliders"></i>
                    </div>
                    Account Credit Policy
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="form-label small fw-800 text-uppercase letter-spacing-1 text-secondary mb-2">Maximum
                        Credit Limit (₱)</label>
                    <div class="input-group">
                        <span
                            class="input-group-text bg-white border-end-0 rounded-start-4 px-3 fw-800 text-primary">₱</span>
                        <input type="number" step="0.01" id="cl-limit"
                            class="form-control border-start-0 py-3 fw-800 fs-5" placeholder="Leave empty for no limit">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-800 text-uppercase letter-spacing-1 text-secondary mb-2">Credit
                        Policy Notes</label>
                    <textarea id="cl-notes" class="form-control rounded-4 p-3" rows="3"
                        placeholder="e.g. Terms: Net 30, Payment via Bank Transfer only..."></textarea>
                </div>
                <div class="d-grid gap-3 pt-2">
                    <button type="button" class="btn btn-primary btn-lg rounded-4 fw-800 py-3 shadow-lg"
                        id="cl-save-btn">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Save Policy Changes
                    </button>
                    <button type="button" class="btn btn-white border rounded-4 fw-600 py-3"
                        data-bs-dismiss="modal">Discard</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const BASE_URL = "<?= BASE_URL ?>";
    const BUYER_ID = <?= $buyer_id ?>;
    const WALKIN_NAME = <?= json_encode($walkin_name) ?>;
    const API_QUERY = BUYER_ID ? `buyer_id=${BUYER_ID}` : `walkin_name=${encodeURIComponent(WALKIN_NAME)}`;

    const LDR = {
        buyer: null,

        async load() {
            try {
                const res = await fetch(`${BASE_URL}api/receivables/ar_ledger.php?${API_QUERY}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.error);

                this.buyer = data.buyer;
                this.renderProfile(data.buyer, data.totals);
                this.renderTable(data.invoices);

                // Wire export link
                document.getElementById('btn-export-csv').href =
                    `${BASE_URL}api/receivables/ar_ledger.php?${API_QUERY}&export=1`;
            } catch (e) {
                document.getElementById('ledger-tbody').innerHTML =
                    `<tr><td colspan="9" class="text-center text-danger py-4">${e.message}</td></tr>`;
            }
        },

        renderProfile(b, totals) {
            const tierStyles = {
                retail: 'bg-light text-dark border',
                wholesale: 'bg-info text-dark',
                dealer: 'bg-primary text-white shadow-sm'
            };

            // Header & Sub
            document.getElementById('ldr-buyer-name').textContent = b.buyer_name;
            document.getElementById('ldr-buyer-sub').textContent = b.shop_name || 'Accounts Receivable Profile';

            // Profile Card
            document.getElementById('ldr-initial').textContent = b.buyer_name.charAt(0);
            document.getElementById('ldr-name').textContent = b.buyer_name;
            document.getElementById('ldr-shop').textContent = b.shop_name || 'Individual';
            document.getElementById('ldr-contact').textContent = b.contact_number || 'None';
            document.getElementById('ldr-tier').innerHTML =
                `<span class="status-pill ${tierStyles[b.price_tier] || 'bg-light text-secondary'}">${b.price_tier.toUpperCase()}</span>`;

            // Stats
            document.getElementById('ldr-total-due').textContent = '₱' + this.m(totals.amount_due);
            document.getElementById('ldr-total-paid').textContent = '₱' + this.m(totals.paid);
            document.getElementById('ldr-total-balance').textContent = '₱' + this.m(totals.balance);

            // Credit limit
            const creditEl = document.getElementById('ldr-credit-info');
            if (b.credit_limit !== null) {
                const pct = Math.min(100, (totals.balance / b.credit_limit) * 100).toFixed(1);
                const barColor = pct >= 100 ? 'var(--danger-color)' : pct >= 80 ? 'var(--warning-color)' : 'var(--success-color)';
                creditEl.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-700 text-secondary">₱${this.m(totals.balance)} used</span>
                    <span class="small fw-800 text-primary">₱${this.m(b.credit_limit)} limit</span>
                </div>
                <div class="credit-progress">
                    <div class="progress-fill" style="width:${pct}%; background:${barColor};"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <span class="small text-secondary fw-500">Available Credit</span>
                    <span class="small fw-800 text-success">₱${this.m(b.available_credit ?? 0)}</span>
                </div>
                ${b.credit_notes ? `<div class="mt-3 p-2 bg-white bg-opacity-50 border rounded-3 small text-secondary fst-italic fw-500" style="font-size: 0.8rem;">"${this.esc(b.credit_notes)}"</div>` : ''}
                ${b.over_limit ? '<div class="badge bg-danger w-100 mt-2 py-2 rounded-3 fw-800 shadow-sm">OVER CREDIT LIMIT</div>' : ''}`;
            } else {
                creditEl.innerHTML = `
                <div class="text-center py-2">
                    <span class="badge bg-white border text-secondary px-3 py-2 rounded-pill fw-600">Unrestricted Credit Account</span>
                </div>`;
            }

            // Pre-fill credit modal
            const clLimit = document.getElementById('cl-limit');
            const clNotes = document.getElementById('cl-notes');
            if (clLimit) clLimit.value = b.credit_limit ?? '';
            if (clNotes) clNotes.value = b.credit_notes || '';
        },

        renderTable(invoices) {
            const tbody = document.getElementById('ledger-tbody');
            if (!invoices.length) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center py-5">
                <i class="fa-solid fa-circle-check fa-4x mb-3 d-block text-success opacity-25"></i>
                <div class="fw-800 fs-4 text-secondary">No Ledger Activity</div>
                <p class="text-muted">This account currently has no associated invoices.</p></td></tr>`;
                return;
            }

            tbody.innerHTML = invoices.map((inv, idx) => {
                const isPaid = inv.payment_status === 'paid';
                const isPartial = inv.payment_status === 'partial';
                const isOverdue = !isPaid && inv.days_overdue > 0;

                let statusLabel, statusClass;
                if (isPaid) { statusLabel = 'Settled'; statusClass = 'pill-paid'; }
                else if (isOverdue) { statusLabel = 'Overdue'; statusClass = 'pill-overdue'; }
                else if (isPartial) { statusLabel = 'Partial'; statusClass = 'pill-partial'; }
                else { statusLabel = 'Pending'; statusClass = 'pill-pending'; }

                const overdueText = isPaid ? '<span class="text-secondary opacity-50">—</span>'
                    : inv.days_overdue === null ? '<span class="text-muted small">No due date</span>'
                        : inv.days_overdue === 0 ? '<span class="text-success fw-700">Not due</span>'
                            : `<span class="text-danger fw-800 animate__animated animate__pulse animate__infinite" style="display:inline-block;">${inv.days_overdue} days</span>`;

                const actionBtn = isPaid
                    ? `<div class="bg-light text-secondary border px-3 py-2 rounded-pill fw-700 d-flex align-items-center justify-content-center" style="font-size: 0.75rem; min-height: 38px;"><i class="fa-solid fa-check me-2"></i>Paid</div>`
                    : `<button class="btn btn-primary btn-sm px-3 py-2 rounded-4 fw-800 shadow-sm transition-all d-flex align-items-center justify-content-center" onclick="LDR.openPayPanel(${inv.payment_id}, '${encodeURIComponent(inv.sale_ref || '').replace(/'/g, '%27')}', ${inv.balance})" style="min-height: 38px;">
                            <i class="fa-solid fa-money-bill-wave me-2"></i>Pay
                       </button>`;

                const commentsBtn = `
                    <button class="btn btn-white border shadow-sm rounded-pill px-3 py-1 position-relative d-flex align-items-center gap-2" 
                            onclick="LDR.openNoteModal(${inv.payment_id}, '${encodeURIComponent(inv.notes || '').replace(/'/g, '%27')}', '${encodeURIComponent(inv.sale_ref || '').replace(/'/g, '%27')}')" 
                            style="font-size: 0.75rem; min-height: 32px;" title="View Comments & History">
                        <i class="fa-solid fa-comments text-primary small"></i>
                        <span class="fw-700">View Notes</span>
                        ${(inv.notes || inv.latest_settlement_note) ? '<span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" style="margin-top:2px; margin-left:-2px;"></span>' : ''}
                    </button>`;

                return `
                <tr style="animation: ellaFadeIn 0.3s ease-out forwards ${idx * 0.05 + 0.5}s; opacity:0;">
                    <td class="ps-4">
                        <div class="fw-700 d-flex align-items-center">
                            <i class="fa-solid fa-calendar-day text-secondary opacity-50 me-2 small"></i>
                            ${this.date(inv.sale_date)}
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-secondary-light text-secondary rounded-3 px-3 py-2 fw-800 border">${inv.sale_ref}</span>
                        ${inv.remarks ? `<div class="x-small text-secondary mt-1 opacity-75" style="font-size: 0.7rem; line-height: 1.1; max-width: 200px;"><i class="fa-solid fa-note-sticky me-1 small"></i>${this.esc(inv.remarks)}</div>` : ''}
                    </td>
                    <td><div class="small fw-600 text-secondary">${inv.due_date ? this.date(inv.due_date) : '—'}</div></td>
                    <td>${overdueText}</td>
                    <td class="text-end fw-600">₱${this.m(inv.amount_due)}</td>
                    <td class="text-end text-success fw-600">₱${this.m(inv.paid_amount)}</td>
                    <td class="text-end fw-800 ${isPaid ? 'text-success' : 'text-danger'}" style="font-size: 1.05rem;">₱${this.m(inv.balance)}</td>
                    <td><span class="status-pill ${statusClass}">${statusLabel.toUpperCase()}</span></td>
                    <td>${commentsBtn}</td>
                    <td class="pe-4 text-end">${actionBtn}</td>
                </tr>`;
            }).join('');
        },

        openPayPanel(paymentId, refEncoded, balance) {
            const ref = decodeURIComponent(refEncoded || '');
            document.getElementById('pp-payment-id').value = paymentId;
            document.getElementById('pp-ref').textContent = ref;
            document.getElementById('pp-balance').textContent = '₱' + this.m(balance);
            document.getElementById('pp-amount').value = balance.toFixed(2);
            document.getElementById('pp-ref-no').value = '';
            document.getElementById('pp-notes').value = '';
            document.getElementById('pp-method').value = 'cash';
            this.loadPayHistory(paymentId);
            document.getElementById('quick-pay-panel').classList.add('open');
            document.getElementById('panel-overlay').classList.add('open');
        },

        closePayPanel() {
            document.getElementById('quick-pay-panel').classList.remove('open');
            document.getElementById('panel-overlay').classList.remove('open');
        },

        async loadPayHistory(paymentId) {
            const el = document.getElementById('pp-history');
            el.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary border-3" style="width: 1.5rem; height: 1.5rem;"></div></div>';
            try {
                const res = await fetch(`${BASE_URL}api/receivables/receivables.php?payment_id=${paymentId}`);
                const data = await res.json();
                if (data.status === 'success' && data.data.length > 0) {
                    el.innerHTML = data.data.map((h, i) => `
                        <div class="d-flex align-items-center p-3 mb-2 rounded-4 bg-light border-start border-4 border-success animate__animated animate__fadeInRight" style="animation-delay: ${i * 0.1}s;">
                            <div class="bg-success text-white rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; min-width: 32px;">
                                <i class="fa-solid fa-check small"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="fw-800 text-success">₱${parseFloat(h.amount).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</div>
                                    <div class="text-secondary" style="font-size: 0.7rem;">${new Date(h.paid_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</div>
                                </div>
                                <div class="text-muted" style="font-size: 11px; font-weight: 500;">
                                    ${h.payment_method?.toUpperCase()} · <i class="fa-regular fa-user me-1 ms-1"></i>${h.collector_name || 'Staff'}
                                </div>
                                ${h.notes ? `<div class="mt-1 small fst-italic text-secondary opacity-75">"${this.esc(h.notes)}"</div>` : ''}
                            </div>
                        </div>`).join('');
                } else {
                    el.innerHTML = `
                        <div class="text-center py-4 border border-dashed rounded-4 opacity-50">
                            <i class="fa-solid fa-receipt fa-2x mb-2 text-secondary"></i>
                            <div class="small fw-600">No payment history recorded</div>
                        </div>`;
                }
            } catch (e) { el.innerHTML = '<div class="alert alert-danger rounded-4 px-3 py-2 small">Timeline error.</div>'; }
        },

        async submitPayment() {
            const paymentId = parseInt(document.getElementById('pp-payment-id').value);
            const amount = parseFloat(document.getElementById('pp-amount').value);
            const method = document.getElementById('pp-method').value;
            const refNo = document.getElementById('pp-ref-no').value;
            const notes = document.getElementById('pp-notes').value;

            if (!amount || amount <= 0) { this.toast('Please enter a valid amount', 'warning'); return; }

            const btn = document.getElementById('pp-submit-btn');
            btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Processing...';

            try {
                const res = await fetch(`${BASE_URL}api/receivables/receivables.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ payment_id: paymentId, amount, payment_method: method, reference_no: refNo, notes }),
                });
                const data = await res.json();
                if (data.status === 'success') {
                    if (typeof EllaToast !== 'undefined') EllaToast.success('Payment successfully recorded!');
                    else this.toast('Payment recorded!', 'success');
                    this.closePayPanel();
                    this.load();
                } else {
                    this.toast('Error: ' + (data.message || 'Unknown'), 'danger');
                }
            } catch (e) { this.toast('Network error', 'danger'); }
            finally { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-check-double me-2"></i>Post Settlement'; }
        },

        openNoteModal(paymentId, encodedNote, encodedSaleRef) {
            const currentNote = decodeURIComponent(encodedNote || '');
            const saleRef = decodeURIComponent(encodedSaleRef || '');
            document.getElementById('note-payment-id').value = paymentId;
            document.getElementById('note-textarea').value = currentNote || '';
            document.getElementById('note-char-count').textContent = (currentNote || '').length;
            document.getElementById('note-sale-ref-display').textContent = saleRef || '---';

            // Clear and load history
            const list = document.getElementById('note-history-list');
            list.innerHTML = '<div class="text-center py-4 opacity-50"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading history...</div>';

            bootstrap.Modal.getOrCreateInstance(document.getElementById('txNoteModal')).show();

            // Fetch history specifically for this modal
            fetch(`${BASE_URL}api/receivables/receivables.php?payment_id=${paymentId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' && data.data.length > 0) {
                        list.innerHTML = data.data.map(h => `
                            <div class="mb-3 border-start border-2 ps-3 py-1" style="border-color: #e2e8f0 !important;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-800 text-dark">₱${this.m(h.amount)}</span>
                                    <span class="x-small text-muted">${new Date(h.paid_at).toLocaleDateString()}</span>
                                </div>
                                <div class="x-small text-secondary fw-500 mb-1">
                                    <i class="fa-solid fa-user me-1"></i>${this.esc(h.collector_name || 'System')} 
                                    <span class="mx-1">•</span> 
                                    <i class="fa-solid fa-credit-card me-1"></i>${h.payment_method}
                                </div>
                                ${h.notes ? `<div class="p-2 bg-light rounded-3 small fst-italic mt-1 border">"${this.esc(h.notes)}"</div>` : ''}
                            </div>
                        `).join('');
                    } else {
                        list.innerHTML = '<div class="text-center py-4 text-muted small fst-italic">No payment history found.</div>';
                    }
                }).catch(() => {
                    list.innerHTML = '<div class="text-center py-4 text-danger small">Error loading history.</div>';
                });

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
                    if (typeof EllaToast !== 'undefined') EllaToast.success('Note saved successfully');
                    else this.toast('Note saved successfully', 'success');

                    bootstrap.Modal.getInstance(document.getElementById('txNoteModal'))?.hide();
                    this.load(); // Reload table to show updated note
                } else {
                    if (typeof EllaToast !== 'undefined') EllaToast.error(data.error || 'Failed to save note');
                    else this.toast(data.error || 'Failed to save note', 'danger');
                }
            } catch (e) {
                this.toast('Network error', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Save Note';
            }
        },

        async saveCreditLimit() {
            const limitStr = document.getElementById('cl-limit').value;
            const notes = document.getElementById('cl-notes').value;
            const btn = document.getElementById('cl-save-btn');
            btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Applying...';
            try {
                const res = await fetch(`${BASE_URL}api/receivables/ar_ledger.php`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ buyer_id: BUYER_ID, credit_limit: limitStr !== '' ? parseFloat(limitStr) : '', credit_notes: notes }),
                });
                const data = await res.json();
                bootstrap.Modal.getInstance(document.getElementById('creditLimitModal'))?.hide();
                if (data.success) {
                    if (typeof EllaToast !== 'undefined') EllaToast.success('Credit policy updated!');
                    else this.toast('Credit limit saved', 'success');
                    this.load();
                }
                else this.toast('Error: ' + data.error, 'danger');
            } catch (e) { this.toast('Network error', 'danger'); }
            finally { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Save Policy Changes'; }
        },

        date(s) {
            const d = new Date(s);
            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        },
        m: (v) => parseFloat(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
        esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; },

        toast(msg, type = 'info') {
            if (typeof EllaToast !== 'undefined') {
                EllaToast[type === 'danger' ? 'error' : type](msg);
                return;
            }
            let c = document.getElementById('_tc');
            if (!c) {
                c = document.createElement('div'); c.id = '_tc';
                c.className = 'position-fixed bottom-0 end-0 p-3'; c.style.zIndex = 1100; document.body.appendChild(c);
            }
            const el = document.createElement('div'); el.className = `toast align-items-center text-bg-${type} border-0 shadow`;
            el.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div>
            <button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
            c.appendChild(el); new bootstrap.Toast(el, { delay: 3500 }).show();
            el.addEventListener('hidden.bs.toast', () => el.remove());
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        LDR.load();
        document.getElementById('btn-edit-limit')?.addEventListener('click', () =>
            bootstrap.Modal.getOrCreateInstance(document.getElementById('creditLimitModal')).show());
        const clSaveBtn = document.getElementById('cl-save-btn');
        if (clSaveBtn) clSaveBtn.addEventListener('click', () => LDR.saveCreditLimit());
    });
</script>

<!-- Invoice Comments Modal -->
<div class="modal fade" id="txNoteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-2xl">
            <div class="modal-header py-3 px-4 rounded-top-5"
                style="background: linear-gradient(135deg,#6366f1,#8b5cf6);">
                <h5 class="modal-title d-flex align-items-center fw-800 text-white">
                    <div class="bg-white rounded-circle p-2 me-3 d-flex align-items-center justify-content-center"
                        style="width:38px;height:38px;color:#6366f1;">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    Invoice Comments
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Sale Info Bar (Small) -->
                <div class="px-4 py-2 bg-light border-bottom d-flex justify-content-between align-items-center">
                    <div class="small fw-800 text-secondary" id="note-sale-ref-display">---</div>
                    <div class="badge bg-white border text-primary rounded-pill px-3 py-1 fw-700 shadow-sm"
                        style="font-size:0.7rem;">Ledger History</div>
                </div>

                <div class="p-4">
                    <input type="hidden" id="note-payment-id">
                    <div class="mb-4">
                        <label class="small text-secondary fw-800 text-uppercase letter-spacing-1 mb-2 d-block"
                            for="note-textarea">Main Transaction Note</label>
                        <textarea id="note-textarea" class="form-control rounded-4 p-3 bg-light border-dashed" rows="3"
                            maxlength="500" placeholder="Type a persistent note for this invoice…"
                            oninput="document.getElementById('note-char-count').textContent=this.value.length"></textarea>
                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-secondary">Keep notes persistent for the term.</small>
                            <small class="text-secondary"><span id="note-char-count">0</span>/500</small>
                        </div>
                        <div class="d-grid mt-3">
                            <button type="button" class="btn btn-primary rounded-4 fw-800 py-2 shadow-sm"
                                id="note-save-btn" onclick="LDR.saveNote()">
                                <i class="fa-solid fa-floppy-disk me-2"></i>Update Main Note
                            </button>
                        </div>
                    </div>

                    <hr class="opacity-10 my-4">

                    <div class="mb-2 d-flex align-items-center">
                        <label
                            class="small text-secondary fw-800 text-uppercase letter-spacing-1 mb-0 flex-grow-1">Settlement
                            History</label>
                        <span class="x-small text-muted fw-500">Timeline of past payments</span>
                    </div>

                    <div id="note-history-list" class="mt-3" style="max-height: 300px; overflow-y: auto;">
                        <!-- Timeline items will be injected here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4">
                <button type="button" class="btn btn-white border rounded-4 fw-600 w-100" data-bs-dismiss="modal">Close
                    View</button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>