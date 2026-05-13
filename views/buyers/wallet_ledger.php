<?php
// views/buyers/wallet_ledger.php
// Wallet Balance Monitor — all wallet credits, debits & adjustments across all buyers
require_once '../../config/config.php';
require_once '../../includes/auth.php';

requirePermission('view_wallet_ledger');

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

$db   = new Database();
$conn = $db->getConnection();

// Buyers list for filter dropdown
$buyers = $conn->query("SELECT buyer_id, buyer_name, shop_name FROM buyers ORDER BY buyer_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* ── Summary Cards ── */
    .wallet-stat-card {
        border: none;
        border-radius: 16px;
        padding: 20px 24px;
        position: relative;
        overflow: hidden;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .wallet-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12) !important;
    }
    .wallet-stat-card .stat-icon {
        width: 52px; height: 52px;
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4rem;
    }
    .wallet-stat-card .stat-value {
        font-size: 1.7rem;
        font-weight: 800;
        line-height: 1.1;
    }
    .wallet-stat-card .stat-label {
        font-size: 0.78rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    /* ── Log Table ── */
    .log-table th {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }
    .log-table td { vertical-align: middle; }
    .log-table tbody tr {
        transition: background 0.15s;
        border-bottom: 1px solid rgba(0,0,0,.04);
    }
    .log-table tbody tr:hover { background: rgba(99,102,241,.04); }

    /* ── Type pill ── */
    .pill-credit {
        background: #dcfce7; color: #15803d;
        padding: 3px 10px; border-radius: 20px;
        font-size: 0.75rem; font-weight: 700;
    }
    .pill-debit {
        background: #fee2e2; color: #b91c1c;
        padding: 3px 10px; border-radius: 20px;
        font-size: 0.75rem; font-weight: 700;
    }

    /* ── Balance sparkline pill ── */
    .balance-chip {
        font-size: 0.8rem; font-weight: 700;
        padding: 2px 8px; border-radius: 8px;
        background: #f1f5f9; color: #475569;
        display: inline-block;
    }

    /* ── Reference link ── */
    .ref-link {
        font-size: 0.8rem;
        color: #6366f1;
        text-decoration: none;
        font-weight: 600;
    }
    .ref-link:hover { text-decoration: underline; }

    /* ── Remarks truncation ── */
    .remarks-cell {
        max-width: 220px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 0.8rem;
        color: #64748b;
    }

    /* ── Mobile card ── */
    .wallet-card-mobile {
        border-radius: 14px;
        border: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        margin-bottom: 12px;
        transition: transform 0.2s;
    }
    .wallet-card-mobile:hover { transform: translateY(-2px); }

    /* ── Pagination ── */
    .page-link { border-radius: 8px !important; margin: 0 2px; }

    /* ── Loading overlay ── */
    #loading-overlay {
        display: none;
        position: absolute; inset: 0;
        background: rgba(255,255,255,.65);
        z-index: 9; align-items: center; justify-content: center;
        border-radius: 16px;
    }
    #loading-overlay.show { display: flex; }

    @media (max-width: 768px) {
        .wallet-stat-card .stat-value { font-size: 1.3rem; }
        .hide-mobile { display: none !important; }
    }
</style>

