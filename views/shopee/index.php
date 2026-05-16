<?php
// views/shopee/index.php — Shopee Sync Dashboard
$page_title = 'Shopee Sync — Dashboard';
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

    <!-- Breadcrumb -->
    <div class="sp-breadcrumb">
        <a href="<?= BASE_URL ?>views/shopee/index.php">Shopee Sync</a>
        <i class="fa-solid fa-chevron-right" style="font-size:0.6rem"></i>
        <span>Dashboard</span>
    </div>

    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="sp-title mb-0">Shopee Sync Dashboard</h1>
                <span class="sp-badge sp-badge-success py-1 px-2" style="font-size:0.65rem">
                    <span class="sp-status-dot online"></span>CONNECTED
                </span>
            </div>
            <p class="sp-subtitle mb-0">Overview of your Shopee marketplace integration</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-shopee shadow-sm" onclick="triggerFullSync(this)">
                <i class="fa-solid fa-cloud-arrow-down me-2"></i>Import Products
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS ── -->
    <div class="row g-3 mb-4">
        <div class="col-lg-2 col-md-4 col-6">
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background:var(--shopee-light);color:var(--shopee-primary)">
                    <i class="fa-solid fa-bag-shopping"></i>
                </div>
                <div>
                    <div class="sp-stat-label">Products</div>
                    <div class="sp-stat-value" id="kpiSynced">0</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background:var(--sp-info-bg);color:var(--sp-info)">
                    <i class="fa-solid fa-cubes"></i>
                </div>
                <div>
                    <div class="sp-stat-label">Online Stock</div>
                    <div class="sp-stat-value" id="kpiOnline">0</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background:var(--sp-success-bg);color:var(--sp-success)">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="sp-stat-label">Matched</div>
                    <div class="sp-stat-value" id="kpiActive">0</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background:var(--sp-warning-bg);color:var(--sp-warning)">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <div class="sp-stat-label">Low Stock</div>
                    <div class="sp-stat-value" id="kpiLow">0</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background:var(--sp-danger-bg);color:var(--sp-danger)">
                    <i class="fa-solid fa-box-open"></i>
                </div>
                <div>
                    <div class="sp-stat-label">Out of Stock</div>
                    <div class="sp-stat-value" id="kpiOos">0</div>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="sp-stat-card">
                <div class="sp-stat-icon" style="background:var(--sp-danger-bg);color:var(--sp-danger)">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
                <div>
                    <div class="sp-stat-label">Failed</div>
                    <div class="sp-stat-value" id="kpiFailed">0</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Column -->
        <div class="col-lg-8">
            <!-- Sync Activity Chart -->
            <div class="sp-card mb-4">
                <div class="sp-card-header">
                    <h5 class="mb-0"><i class="fa-solid fa-chart-line text-shopee me-2"></i>Sync Performance</h5>
                    <span class="small text-secondary">Last 7 Days</span>
                </div>
                <div class="sp-card-body">
                    <div style="height:300px">
                        <canvas id="chartSync"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Timeline -->
            <div class="sp-card">
                <div class="sp-card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fa-solid fa-clock-rotate-left text-shopee me-2"></i>Recent Activity Logs</h5>
                    <a href="<?= BASE_URL ?>views/shopee/logs.php" class="btn btn-ghost btn-sm">View Full History</a>
                </div>
                <div class="sp-card-body p-0">
                    <ul class="sp-timeline mb-0" id="activityTimeline" style="padding: 1.5rem">
                        <!-- Rendered by JS -->
                    </ul>
                </div>
            </div>
        </div>

        <!-- Sidebar Column -->
        <div class="col-lg-4">
            <!-- Sync Health Status Grid -->
            <div class="sp-card mb-4">
                <div class="sp-card-header">
                    <h5 class="mb-0"><i class="fa-solid fa-heart-pulse text-shopee me-2"></i>System Health</h5>
                </div>
                <div class="sp-card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="sp-health-item">
                                <div class="small text-secondary mb-1">API Connection</div>
                                <div class="fw-bold text-success"><i class="fa-solid fa-circle-check me-1"></i>Active</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="sp-health-item">
                                <div class="small text-secondary mb-1">Token Status</div>
                                <div class="fw-bold text-success"><i class="fa-solid fa-shield-check me-1"></i>Secure</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="sp-health-item">
                                <div class="small text-secondary mb-1">Last Sync</div>
                                <div class="fw-bold" id="lastSyncTime" style="font-size:0.85rem">Never</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="sp-health-item">
                                <div class="small text-secondary mb-1">Environment</div>
                                <div class="fw-bold text-info"><i class="fa-solid fa-flask me-1"></i>Test</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="sp-card">
                <div class="sp-card-header">
                    <h5 class="mb-0"><i class="fa-solid fa-wand-magic-sparkles text-shopee me-2"></i>Quick Tasks</h5>
                </div>
                <div class="sp-card-body d-grid gap-2">
                    <a href="<?= BASE_URL ?>views/shopee/allocation.php" class="sp-quick-link">
                        <i class="fa-solid fa-sliders"></i>
                        <div class="link-content">
                            <div class="link-title">Stock Allocation</div>
                            <div class="link-sub">Control how much stock Shopee can sell</div>
                        </div>
                        <i class="fa-solid fa-chevron-right ms-auto" style="font-size:0.7rem; color:var(--text-secondary)"></i>
                    </a>
                    <a href="<?= BASE_URL ?>views/shopee/mapping.php" class="sp-quick-link">
                        <i class="fa-solid fa-link"></i>
                        <div class="link-content">
                            <div class="link-title">Product Mapping</div>
                            <div class="link-sub">Match Shopee listings to POS items</div>
                        </div>
                        <i class="fa-solid fa-chevron-right ms-auto" style="font-size:0.7rem; color:var(--text-secondary)"></i>
                    </a>
                    <a href="<?= BASE_URL ?>views/shopee/products.php" class="sp-quick-link">
                        <i class="fa-solid fa-bag-shopping"></i>
                        <div class="link-content">
                            <div class="link-title">Inventory View</div>
                            <div class="link-sub">Browse all imported Shopee products</div>
                        </div>
                        <i class="fa-solid fa-chevron-right ms-auto" style="font-size:0.7rem; color:var(--text-secondary)"></i>
                    </a>
                    <a href="<?= BASE_URL ?>views/shopee/settings.php" class="sp-quick-link border-dashed" style="border-style:dashed">
                        <i class="fa-solid fa-gear"></i>
                        <div class="link-content">
                            <div class="link-title">API Settings</div>
                            <div class="link-sub">Update credentials and permissions</div>
                        </div>
                        <i class="fa-solid fa-chevron-right ms-auto" style="font-size:0.7rem; color:var(--text-secondary)"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" async></script>

