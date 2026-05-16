<?php
// views/shopee/settings.php — Shopee Sync Settings (REAL)
$page_title = 'Shopee Sync — Settings';
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    denyAccess("You do not have permission to access Shopee Sync.");
}
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Check if we just came back from OAuth
$authSuccess = isset($_GET['auth']) && $_GET['auth'] === 'success';
$authShopId  = $_GET['shop_id'] ?? '';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/shopee-sync.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shopee-sync.css') ?>">

<div class="sp-page sp-animate">
    <div class="sp-breadcrumb">
        <a href="<?= BASE_URL ?>views/shopee/index.php">Shopee Sync</a>
        <i class="fa-solid fa-chevron-right" style="font-size:0.6rem"></i>
        <span>Settings</span>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="sp-title mb-0"><i class="fa-solid fa-gear text-shopee me-2"></i>Settings</h1>
            <p class="sp-subtitle mb-0">Configure your Shopee API credentials and connect your shop</p>
        </div>
    </div>

    <?php if ($authSuccess): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="fa-solid fa-circle-check fs-5"></i>
        <div><strong>Shop authorized successfully!</strong> Shop ID: <?= htmlspecialchars($authShopId) ?> — You can now import products.</div>
    </div>
    <?php endif; ?>

    <!-- Status Banner -->
    <div id="statusBanner" class="sp-card mb-4" style="display:none">
        <div class="sp-card-body d-flex align-items-center gap-3">
            <div class="sp-stat-icon" id="statusIcon" style="background:var(--sp-success-bg);color:var(--sp-success)">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="flex-grow-1">
                <div class="fw-bold" id="statusTitle">Not Configured</div>
                <div class="small text-secondary" id="statusSub"></div>
            </div>
            <span class="sp-badge" id="statusBadge"></span>
        </div>
    </div>

    <div class="row g-4">
        <!-- LEFT: Credentials -->
        <div class="col-lg-6">
            <div class="sp-card mb-4">
                <div class="sp-card-header">
                    <h5><i class="fa-solid fa-key text-shopee me-2"></i>API Credentials</h5>
                </div>
                <div class="sp-card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Environment</label>
                        <div class="d-flex gap-2">
                            <button class="sp-pill active" id="envTest" onclick="setEnv('test')">
                                <i class="fa-solid fa-flask me-1"></i>Test
                            </button>
                            <button class="sp-pill" id="envLive" onclick="setEnv('live')">
                                <i class="fa-solid fa-globe me-1"></i>Live
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Partner ID</label>
                        <input type="text" class="form-control" id="partnerId" placeholder="e.g. 1234567">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Partner Key (Secret)</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="partnerKey" placeholder="Your partner secret key">
                            <button class="btn btn-outline-secondary" onclick="toggleKeyVisibility()">
                                <i class="fa-solid fa-eye" id="keyEyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Shop Region</label>
                        <select class="form-select" id="shopRegion">
                            <option value="PH" selected>Philippines (PH)</option>
                            <option value="SG">Singapore (SG)</option>
                            <option value="MY">Malaysia (MY)</option>
                            <option value="TH">Thailand (TH)</option>
                            <option value="ID">Indonesia (ID)</option>
                            <option value="VN">Vietnam (VN)</option>
                        </select>
                    </div>

                    <button class="btn btn-shopee w-100" onclick="saveCredentials()" id="btnSave">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Save Credentials
                    </button>
                </div>
            </div>

            <!-- Inventory Rules -->
            <div class="sp-card">
                <div class="sp-card-header">
                    <h5><i class="fa-solid fa-clipboard-list text-shopee me-2"></i>Inventory Rules</h5>
                </div>
                <div class="sp-card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-3" style="border-bottom:1px solid var(--border-color)">
                        <div>
                            <div class="fw-bold">Automatic Stock Deduction</div>
                            <div class="small text-secondary">Deduct from POS when Shopee order is placed</div>
                        </div>
                        <label class="sp-toggle"><input type="checkbox" checked><span class="sp-toggle-slider"></span></label>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-3" style="border-bottom:1px solid var(--border-color)">
                        <div>
                            <div class="fw-bold">Cancelled Order Restore</div>
                            <div class="small text-secondary">Restore stock when order is cancelled</div>
                        </div>
                        <label class="sp-toggle"><input type="checkbox" checked><span class="sp-toggle-slider"></span></label>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-bold">Return Order Restore</div>
                            <div class="small text-secondary">Restore stock when order is returned</div>
                        </div>
                        <label class="sp-toggle"><input type="checkbox" checked><span class="sp-toggle-slider"></span></label>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Connection & Authorization -->
        <div class="col-lg-6">
            <div class="sp-card mb-4">
                <div class="sp-card-header">
                    <h5><i class="fa-solid fa-plug text-shopee me-2"></i>Shop Authorization</h5>
                </div>
                <div class="sp-card-body">
                    <div id="authSection">
                        <!-- Dynamic content from JS -->
                    </div>
                </div>
            </div>

            <!-- Token Info -->
            <div class="sp-card mb-4" id="tokenCard" style="display:none">
                <div class="sp-card-header">
                    <h5><i class="fa-solid fa-shield-halved text-shopee me-2"></i>Token Status</h5>
                </div>
                <div class="sp-card-body">
                    <div class="d-flex justify-content-between mb-2 pb-2" style="border-bottom:1px solid var(--border-color)">
                        <span class="small text-secondary">Access Token</span>
                        <span class="sp-badge" id="tokenAccessBadge">—</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 pb-2" style="border-bottom:1px solid var(--border-color)">
                        <span class="small text-secondary">Expires</span>
                        <span class="small" id="tokenExpiry">—</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="small text-secondary">Environment</span>
                        <span class="sp-badge" id="tokenEnvBadge">—</span>
                    </div>
                    <button class="btn btn-outline-shopee w-100" onclick="refreshTokens()">
                        <i class="fa-solid fa-rotate me-2"></i>Refresh Tokens
                    </button>
                </div>
            </div>

            <!-- Quick Test -->
            <div class="sp-card" id="importCard" style="display:none">
                <div class="sp-card-header">
                    <h5><i class="fa-solid fa-cloud-arrow-down text-shopee me-2"></i>Import Products</h5>
                </div>
                <div class="sp-card-body">
                    <p class="small text-secondary mb-3">Pull all products and stock levels from your Shopee shop. This will auto-match products to your POS inventory by SKU.</p>
                    <div id="importStatus" class="mb-3" style="display:none"></div>
                    <button class="btn btn-shopee w-100" onclick="importProducts()" id="btnImport">
                        <i class="fa-solid fa-cloud-arrow-down me-2"></i>Import All Products from Shopee
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentEnv = 'test';

