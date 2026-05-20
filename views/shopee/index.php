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
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/shopee-sync.css?v=<?= filemtime(__DIR__.'/../../assets/css/shopee-sync.css') ?>">
<style>
.sp-section-title {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-secondary);
}
.sp-dash-nav {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}
@media (max-width: 991px) {
    .sp-dash-nav {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 575px) {
    .sp-dash-nav {
        grid-template-columns: 1fr;
    }
}
.sp-nav-card {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.2rem;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    background: var(--bg-surface);
    text-decoration: none;
    color: var(--text-primary);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.sp-nav-card:hover {
    border-color: var(--shopee-primary);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(238, 77, 45, 0.06);
    color: var(--text-primary);
}
.sp-nav-card-icon {
    width: 42px;
    height: 42px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
    transition: transform 0.3s;
}
.sp-nav-card:hover .sp-nav-card-icon {
    transform: scale(1.08);
}
.sp-nav-card-content {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    text-align: left;
}
.sp-nav-card-label {
    font-weight: 700;
    font-size: 0.88rem;
    color: var(--text-primary);
    transition: color 0.15s ease;
}
.sp-nav-card:hover .sp-nav-card-label {
    color: var(--shopee-primary);
}
.sp-nav-card-desc {
    font-size: 0.74rem;
    color: var(--text-secondary);
    line-height: 1.35;
}
.sp-health-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.sp-alert-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.85rem 1.1rem;
    border-radius: 10px;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    transition: all 0.15s ease;
}
.sp-alert-item:hover {
    transform: translateX(3px);
    border-color: var(--border-color-hover);
}
.sp-alert-item.danger {
    border-left: 3.5px solid var(--sp-danger);
}
.sp-alert-item.warning {
    border-left: 3.5px solid var(--sp-warning);
}
.sp-alert-item.success {
    border-left: 3.5px solid var(--sp-success);
}
.sp-alert-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.sp-alert-left i {
    font-size: 1rem;
}
.sp-alert-title {
    font-weight: 600;
    font-size: 0.84rem;
    color: var(--text-primary);
}
.sp-alert-count {
    font-weight: 700;
    font-size: 0.95rem;
    font-family: 'SFMono-Regular', Consolas, monospace;
}
.sp-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}
.sp-dot.green {
    background: var(--sp-success);
    box-shadow: 0 0 6px var(--sp-success);
}
.sp-dot.red {
    background: var(--sp-danger);
    box-shadow: 0 0 6px var(--sp-danger);
}
.sp-dot.yellow {
    background: var(--sp-warning);
    box-shadow: 0 0 6px var(--sp-warning);
}
</style>