<script>
async function loadDashboard() {
    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/get_dashboard_stats.php`);
        const data = await res.json();

        if (data.success) {
            // Update KPI
            document.getElementById('kpiSynced').textContent = data.kpi.synced.toLocaleString();
            document.getElementById('kpiOnline').textContent = data.kpi.online.toLocaleString();
            document.getElementById('kpiActive').textContent = data.kpi.active.toLocaleString();
            document.getElementById('kpiLow').textContent = data.kpi.low.toLocaleString();
            document.getElementById('kpiOos').textContent = data.kpi.oos.toLocaleString();
            document.getElementById('kpiFailed').textContent = data.kpi.failed.toLocaleString();
            document.getElementById('lastSyncTime').textContent = data.kpi.last_sync;

            // Update Timeline
            const tl = document.getElementById('activityTimeline');
            if (data.timeline && data.timeline.length) {
                tl.innerHTML = data.timeline.map(a => `
                    <li class="sp-timeline-item">
                        <div class="sp-timeline-dot ${a.dot}"></div>
                        <div class="flex-grow-1">
                            <div class="sp-timeline-text">${a.text}</div>
                            <div class="sp-timeline-sub text-truncate" style="max-width:300px">${a.sub}</div>
                        </div>
                        <div class="sp-timeline-time">${a.time}</div>
                    </li>
                `).join('');
            } else {
                tl.innerHTML = '<div class="text-center py-4 text-secondary small">No recent activity</div>';
            }

            // Update Charts
            if (data.charts && data.charts.sync && data.charts.sync.length) {
                renderSyncChart(data.charts.sync);
            }
        }
    } catch (e) {
        console.error('Failed to load dashboard:', e);
    }
}

function renderSyncChart(syncData) {
    if (typeof Chart === 'undefined') {
        // If Chart.js is still loading asynchronously, wait and retry
        setTimeout(() => renderSyncChart(syncData), 200);
        return;
    }
    const labels = syncData.map(d => d.date);
    const success = syncData.map(d => d.success);
    const failed = syncData.map(d => d.failed);

    const ctx = document.getElementById('chartSync');
    if (!ctx) return;
    
    const existing = Chart.getChart(ctx);
    if (existing) existing.destroy();

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                { label:'Successful', data: success, borderColor:'#34d399', tension:0.3, pointRadius:3, borderWidth:2 },
                { label:'Failed', data: failed, borderColor:'#f87171', tension:0.3, pointRadius:3, borderWidth:2 }
            ]
        },
        options: { 
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: true, position:'bottom', labels:{ boxWidth:8, usePointStyle:true, pointStyle:'circle', color: '#94a3b8', font:{size:10} } } },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 10 } } },
                y: { grid: { color: 'rgba(255,255,255,0.08)' }, ticks: { color: '#94a3b8', font: { size: 10 } }, beginAtZero: true }
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    loadDashboard();
});

async function triggerFullSync(btn) {
    if (!btn) btn = event?.currentTarget || document.querySelector('.btn-shopee');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Syncing...';
    
    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/fetch_products.php`);
        const data = await res.json();
        if (data.success) {
            if (typeof EllaToast !== 'undefined') EllaToast.success('Import completed successfully');
            loadDashboard();
        } else {
            if (typeof EllaToast !== 'undefined') EllaToast.error(data.error);
        }
    } catch (e) {
        if (typeof EllaToast !== 'undefined') EllaToast.error('Network error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