function setEnv(env) {
    currentEnv = env;
    document.getElementById('envTest').classList.toggle('active', env === 'test');
    document.getElementById('envLive').classList.toggle('active', env === 'live');
}

function toggleKeyVisibility() {
    const inp = document.getElementById('partnerKey');
    const icon = document.getElementById('keyEyeIcon');
    if (inp.type === 'password') { inp.type = 'text'; icon.className = 'fa-solid fa-eye-slash'; }
    else { inp.type = 'password'; icon.className = 'fa-solid fa-eye'; }
}

// ── Save Credentials ──
async function saveCredentials() {
    const partnerId  = document.getElementById('partnerId').value.trim();
    const partnerKey = document.getElementById('partnerKey').value.trim();
    const shopRegion = document.getElementById('shopRegion').value;

    if (!partnerId) {
        EllaToast.error('Partner ID is required');
        return;
    }

    const btn = document.getElementById('btnSave');
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Saving...';

    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/save_config.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ environment: currentEnv, partner_id: partnerId, partner_key: partnerKey, shop_region: shopRegion })
        });
        const data = await res.json();
        if (data.success) {
            EllaToast.success(data.message);
            loadStatus();
        } else {
            EllaToast.error(data.error);
        }
    } catch (e) {
        EllaToast.error('Network error: ' + e.message);
    } finally {
        btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-floppy-disk me-2"></i>Save Credentials';
    }
}

