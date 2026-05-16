<?php
// views/shopee/logs.php — Sync Logs
$page_title = 'Shopee Sync — Sync Logs';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    denyAccess("You do not have permission to access Shopee Sync.");
}
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/shopee-sync.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shopee-sync.css') ?>">

<div class="sp-page sp-animate">
    <div class="sp-breadcrumb">
        <a href="<?= BASE_URL ?>views/shopee/index.php">Shopee Sync</a>
        <i class="fa-solid fa-chevron-right" style="font-size:0.6rem"></i>
        <span>Sync Logs</span>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="sp-title mb-0"><i class="fa-solid fa-clock-rotate-left text-shopee me-2"></i>Sync Logs</h1>
            <p class="sp-subtitle mb-0">Track every stock update, order sync, and system event</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-shopee" onclick="exportLogs()"><i class="fa-solid fa-download me-2"></i>Export CSV</button>
            <button class="btn btn-ghost" onclick="clearFilters()"><i class="fa-solid fa-rotate-left me-2"></i>Clear Filters</button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="sp-stat-card accent-success">
                <div class="sp-stat-icon" style="background:var(--sp-success-bg);color:var(--sp-success)"><i class="fa-solid fa-check-circle"></i></div>
                <div><div class="sp-stat-label">Successful</div><div class="sp-stat-value"><?= number_format($counts['success'] ?? 0) ?></div></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="sp-stat-card accent-danger">
                <div class="sp-stat-icon" style="background:var(--sp-danger-bg);color:var(--sp-danger)"><i class="fa-solid fa-circle-xmark"></i></div>
                <div><div class="sp-stat-label">Failed</div><div class="sp-stat-value"><?= number_format($counts['failed'] ?? 0) ?></div></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="sp-stat-card accent-warning">
                <div class="sp-stat-icon" style="background:var(--sp-warning-bg);color:var(--sp-warning)"><i class="fa-solid fa-rotate"></i></div>
                <div><div class="sp-stat-label">Today's Total</div><div class="sp-stat-value"><?= number_format($counts['total'] ?? 0) ?></div></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="sp-stat-card accent-info">
                <div class="sp-stat-icon" style="background:var(--sp-info-bg);color:var(--sp-info)"><i class="fa-solid fa-clock"></i></div>
                <div><div class="sp-stat-label">Retention</div><div class="sp-stat-value">30 Days</div></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="sp-card mb-4">
        <div class="sp-card-body d-flex flex-wrap align-items-center gap-3" style="padding:0.85rem 1.25rem">
            <div class="sp-search flex-grow-1" style="max-width:320px">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="logSearch" placeholder="Search by product, SKU, or event..." oninput="renderLogs()">
            </div>
            <div class="sp-filter-pills">
                <button class="sp-pill active" onclick="setLogFilter('all',this)">All Events</button>
                <button class="sp-pill" onclick="setLogFilter('product_import',this)">Product Import</button>
                <button class="sp-pill" onclick="setLogFilter('stock_update',this)">Stock Updates</button>
                <button class="sp-pill" onclick="setLogFilter('order',this)">Order Syncs</button>
                <button class="sp-pill" onclick="setLogFilter('allocation',this)">Allocations</button>
                <button class="sp-pill" onclick="setLogFilter('failed',this)">Failed</button>
            </div>
            <select class="form-select form-select-sm" style="max-width:140px" id="logPeriod" onchange="renderLogs()">
                <option value="all">All Time</option>
                <option value="today" selected>Today</option>
                <option value="week">This Week</option>
                <option value="month">This Month</option>
            </select>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="sp-card">
        <div class="sp-card-body p-0 sp-table-wrap">
            <table class="sp-table">
                <thead>
                    <tr>
                        <th style="width:180px">Timestamp</th>
                        <th>Product</th>
                        <th>Event Type</th>
                        <th class="text-center">Old Stock</th>
                        <th class="text-center"><i class="fa-solid fa-arrow-right"></i></th>
                        <th class="text-center">New Stock</th>
                        <th>Source</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="logBody"></tbody>
            </table>
        </div>
    </div>
</div>