<div class="sp-page sp-animate">
    <div class="sp-breadcrumb">
        <span>Shopee Sync</span>
        <i class="fa-solid fa-chevron-right" style="font-size:.6rem"></i>
        <span>Dashboard</span>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="sp-title mb-0">Shopee Sync Dashboard</h1>
                <span class="sp-badge py-1 px-2" style="font-size:.65rem" id="connBadge"><span class="sp-dot yellow"></span> Checking...</span>
            </div>
            <p class="sp-subtitle mb-0">Operational control center for your Shopee integration</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left: Operations & Core Metrics -->
        <div class="col-lg-8">
            <!-- Quick Navigation Tiles -->
            <div class="mb-4">
                <div class="sp-section-title mb-3"><i class="fa-solid fa-compass text-shopee me-2"></i>Quick Navigation</div>
                <div class="sp-dash-nav">
                    <a href="<?= BASE_URL ?>views/shopee/products.php" class="sp-nav-card">
                        <div class="sp-nav-card-icon" style="background:var(--shopee-light);color:var(--shopee-primary)"><i class="fa-solid fa-bag-shopping"></i></div>
                        <div class="sp-nav-card-content">
                            <span class="sp-nav-card-label">Shopee Products</span>
                            <span class="sp-nav-card-desc">Browse local and online synced listings catalog</span>
                        </div>
                    </a>
                    <a href="<?= BASE_URL ?>views/shopee/mapping.php" class="sp-nav-card">
                        <div class="sp-nav-card-icon" style="background:var(--sp-success-bg);color:var(--sp-success)"><i class="fa-solid fa-link"></i></div>
                        <div class="sp-nav-card-content">
                            <span class="sp-nav-card-label">Product Mapping</span>
                            <span class="sp-nav-card-desc">Match local ERP items to Shopee listings</span>
                        </div>
                    </a>
                    <a href="<?= BASE_URL ?>views/shopee/allocation.php" class="sp-nav-card">
                        <div class="sp-nav-card-icon" style="background:var(--sp-info-bg);color:var(--sp-info)"><i class="fa-solid fa-sliders"></i></div>
                        <div class="sp-nav-card-content">
                            <span class="sp-nav-card-label">Stock Allocation</span>
                            <span class="sp-nav-card-desc">Manage online stock safety limits and rules</span>
                        </div>
                    </a>
                    <a href="<?= BASE_URL ?>views/shopee/resolution_center.php" class="sp-nav-card">
                        <div class="sp-nav-card-icon" style="background:var(--sp-danger-bg);color:var(--sp-danger)"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div class="sp-nav-card-content">
                            <span class="sp-nav-card-label">Resolution Center</span>
                            <span class="sp-nav-card-desc">Detect and fix duplicate SKU inventory blocks</span>
                        </div>
                    </a>
                    <a href="<?= BASE_URL ?>views/shopee/logs.php" class="sp-nav-card">
                        <div class="sp-nav-card-icon" style="background:var(--sp-neutral-bg);color:var(--text-secondary)"><i class="fa-solid fa-clock-rotate-left"></i></div>
                        <div class="sp-nav-card-content">
                            <span class="sp-nav-card-label">Sync Logs</span>
                            <span class="sp-nav-card-desc">Review automatic stock and order update events</span>
                        </div>
                    </a>
                    <a href="<?= BASE_URL ?>views/shopee/settings.php" class="sp-nav-card">
                        <div class="sp-nav-card-icon" style="background:var(--sp-warning-bg);color:var(--sp-warning)"><i class="fa-solid fa-gear"></i></div>
                        <div class="sp-nav-card-content">
                            <span class="sp-nav-card-label">Settings & Setup</span>
                            <span class="sp-nav-card-desc">Configure API settings and system sync loops</span>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Core Integration Metrics -->
            <div>
                <div class="sp-section-title mb-3"><i class="fa-solid fa-chart-simple text-shopee me-2"></i>Integration Metrics</div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="sp-stat-card accent-shopee">
                            <div class="sp-stat-icon" style="background:var(--shopee-light);color:var(--shopee-primary)"><i class="fa-solid fa-bag-shopping"></i></div>
                            <div>
                                <div class="sp-stat-label">Total Sync Products</div>
                                <div class="sp-stat-value" id="kTotalP">—</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="sp-stat-card">
                            <div class="sp-stat-icon" style="background:var(--sp-info-bg);color:var(--sp-info)"><i class="fa-solid fa-layer-group"></i></div>
                            <div>
                                <div class="sp-stat-label">Total Variations</div>
                                <div class="sp-stat-value" id="kTotalV">—</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="sp-stat-card accent-success">
                            <div class="sp-stat-icon" style="background:var(--sp-success-bg);color:var(--sp-success)"><i class="fa-solid fa-link"></i></div>
                            <div>
                                <div class="sp-stat-label">Mapped Items</div>
                                <div class="sp-stat-value" id="kMatched">—</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="sp-stat-card accent-warning">
                            <div class="sp-stat-icon" style="background:var(--sp-warning-bg);color:var(--sp-warning)"><i class="fa-solid fa-link-slash"></i></div>
                            <div>
                                <div class="sp-stat-label">Unmapped Listings</div>
                                <div class="sp-stat-value" id="kUnmatched">—</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: System Status & Alerts Sidebar -->
        <div class="col-lg-4">
            <div class="sp-health-sidebar">
                <!-- System Connection Health -->
                <div>
                    <div class="sp-section-title mb-3"><i class="fa-solid fa-heart-pulse text-shopee me-2"></i>System Connection</div>
                    <div class="sp-card">
                        <div class="sp-card-body p-3 d-flex flex-column gap-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small text-secondary fw-semibold">Shopee API</span>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="sp-dot" id="dotConn"></span>
                                    <span class="small fw-bold" id="valConn">—</span>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small text-secondary fw-semibold">Token Status</span>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="sp-dot" id="dotToken"></span>
                                    <span class="small fw-bold" id="valToken">—</span>
                                </div>
                            </div>
                            <hr style="margin:0.25rem 0; opacity:0.08">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="small text-secondary fw-semibold"><i class="fa-solid fa-clock me-1"></i>Last Sync Event</span>
                                <span class="small fw-bold text-dark" id="valLastSync">—</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Critical Alerts -->
                <div>
                    <div class="sp-section-title mb-3"><i class="fa-solid fa-bell text-shopee me-2"></i>Critical Health & Alerts</div>
                    <div class="d-flex flex-column gap-2">
                        <!-- Missing SKUs -->
                        <div class="sp-alert-item danger">
                            <div class="sp-alert-left">
                                <i class="fa-solid fa-triangle-exclamation text-danger"></i>
                                <span class="sp-alert-title">Missing SKUs</span>
                            </div>
                            <span class="sp-alert-count text-danger" id="kMissingErrors">—</span>
                        </div>

                        <!-- Duplicate SKUs -->
                        <div class="sp-alert-item danger">
                            <div class="sp-alert-left">
                                <i class="fa-solid fa-clone text-danger"></i>
                                <span class="sp-alert-title">Duplicate SKUs</span>
                            </div>
                            <span class="sp-alert-count text-danger" id="kDupErrors">—</span>
                        </div>

                        <!-- Out of Stock -->
                        <div class="sp-alert-item danger">
                            <div class="sp-alert-left">
                                <i class="fa-solid fa-circle-xmark text-danger"></i>
                                <span class="sp-alert-title">Out of Stock</span>
                            </div>
                            <span class="sp-alert-count text-danger" id="kOos">—</span>
                        </div>

                        <!-- Low Stock -->
                        <div class="sp-alert-item warning">
                            <div class="sp-alert-left">
                                <i class="fa-solid fa-box-open text-warning"></i>
                                <span class="sp-alert-title">Low Stock Alert</span>
                            </div>
                            <span class="sp-alert-count text-warning" id="kLow">—</span>
                        </div>

                        <!-- Synced (24h) -->
                        <div class="sp-alert-item success">
                            <div class="sp-alert-left">
                                <i class="fa-solid fa-rotate text-success"></i>
                                <span class="sp-alert-title">Updates (24h)</span>
                            </div>
                            <span class="sp-alert-count text-success" id="kRecent">—</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const res  = await fetch(`${window.BASE_URL}api/shopee/get_dashboard_stats.php`);
        const data = await res.json();
        if (!data.success) return;
        const k = data.kpi, s = data.status;

        // KPIs
        document.getElementById('kTotalP').textContent    = k.total_products.toLocaleString();
        document.getElementById('kTotalV').textContent    = k.total_variations.toLocaleString();
        document.getElementById('kMatched').textContent   = k.matched.toLocaleString();
        document.getElementById('kUnmatched').textContent = k.unmatched.toLocaleString();
        
        let missingErrors = 0;
        let duplicateErrors = 0;
        if (data.health) {
            if (data.health.missing_errors !== undefined) missingErrors = data.health.missing_errors;
            if (data.health.duplicate_errors !== undefined) duplicateErrors = data.health.duplicate_errors;
        }
        document.getElementById('kMissingErrors').textContent = missingErrors.toLocaleString();
        document.getElementById('kDupErrors').textContent     = duplicateErrors.toLocaleString();
        
        document.getElementById('kLow').textContent       = k.low_stock.toLocaleString();
        document.getElementById('kOos').textContent       = k.oos.toLocaleString();
        document.getElementById('kRecent').textContent    = k.recently_synced.toLocaleString();

        // Connection badge
        const cb = document.getElementById('connBadge');
        if (s.connected) { cb.innerHTML = '<span class="sp-dot green"></span> Connected'; cb.className = 'sp-badge sp-badge-success py-1 px-2'; cb.style.fontSize='.65rem'; }
        else             { cb.innerHTML = '<span class="sp-dot red"></span> Not Connected'; cb.className = 'sp-badge sp-badge-danger py-1 px-2'; cb.style.fontSize='.65rem'; }

        // Status bar
        document.getElementById('dotConn').className  = 'sp-dot '+(s.connected   ? 'green':'red');
        document.getElementById('dotToken').className = 'sp-dot '+(s.token_valid ? 'green':'red');
        document.getElementById('valConn').textContent    = s.connected   ? 'Active' : 'Not configured';
        document.getElementById('valToken').textContent   = s.token_valid ? 'Valid'  : 'Expired / Missing';
        document.getElementById('valLastSync').textContent= s.last_sync;

    } catch(e) { console.error('Dashboard load failed:', e); }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