// ── Authorize Shop ──
async function authorizeShop() {
    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/auth.php`);
        const data = await res.json();
        if (data.success) {
            EllaToast.success('Redirecting to Shopee authorization...');
            // Open in same window so callback can redirect back
            window.location.href = data.auth_url;
        } else {
            EllaToast.error(data.error);
        }
    } catch (e) {
        EllaToast.error('Network error: ' + e.message);
    }
}

// ── Import Products ──
async function importProducts() {
    const btn = document.getElementById('btnImport');
    const status = document.getElementById('importStatus');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Importing from Shopee...';
    status.style.display = 'block';
    
    let totalItems = 0;
    let totalRows = 0;
    let totalInserted = 0;
    let totalUpdated = 0;
    let totalMatched = 0;
    let offset = 0;
    let hasNextPage = true;

    try {
        while (hasNextPage) {
            status.innerHTML = `<div class="alert alert-info py-2 small"><i class="fa-solid fa-spinner fa-spin me-2"></i>Fetching products from Shopee API (Page Offset: ${offset})... This may take a moment.</div>`;
            
            const res = await fetch(`${window.BASE_URL}api/shopee/fetch_products.php?offset=${offset}`);
            const data = await res.json();
            
            if (data.success) {
                totalItems += data.total_items || 0;
                totalRows += data.total_rows || 0;
                totalInserted += data.inserted || 0;
                totalUpdated += data.updated || 0;
                totalMatched += data.auto_matched || 0;
                
                hasNextPage = data.has_next_page;
                offset = data.next_offset;
            } else {
                status.innerHTML = `<div class="alert alert-danger py-2 small"><i class="fa-solid fa-xmark me-2"></i>${data.error}</div>`;
                EllaToast.error(data.error);
                hasNextPage = false;
                break;
            }
        }
        
        if (totalItems > 0 || !hasNextPage) {
            status.innerHTML = `<div class="alert alert-success py-2 small">
                <i class="fa-solid fa-check-circle me-2"></i>
                <strong>Import complete!</strong><br>
                Total items: ${totalItems} · Rows: ${totalRows}<br>
                New: ${totalInserted} · Updated: ${totalUpdated} · Auto-matched: ${totalMatched}
            </div>`;
            EllaToast.success(`Imported ${totalRows} products from Shopee!`);
        }
        
    } catch (e) {
        status.innerHTML = `<div class="alert alert-danger py-2 small"><i class="fa-solid fa-xmark me-2"></i>${e.message}</div>`;
        EllaToast.error('Network error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-down me-2"></i>Import All Products from Shopee';
    }
}

// ── Load Current Status ──
async function loadStatus() {
    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/get_config.php`);
        const data = await res.json();

        const banner = document.getElementById('statusBanner');
        const authSection = document.getElementById('authSection');
        const tokenCard = document.getElementById('tokenCard');
        const importCard = document.getElementById('importCard');

        if (!data.success || !data.configured) {
            banner.style.display = 'block';
            document.getElementById('statusIcon').style.background = 'var(--sp-warning-bg)';
            document.getElementById('statusIcon').style.color = 'var(--sp-warning)';
            document.getElementById('statusIcon').innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
            document.getElementById('statusTitle').textContent = 'Not Configured';
            document.getElementById('statusSub').textContent = 'Enter your Shopee Partner credentials to get started.';
            document.getElementById('statusBadge').className = 'sp-badge sp-badge-warning';
            document.getElementById('statusBadge').innerHTML = '<i class="fa-solid fa-gear"></i> Setup Required';

            authSection.innerHTML = '<div class="sp-empty"><i class="fa-solid fa-plug d-block"></i><h5>No Credentials</h5><p>Save your Partner ID and Key first.</p></div>';
            return;
        }

        // Configured — show status
        banner.style.display = 'block';
        document.getElementById('partnerId').value = data.partner_id;
        if (data.partner_key) {
            document.getElementById('partnerKey').value = data.partner_key;
        }
        if (data.shop_region) {
            document.getElementById('shopRegion').value = data.shop_region;
        }
        setEnv(data.environment);

        if (data.authorized) {
            document.getElementById('statusIcon').style.background = 'var(--sp-success-bg)';
            document.getElementById('statusIcon').style.color = 'var(--sp-success)';
            document.getElementById('statusIcon').innerHTML = '<i class="fa-solid fa-circle-check"></i>';
            document.getElementById('statusTitle').textContent = 'Connected & Authorized';
            document.getElementById('statusSub').textContent = `Shop ID: ${data.shop_id} · ${data.environment.toUpperCase()} mode · ${data.products_total} products imported`;
            document.getElementById('statusBadge').className = 'sp-badge sp-badge-success';
            document.getElementById('statusBadge').innerHTML = '<i class="fa-solid fa-circle" style="font-size:6px"></i> Connected';

            authSection.innerHTML = `
                <div class="d-flex align-items-center gap-3 p-3 rounded mb-3" style="background:var(--bg-body);border:1px solid var(--border-color)">
                    <div class="sp-stat-icon" style="background:var(--shopee-light);color:var(--shopee-primary)"><i class="fa-solid fa-shop"></i></div>
                    <div class="flex-grow-1">
                        <div class="fw-bold">Shop ID: ${data.shop_id}</div>
                        <div class="small text-success"><i class="fa-solid fa-circle me-1" style="font-size:6px"></i>Authorized</div>
                    </div>
                    <span class="sp-badge sp-badge-${data.environment === 'test' ? 'info' : 'success'}">${data.environment.toUpperCase()}</span>
                </div>
                <div class="small text-secondary">
                    <strong>Mapped:</strong> ${data.products_mapped} · <strong>Unmapped:</strong> ${data.products_unmapped} · <strong>Total:</strong> ${data.products_total}
                </div>
            `;

            // Token card
            tokenCard.style.display = 'block';
            document.getElementById('tokenAccessBadge').className = 'sp-badge sp-badge-' + (data.token_status === 'valid' ? 'success' : 'danger');
            document.getElementById('tokenAccessBadge').textContent = data.token_status === 'valid' ? 'Valid' : 'Expired';
            document.getElementById('tokenExpiry').textContent = data.token_expires ? data.token_expires + ` (${data.token_days_left}d left)` : '—';
            document.getElementById('tokenEnvBadge').className = 'sp-badge sp-badge-' + (data.environment === 'test' ? 'info' : 'success');
            document.getElementById('tokenEnvBadge').textContent = data.environment.toUpperCase();

            // Import card
            importCard.style.display = 'block';

        } else {
            document.getElementById('statusIcon').style.background = 'var(--sp-info-bg)';
            document.getElementById('statusIcon').style.color = 'var(--sp-info)';
            document.getElementById('statusIcon').innerHTML = '<i class="fa-solid fa-key"></i>';
            document.getElementById('statusTitle').textContent = 'Credentials Saved — Authorization Needed';
            document.getElementById('statusSub').textContent = 'Click "Authorize Shop" to connect your Shopee store.';
            document.getElementById('statusBadge').className = 'sp-badge sp-badge-info';
            document.getElementById('statusBadge').innerHTML = 'Awaiting Auth';

            authSection.innerHTML = `
                <div class="text-center py-3">
                    <i class="fa-solid fa-shop-lock text-secondary mb-3 d-block" style="font-size:2.5rem;opacity:0.4"></i>
                    <h5 class="fw-bold">Authorize Your Shop</h5>
                    <p class="small text-secondary mb-3">You'll be redirected to Shopee to grant access. After authorizing, you'll be sent back here automatically.</p>
                    <button class="btn btn-shopee" onclick="authorizeShop()">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>Authorize with Shopee (${data.environment.toUpperCase()})
                    </button>
                </div>
            `;
        }

    } catch (e) {
        console.error('Failed to load Shopee config:', e);
    }
}

async function refreshTokens() {
    const btn = event ? event.currentTarget : null;
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Refreshing...';
    }

    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/refresh_token.php`);
        const data = await res.json();
        
        if (data.success) {
            EllaToast.success(data.message);
            loadStatus(); // Reload the status to show new expiration
        } else {
            EllaToast.error(data.error || 'Failed to refresh tokens');
        }
    } catch (e) {
        EllaToast.error('Network error: ' + e.message);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-rotate me-2"></i>Refresh Tokens';
        }
    }
}

document.addEventListener('DOMContentLoaded', loadStatus);
</script>

<?php require_once '../../includes/footer.php'; ?>