<div class="container-fluid p-3 p-lg-4">

    <!-- ── Page Header ── -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h4 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-wallet text-success me-2"></i>Wallet Ledger
            </h4>
            <p class="text-muted mb-0 small">Monitor all wallet credits, debits, and adjustments across buyers</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Back to Buyers
        </a>
    </div>

    <!-- ── Summary Stats ── -->
    <div class="row g-3 mb-4" id="stats-row">
        <div class="col-6 col-lg-3">
            <div class="card wallet-stat-card shadow-sm" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:#bbf7d0;">
                        <i class="fa-solid fa-arrow-trend-up text-success"></i>
                    </div>
                    <div>
                        <div class="stat-value text-success" id="stat-credits">₱0</div>
                        <div class="stat-label text-success">Total Credits</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card wallet-stat-card shadow-sm" style="background:linear-gradient(135deg,#fff5f5,#fee2e2);">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:#fecaca;">
                        <i class="fa-solid fa-arrow-trend-down text-danger"></i>
                    </div>
                    <div>
                        <div class="stat-value text-danger" id="stat-debits">₱0</div>
                        <div class="stat-label text-danger">Total Debits</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card wallet-stat-card shadow-sm" style="background:linear-gradient(135deg,#eff6ff,#dbeafe);">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:#bfdbfe;">
                        <i class="fa-solid fa-list-check text-primary"></i>
                    </div>
                    <div>
                        <div class="stat-value text-primary" id="stat-total">0</div>
                        <div class="stat-label text-primary">Log Entries</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card wallet-stat-card shadow-sm" style="background:linear-gradient(135deg,#fefce8,#fef9c3);">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background:#fde68a;">
                        <i class="fa-solid fa-users text-warning"></i>
                    </div>
                    <div>
                        <div class="stat-value text-warning" id="stat-buyers">0</div>
                        <div class="stat-label text-warning">Buyers Active</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Filters ── -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form id="filter-form" class="row g-2 align-items-end">
                <div class="col-12 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">FROM</label>
                    <input type="date" id="f-date-from" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">TO</label>
                    <input type="date" id="f-date-to" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">BUYER</label>
                    <select id="f-buyer" class="form-select form-select-sm">
                        <option value="">All Buyers</option>
                        <?php foreach ($buyers as $b): ?>
                            <option value="<?= $b['buyer_id'] ?>">
                                <?= htmlspecialchars($b['buyer_name']) ?>
                                <?= $b['shop_name'] ? '· ' . htmlspecialchars($b['shop_name']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">TYPE</label>
                    <select id="f-type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="credit">Credit (Add)</option>
                        <option value="debit">Debit (Deduct)</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">SEARCH</label>
                    <input type="text" id="f-search" class="form-control form-control-sm"
                        placeholder="Buyer, ref, remarks..." autocomplete="off">
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="fa-solid fa-filter me-1"></i>Filter
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-reset" title="Reset">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Results ── -->
    <div class="card border-0 shadow-sm" style="position:relative;">
        <div id="loading-overlay">
            <div class="spinner-border text-success" role="status"></div>
        </div>

        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">
                <i class="fa-solid fa-clock-rotate-left text-success me-2"></i>Wallet Activity Log
            </h6>
            <span class="badge bg-secondary" id="result-count">0 records</span>
        </div>

        <div class="card-body p-0">
            <!-- Loading placeholder -->
            <div id="loading-state" class="text-center py-5">
                <div class="spinner-border text-success" role="status"></div>
                <p class="text-muted mt-2 small">Loading wallet logs...</p>
            </div>

            <!-- Empty state -->
            <div id="empty-state" class="text-center py-5 d-none">
                <i class="fa-solid fa-wallet fa-3x text-muted opacity-25 mb-3"></i>
                <h6 class="text-muted">No wallet activity found</h6>
                <p class="small text-muted">Try adjusting your filters</p>
            </div>

            <!-- Desktop table -->
            <div class="table-responsive d-none d-lg-block" id="table-wrapper">
                <table class="table table-hover log-table mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Date & Time</th>
                            <th>Buyer</th>
                            <th class="text-center">Type</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Wallet After</th>
                            <th>Reference</th>
                            <th>Remarks</th>
                            <th>Cashier</th>
                        </tr>
                    </thead>
                    <tbody id="logs-tbody"></tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <div class="p-3 d-lg-none" id="logs-cards"></div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center p-3 border-top d-none" id="pagination-row">
                <small class="text-muted" id="pagination-info"></small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination-list"></ul>
                </nav>
            </div>
        </div>
    </div>

</div>

<script>
    let currentPage = 1;
    let lastData = null;

    // ── Init ──────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('filter-form').addEventListener('submit', e => {
            e.preventDefault();
            currentPage = 1;
            load();
        });

        document.getElementById('btn-reset').addEventListener('click', () => {
            document.getElementById('f-date-from').value  = new Date().toISOString().slice(0,7) + '-01';
            document.getElementById('f-date-to').value    = new Date().toISOString().slice(0,10);
            document.getElementById('f-buyer').value      = '';
            document.getElementById('f-type').value       = '';
            document.getElementById('f-search').value     = '';
            currentPage = 1;
            load();
        });

        // Debounced search
        let debounce;
        document.getElementById('f-search').addEventListener('input', () => {
            clearTimeout(debounce);
            debounce = setTimeout(() => { currentPage = 1; load(); }, 380);
        });

        load();
    });

    // ── Load ──────────────────────────────────────────────────────────────────
    async function load() {
        const overlay = document.getElementById('loading-overlay');
        overlay.classList.add('show');
        document.getElementById('loading-state').classList.remove('d-none');
        document.getElementById('empty-state').classList.add('d-none');
        document.getElementById('table-wrapper')?.classList.add('d-none');
        document.getElementById('logs-cards').innerHTML = '';
        document.getElementById('pagination-row').classList.add('d-none');

        const params = new URLSearchParams({
            date_from: document.getElementById('f-date-from').value,
            date_to:   document.getElementById('f-date-to').value,
            buyer_id:  document.getElementById('f-buyer').value,
            type:      document.getElementById('f-type').value,
            search:    document.getElementById('f-search').value,
            page:      currentPage,
        });

        try {
            const res  = await fetch(`../../api/buyers/get_wallet_logs.php?${params}`);
            const data = await res.json();

            overlay.classList.remove('show');
            document.getElementById('loading-state').classList.add('d-none');

            if (!data.success) throw new Error(data.error || 'Failed to load.');

            lastData = data;
            updateStats(data.stats);

            if (!data.logs || data.logs.length === 0) {
                document.getElementById('empty-state').classList.remove('d-none');
                document.getElementById('result-count').textContent = '0 records';
                return;
            }

            document.getElementById('result-count').textContent = `${data.total.toLocaleString()} records`;
            renderTable(data.logs);
            renderCards(data.logs);
            renderPagination(data.page, data.total_pages, data.total, data.per_page);

        } catch (err) {
            overlay.classList.remove('show');
            document.getElementById('loading-state').classList.add('d-none');
            document.getElementById('empty-state').classList.remove('d-none');
            document.getElementById('empty-state').innerHTML = `
                <i class="fa-solid fa-exclamation-circle fa-3x text-danger opacity-50 mb-3"></i>
                <h6 class="text-danger">Error Loading Data</h6>
                <p class="small text-muted">${esc(err.message)}</p>
            `;
            console.error(err);
        }
    }

    function updateStats(s) {
        document.getElementById('stat-credits').textContent = '₱' + fmt(s.total_credits || 0);
        document.getElementById('stat-debits').textContent  = '₱' + fmt(s.total_debits  || 0);
        document.getElementById('stat-total').textContent   = Number(s.total_logs   || 0).toLocaleString();
        document.getElementById('stat-buyers').textContent  = Number(s.unique_buyers || 0).toLocaleString();
    }

    // ── Table ─────────────────────────────────────────────────────────────────
    function renderTable(logs) {
        const tbody = document.getElementById('logs-tbody');
        tbody.innerHTML = logs.map(log => {
            const isCredit = log.type === 'credit';
            return `
            <tr>
                <td class="ps-4">
                    <div class="small fw-semibold">${fmtDate(log.created_at)}</div>
                    <div class="x-small text-muted">${fmtTime(log.created_at)}</div>
                </td>
                <td>
                    <div class="fw-semibold small">${esc(log.buyer_name || '—')}</div>
                    ${log.shop_name ? `<div class="x-small text-muted">${esc(log.shop_name)}</div>` : ''}
                </td>
                <td class="text-center">
                    <span class="${isCredit ? 'pill-credit' : 'pill-debit'}">
                        <i class="fa-solid ${isCredit ? 'fa-plus' : 'fa-minus'} me-1"></i>
                        ${isCredit ? 'Credit' : 'Debit'}
                    </span>
                </td>
                <td class="text-end fw-bold ${isCredit ? 'text-success' : 'text-danger'}">
                    ${isCredit ? '+' : '-'}₱${fmt(log.amount)}
                </td>
                <td class="text-end">
                    <span class="balance-chip">₱${fmt(log.balance_after)}</span>
                </td>
                <td>
                    ${log.reference_id
                        ? `<a href="../pos/receipts.php?search=${encodeURIComponent(log.reference_id)}" target="_blank" class="ref-link">
                                <i class="fa-solid fa-receipt me-1"></i>${esc(log.reference_id)}
                            </a>`
                        : `<span class="text-muted small">—</span>`}
                </td>
                <td>
                    <div class="remarks-cell" title="${esc(log.remarks || '')}">${esc(remarkLabel(log.remarks))}</div>
                </td>
                <td>
                    <span class="small text-muted">${esc(log.cashier_name || 'System')}</span>
                </td>
            </tr>`;
        }).join('');

        document.getElementById('table-wrapper').classList.remove('d-none');
    }

    // ── Mobile Cards ──────────────────────────────────────────────────────────
    function renderCards(logs) {
        const container = document.getElementById('logs-cards');
        container.innerHTML = logs.map(log => {
            const isCredit = log.type === 'credit';
            return `
            <div class="card wallet-card-mobile">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-bold small">${esc(log.buyer_name || '—')}</div>
                            ${log.shop_name ? `<div class="x-small text-muted">${esc(log.shop_name)}</div>` : ''}
                            <div class="x-small text-muted mt-1">${fmtDate(log.created_at)} ${fmtTime(log.created_at)}</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold fs-6 ${isCredit ? 'text-success' : 'text-danger'}">
                                ${isCredit ? '+' : '-'}₱${fmt(log.amount)}
                            </div>
                            <span class="${isCredit ? 'pill-credit' : 'pill-debit'} mt-1 d-inline-block">
                                ${isCredit ? 'Credit' : 'Debit'}
                            </span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <div class="small text-muted remarks-cell" style="max-width:180px;" title="${esc(log.remarks || '')}">
                            ${esc(remarkLabel(log.remarks))}
                        </div>
                        <span class="balance-chip">Balance: ₱${fmt(log.balance_after)}</span>
                    </div>
                    ${log.reference_id ? `<div class="mt-1">
                        <a href="../pos/receipts.php?search=${encodeURIComponent(log.reference_id)}" target="_blank" class="ref-link">
                            <i class="fa-solid fa-receipt me-1"></i>${esc(log.reference_id)}
                        </a>
                    </div>` : ''}
                </div>
            </div>`;
        }).join('');
    }

    // ── Pagination ────────────────────────────────────────────────────────────
    function renderPagination(page, totalPages, total, perPage) {
        const row  = document.getElementById('pagination-row');
        const info = document.getElementById('pagination-info');
        const list = document.getElementById('pagination-list');

        if (totalPages <= 1) { row.classList.add('d-none'); return; }

        const from = (page - 1) * perPage + 1;
        const to   = Math.min(page * perPage, total);
        info.textContent = `Showing ${from}–${to} of ${total.toLocaleString()}`;

        const pages = [];
        if (page > 1) pages.push({ label: '«', pg: page - 1 });
        for (let p = Math.max(1, page - 2); p <= Math.min(totalPages, page + 2); p++) pages.push({ label: p, pg: p });
        if (page < totalPages) pages.push({ label: '»', pg: page + 1 });

        list.innerHTML = pages.map(({ label, pg }) => `
            <li class="page-item ${pg === page ? 'active' : ''}">
                <button class="page-link" onclick="goPage(${pg})">${label}</button>
            </li>
        `).join('');

        row.classList.remove('d-none');
    }

    function goPage(pg) {
        currentPage = pg;
        load();
        window.scrollTo(0, 0);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function fmt(v) {
        return parseFloat(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtDate(d) {
        return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function fmtTime(d) {
        return new Date(d).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    }

    function remarkLabel(r) {
        if (!r) return '—';
        // Strip prefix tags like [CREDIT ADJ], [DEBIT ADJ] for cleaner display
        return r.replace(/^\[(CREDIT|DEBIT) ADJ\]\s*/i, '').replace(/^\[.*?\]\s*/, '');
    }

    function esc(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }
</script>

<?php require_once '../../includes/footer.php'; ?>