<?php
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->query("SELECT * FROM shopee_sync_logs ORDER BY created_at DESC LIMIT 200");
$dbLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$logsJson = [];
foreach ($dbLogs as $l) {
    $logsJson[] = [
        'ts' => date('Y-m-d H:i:s', strtotime($l['created_at'])),
        'product' => $l['product_name'] ?: 'System Event',
        'sku' => $l['sku'] ?: '—',
        'type' => str_replace('_', ' ', ucfirst($l['event_type'])),
        'event' => $l['event_type'],
        'oldStock' => $l['old_value'] !== null ? $l['old_value'] : '—',
        'newStock' => $l['new_value'] !== null ? $l['new_value'] : '—',
        'source' => $l['source'] ?: 'Automated',
        'status' => $l['status']
    ];
}

$counts = $conn->query("SELECT 
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    COUNT(*) as total
FROM shopee_sync_logs WHERE created_at >= CURDATE()")->fetch(PDO::FETCH_ASSOC);
?>
<script>
const LOGS = <?= json_encode($logsJson) ?>;

let logFilter = 'all';

function setLogFilter(f, btn) {
    logFilter = f;
    document.querySelectorAll('.sp-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    renderLogs();
}

function clearFilters() {
    logFilter = 'all';
    document.getElementById('logSearch').value = '';
    document.getElementById('logPeriod').value = 'all';
    document.querySelectorAll('.sp-pill').forEach(p => p.classList.remove('active'));
    document.querySelector('.sp-pill').classList.add('active');
    renderLogs();
}

function renderLogs() {
    const search = (document.getElementById('logSearch')?.value || '').toLowerCase();
    const body = document.getElementById('logBody');

    let items = LOGS.filter(l => {
        if (search && !l.product.toLowerCase().includes(search) && !l.sku.toLowerCase().includes(search) && !l.type.toLowerCase().includes(search)) return false;
        if (logFilter !== 'all' && l.event !== logFilter) return false;
        return true;
    });

    if (!items.length) {
        body.innerHTML = '<tr><td colspan="8"><div class="sp-empty"><i class="fa-solid fa-clock-rotate-left d-block"></i><h5>No logs found</h5><p>Try adjusting your filters.</p></div></td></tr>';
        return;
    }

    body.innerHTML = items.map(l => {
        let eventBadge = '';
        switch(l.event) {
            case 'product_import': eventBadge = '<span class="sp-badge sp-badge-info"><i class="fa-solid fa-cloud-arrow-down"></i> Product Import</span>'; break;
            case 'stock_update': eventBadge = '<span class="sp-badge sp-badge-info"><i class="fa-solid fa-arrows-rotate"></i> Stock Update</span>'; break;
            case 'order': eventBadge = '<span class="sp-badge sp-badge-shopee"><i class="fa-solid fa-cart-shopping"></i> Order Sync</span>'; break;
            case 'allocation': eventBadge = '<span class="sp-badge sp-badge-success"><i class="fa-solid fa-sliders"></i> Allocation</span>'; break;
            case 'failed': eventBadge = '<span class="sp-badge sp-badge-danger"><i class="fa-solid fa-xmark"></i> Failed</span>'; break;
            case 'restore': eventBadge = '<span class="sp-badge sp-badge-warning"><i class="fa-solid fa-rotate-left"></i> Restore</span>'; break;
            default: eventBadge = '<span class="sp-badge sp-badge-neutral">' + l.type + '</span>';
        }

        let statusBadge = l.status === 'success'
            ? '<span class="sp-badge sp-badge-success"><i class="fa-solid fa-check"></i> Success</span>'
            : '<span class="sp-badge sp-badge-danger"><i class="fa-solid fa-xmark"></i> Failed</span>';

        const arrow = l.oldStock !== l.newStock && l.oldStock !== '—'
            ? (l.newStock > l.oldStock ? '<i class="fa-solid fa-arrow-up text-success"></i>' : '<i class="fa-solid fa-arrow-down text-danger"></i>')
            : '<i class="fa-solid fa-minus text-secondary"></i>';

        return `<tr>
            <td><div class="small text-secondary">${l.ts}</div></td>
            <td><div class="fw-bold">${l.product}</div><div class="small text-secondary">${l.sku}</div></td>
            <td>${eventBadge}</td>
            <td class="text-center fw-bold">${l.oldStock}</td>
            <td class="text-center">${arrow}</td>
            <td class="text-center fw-bold">${l.newStock}</td>
            <td><div class="small text-secondary">${l.source}</div></td>
            <td>${statusBadge}</td>
        </tr>`;
    }).join('');
}

document.addEventListener('DOMContentLoaded', renderLogs);

function exportLogs() {
    if (typeof EllaToast !== 'undefined') EllaToast.success('Sync logs exported as CSV');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
