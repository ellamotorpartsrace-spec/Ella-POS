<?php
// views/shopee/logs.php — Sync Logs
$page_title = 'Shopee Sync — Sync Logs';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    denyAccess("You do not have permission to access Shopee Sync.");
}

$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->query("
    SELECT l.*, u.full_name AS user_name 
    FROM shopee_sync_logs l
    LEFT JOIN users u ON l.created_by = u.id
    ORDER BY l.created_at DESC 
    LIMIT 200
");
$dbLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$logsJson = [];
foreach ($dbLogs as $l) {
    $logsJson[] = [
        'ts' => date('Y-m-d h:i:s A', strtotime($l['created_at'])),
        'product' => $l['product_name'] ?: 'System Event',
        'sku' => $l['sku'] ?: '—',
        'type' => str_replace('_', ' ', ucfirst($l['event_type'])),
        'event' => $l['event_type'],
        'oldStock' => $l['old_value'] !== null ? $l['old_value'] : '—',
        'newStock' => $l['new_value'] !== null ? $l['new_value'] : '—',
        'source' => $l['source'] ?: 'Automated',
        'status' => $l['status'],
        'error' => $l['error_message'] ?: '',
        'user' => $l['user_name'] ?: 'System'
    ];
}

$counts = $conn->query("SELECT 
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    COUNT(*) as total
FROM shopee_sync_logs WHERE created_at >= CURDATE()")->fetch(PDO::FETCH_ASSOC);

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
            <button class="btn btn-outline-danger" onclick="clearLogHistory()"><i class="fa-solid fa-trash me-2"></i>Clear History</button>
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
                <button class="sp-pill" onclick="setLogFilter('shopee_sku',this)">Shopee SKU</button>
                <button class="sp-pill" onclick="setLogFilter('mapping',this)">Mappings</button>
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
                        <th style="width:160px">Timestamp</th>
                        <th style="width:380px">Scope / Product</th>
                        <th style="width:140px">Event Type</th>
                        <th>Details / Summary</th>
                        <th style="width:120px">User</th>
                        <th style="width:110px">Source</th>
                        <th style="width:110px">Status</th>
                    </tr>
                </thead>
                <tbody id="logBody"></tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Database variables are handled at the top
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
        body.innerHTML = '<tr><td colspan="7"><div class="sp-empty"><i class="fa-solid fa-clock-rotate-left d-block"></i><h5>No logs found</h5><p>Try adjusting your filters.</p></div></td></tr>';
        return;
    }

    body.innerHTML = items.map(l => {
        let eventBadge = '';
        switch(l.event) {
            case 'product_import': 
                if (l.product.includes('Quick Sync')) {
                    eventBadge = '<span class="sp-badge" style="background:rgba(238,77,45,0.12);color:#ee4d2d"><i class="fa-solid fa-bolt me-1"></i>Quick Sync</span>';
                } else if (l.product.includes('Full Sync')) {
                    eventBadge = '<span class="sp-badge" style="background:rgba(59,130,246,0.12);color:#3b82f6"><i class="fa-solid fa-arrows-rotate me-1"></i>Full Sync</span>';
                } else if (l.product.includes('Stock Sync')) {
                    eventBadge = '<span class="sp-badge" style="background:rgba(16,185,129,0.12);color:#10b981"><i class="fa-solid fa-box me-1"></i>Stock Sync</span>';
                } else if (l.product.includes('Price Sync')) {
                    eventBadge = '<span class="sp-badge" style="background:rgba(245,158,11,0.12);color:#f59e0b"><i class="fa-solid fa-sack-dollar me-1"></i>Price Sync</span>';
                } else if (l.product.includes('Mapping Sync')) {
                    eventBadge = '<span class="sp-badge" style="background:rgba(102,16,242,0.12);color:#6610f2"><i class="fa-solid fa-link me-1"></i>Mapping Sync</span>';
                } else {
                    eventBadge = '<span class="sp-badge sp-badge-info"><i class="fa-solid fa-cloud-arrow-down me-1"></i>Import</span>';
                }
                break;
            case 'stock_update': eventBadge = '<span class="sp-badge sp-badge-success"><i class="fa-solid fa-arrows-rotate me-1"></i>Stock Sync</span>'; break;
            case 'shopee_sku': eventBadge = '<span class="sp-badge" style="background:rgba(253,126,20,0.12);color:#fd7e14;border:1px solid rgba(253,126,20,0.25)"><i class="fa-solid fa-tag me-1"></i>Shopee SKU</span>'; break;
            case 'mapping': eventBadge = '<span class="sp-badge sp-badge-info" style="background:rgba(102,16,242,0.1);color:#6610f2"><i class="fa-solid fa-link me-1"></i>Mapping</span>'; break;
            case 'order_sync':
            case 'order': eventBadge = '<span class="sp-badge sp-badge-neutral" style="background:#ff5722;color:#fff"><i class="fa-solid fa-cart-shopping me-1"></i>Order Sync</span>'; break;
            case 'allocation': eventBadge = '<span class="sp-badge sp-badge-success" style="background:rgba(40,167,69,0.1);color:#28a745"><i class="fa-solid fa-sliders me-1"></i>Allocation</span>'; break;
            case 'failed': eventBadge = '<span class="sp-badge sp-badge-danger"><i class="fa-solid fa-xmark me-1"></i>Failed</span>'; break;
            case 'restore': eventBadge = '<span class="sp-badge sp-badge-warning"><i class="fa-solid fa-rotate-left me-1"></i>Restore</span>'; break;
            case 'token_refresh': eventBadge = '<span class="sp-badge sp-badge-warning"><i class="fa-solid fa-key me-1"></i>Token</span>'; break;
            default: eventBadge = '<span class="sp-badge sp-badge-neutral">' + l.type + '</span>';
        }

        let statusBadge = l.status === 'success'
            ? '<span class="sp-badge sp-badge-success"><i class="fa-solid fa-check me-1"></i>Success</span>'
            : '<span class="sp-badge sp-badge-danger"><i class="fa-solid fa-xmark me-1"></i>Failed</span>';

        let details = '—';
        if (l.status === 'failed') {
            details = `<span class="text-danger small fw-semibold"><i class="fa-solid fa-circle-exclamation me-1"></i>${l.error || l.newStock || 'Operation failed'}</span>`;
        } else if (l.event === 'mapping') {
            if (l.oldStock === '—' || l.oldStock === 'Unmapped' || !l.oldStock) {
                details = `<span class="small text-dark">Linked to POS SKU: <span class="font-monospace bg-light border rounded px-1 py-0.5 text-shopee fw-semibold">${l.newStock}</span></span>`;
            } else if (l.newStock === 'Unmapped' || l.newStock === '—' || !l.newStock) {
                details = `<span class="small text-muted">Unlinked from POS SKU: <del class="font-monospace bg-light border rounded px-1 py-0.5 text-muted">${l.oldStock}</del></span>`;
            } else {
                details = `<span class="small text-dark">Changed link: <del class="font-monospace bg-light border rounded px-1 py-0.5 text-muted">${l.oldStock}</del> <i class="fa-solid fa-arrow-right text-shopee mx-1" style="font-size:0.85rem"></i> <span class="font-monospace bg-light border rounded px-1 py-0.5 text-shopee fw-semibold">${l.newStock}</span></span>`;
            }
        } else if (l.event === 'stock_update' || l.event === 'allocation') {
            if (l.oldStock !== '—' && l.newStock !== '—' && !isNaN(l.oldStock) && !isNaN(l.newStock)) {
                const arrowIcon = Number(l.newStock) > Number(l.oldStock)
                    ? '<i class="fa-solid fa-arrow-trend-up text-success mx-1"></i>' 
                    : '<i class="fa-solid fa-arrow-trend-down text-danger mx-1"></i>';
                details = `<span class="small text-dark">Stock changed: <strong>${l.oldStock}</strong> ${arrowIcon} <strong>${l.newStock}</strong></span>`;
            } else {
                details = `<span class="small text-secondary">${l.newStock || '—'}</span>`;
            }
        } else if (l.event === 'shopee_sku') {
            if (l.newStock && l.newStock.includes('Added/Fix Missing SKU: ')) {
                const skuVal = l.newStock.replace('Added/Fix Missing SKU: ', '');
                details = `<span class="small text-dark">Added/Fix Missing SKU: <span class="font-monospace bg-light border rounded px-1.5 py-0.5 text-shopee fw-semibold" style="color:#fd7e14; background:rgba(253,126,20,0.06); border-color:rgba(253,126,20,0.18)!important">${skuVal}</span></span>`;
            } else {
                details = `<span class="small text-secondary">${l.newStock}</span>`;
            }
        } else {
            if (l.newStock === '—' || !l.newStock) {
                if (l.event === 'token_refresh') {
                    details = '<span class="small text-secondary">Token successfully refreshed</span>';
                } else {
                    details = '<span class="small text-secondary">—</span>';
                }
            } else {
                details = `<span class="small text-secondary">${l.newStock}</span>`;
            }
        }

        const parts = l.product.split(' — ');
        const mainName = parts[0];
        const varName = parts[1] || '';

        return `<tr>
            <td><div class="small text-secondary">${l.ts}</div></td>
            <td>
                <div class="fw-bold text-dark small" style="max-width:500px; word-break:break-word; line-height:1.3;" title="${mainName}">${mainName}</div>
                ${varName ? `<div class="small" style="font-size:0.75rem; margin-top:2px;">
                    <span class="text-secondary fw-semibold">Variation:</span> 
                    <span class="fw-bold text-dark">${varName}</span>
                </div>` : ''}
            </td>
            <td>${eventBadge}</td>
            <td><div class="text-start">${details}</div></td>
            <td><span class="small fw-semibold text-secondary"><i class="fa-solid fa-user me-1" style="font-size:0.75rem"></i>${l.user}</span></td>
            <td><div class="small text-secondary">${l.source}</div></td>
            <td>${statusBadge}</td>
        </tr>`;
    }).join('');
}

document.addEventListener('DOMContentLoaded', renderLogs);

async function clearLogHistory() {
    if (!confirm("Are you sure you want to clear all sync logs history? This action cannot be undone.")) return;
    
    try {
        const res = await fetch(window.BASE_URL + 'api/shopee/clear_logs.php', {
            method: 'POST'
        });
        const data = await res.json();
        if (data.success) {
            if (typeof EllaToast !== 'undefined') {
                EllaToast.success('Sync history cleared successfully');
            } else {
                alert('Sync history cleared successfully');
            }
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error(data.error || 'Failed to clear history');
        }
    } catch(err) {
        if (typeof EllaToast !== 'undefined') {
            EllaToast.error(err.message);
        } else {
            alert(err.message);
        }
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
