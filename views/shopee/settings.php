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
<style>
@keyframes countdownPulse {
    0% { opacity: 1; }
    50% { opacity: 0.75; }
    100% { opacity: 1; }
}
.countdown-pulse {
    animation: countdownPulse 1.5s infinite ease-in-out;
}
</style>

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
                <div class="sp-card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fa-solid fa-shield-halved text-shopee me-2"></i>Token Status</h5>
                    <button class="btn btn-link btn-sm p-0 text-shopee" style="font-size:0.75rem; text-decoration:none; font-weight: 500;" onclick="copyAccessToken()" id="copyTokenHeaderBtn">
                        <i class="fa-solid fa-copy me-1"></i>Copy Token
                    </button>
                </div>
                <div class="sp-card-body">
                    <div class="d-flex justify-content-between mb-2 pb-2" style="border-bottom:1px solid var(--border-color)">
                        <span class="small text-secondary">Token Validity</span>
                        <span class="sp-badge" id="tokenAccessBadge">—</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2" style="border-bottom:1px solid var(--border-color)">
                        <span class="small text-secondary">Access Token</span>
                        <span class="sp-badge sp-badge-success" id="tokenActiveStatus">Active</span>
                    </div>
                    <input type="hidden" id="tokenValue">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2" style="border-bottom:1px solid var(--border-color)">
                        <span class="small text-secondary">Expires</span>
                        <span class="small text-secondary d-inline-flex align-items-center gap-2" id="tokenExpiry">—</span>
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

            <!-- Smart Sync -->
            <div class="sp-card" id="importCard" style="display:none">
                <div class="sp-card-header">
                    <h5><i class="fa-solid fa-cloud-arrow-down text-shopee me-2"></i>Smart Sync Products</h5>
                </div>
                <div class="sp-card-body">
                    <p class="small text-secondary mb-3">Fetch products and stock levels from your Shopee store using batch processing to prevent freezing. Select your sync mode below.</p>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Sync Mode</label>
                        <select class="form-select mb-2" id="syncMode">
                            <option value="quick" selected>⚡ Quick Sync (Only changed/new products)</option>
                            <option value="full">🔄 Full Sync (Re-fetch all products & variations)</option>
                            <option value="stock">📦 Stock Sync Only (Fast stock updates)</option>
                            <option value="price">💰 Price Sync Only (Fast price updates)</option>
                            <option value="mapping">🔗 Mapping Sync (Re-check unmatched products)</option>
                        </select>
                        <div class="small text-secondary" id="syncModeDesc">Fastest. Only fetches products updated on Shopee since your last sync.</div>
                    </div>

                    <div id="importStatus" class="mb-3" style="display:none">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold small" id="syncProgressLabel">Initializing Sync...</span>
                            <span class="small text-secondary" id="syncProgressCount">0 / 0</span>
                        </div>
                        <div class="sp-progress-wrap mb-2">
                            <div class="sp-progress-fill" id="syncProgressBar" style="width:0%;background:var(--shopee-primary)"></div>
                        </div>
                        <div class="alert alert-info py-2 small mb-0" id="syncLogText"><i class="fa-solid fa-spinner fa-spin me-2"></i>Starting queue...</div>
                    </div>

                    <button class="btn btn-shopee w-100" onclick="startSmartSync()" id="btnImport">
                        <i class="fa-solid fa-play me-2"></i>Start Sync
                    </button>
                </div>
            </div>

            <!-- Danger Zone: Reset Integration -->
            <div class="sp-card border-danger mt-4" id="resetCard" style="display:none">
                <div class="sp-card-header bg-danger-light" style="border-bottom:1px solid rgba(220,53,69,0.1)">
                    <h5 class="text-danger mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i>Danger Zone</h5>
                </div>
                <div class="sp-card-body">
                    <p class="small text-secondary mb-3">Wipe all cached Shopee products, variants, logs, and mapping records. This does <strong>NOT</strong> delete your API credentials. You can start completely fresh from scratch!</p>
                    <button class="btn btn-outline-danger w-100" onclick="resetIntegrationData()" id="btnResetData">
                        <i class="fa-solid fa-trash-can me-2"></i>Reset & Start Fresh
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentEnv = 'test';
let expiryTimer = null;
let tokenExpiresTime = null;
let rawTokenExpiresStr = "";

function startExpiryCountdown() {
    if (expiryTimer) {
        clearInterval(expiryTimer);
        expiryTimer = null;
    }
    
    if (!tokenExpiresTime) {
        document.getElementById('tokenExpiry').textContent = '—';
        return;
    }
    
    function updateCountdown() {
        const now = Date.now();
        const diffMs = tokenExpiresTime - now;

        if (diffMs > 0) {
            const totalSecs = Math.floor(diffMs / 1000);
            const hours = Math.floor(totalSecs / 3600);
            const mins = Math.floor((totalSecs % 3600) / 60);
            const secs = totalSecs % 60;
            
            let durationStr = "";
            if (hours > 0) {
                durationStr += `${hours}h `;
            }
            durationStr += `${mins}m ${secs}s left`;
            document.getElementById('tokenExpiry').innerHTML = `
                <span class="text-dark countdown-pulse">${durationStr}</span>
            `;
        } else {
            document.getElementById('tokenExpiry').innerHTML = `
                <span class="text-danger">Expired</span>
            `;
            clearInterval(expiryTimer);
            expiryTimer = null;
        }
    }
    
    updateCountdown(); // Run immediately
    expiryTimer = setInterval(updateCountdown, 1000);
}

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

// ── Smart Sync System ──
document.getElementById('syncMode')?.addEventListener('change', function() {
    const descs = {
        'quick': 'Fastest. Only fetches products updated on Shopee since your last sync.',
        'full': 'Slowest. Deeply re-fetches every product, variation, image, and price from Shopee.',
        'stock': 'Fast. Only updates stock levels from Shopee, ignoring name/image changes.',
        'price': 'Fast. Only updates price changes from Shopee.',
        'mapping': 'Instant. Runs auto-matching algorithms against existing unmatched products.'
    };
    document.getElementById('syncModeDesc').textContent = descs[this.value];
});

async function startSmartSync() {
    const btn = document.getElementById('btnImport');
    const status = document.getElementById('importStatus');
    const mode = document.getElementById('syncMode').value;
    const logText = document.getElementById('syncLogText');
    const progBar = document.getElementById('syncProgressBar');
    const progLbl = document.getElementById('syncProgressLabel');
    const progCnt = document.getElementById('syncProgressCount');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Sync in progress...';
    status.style.display = 'block';
    
    // Reset UI
    progBar.style.width = '0%'; progBar.style.background = 'var(--shopee-primary)';
    progLbl.textContent = 'Initializing Queue...';
    progCnt.textContent = '0 / ?';
    logText.className = 'alert alert-info py-2 small mb-0';
    logText.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Contacting Shopee API...';

    let totalItems = 0, totalRows = 0, totalInserted = 0, totalUpdated = 0, totalMatched = 0;
    let offset = 0, hasNextPage = true, queueId = null;

    try {
        // Step 1: Init Queue
        const initRes = await fetch(`${window.BASE_URL}api/shopee/sync_init.php`, {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({mode})
        });
        const initData = await initRes.json();
        if (!initData.success) throw new Error(initData.error);
        queueId = initData.queue_id;

        // Step 2: Loop chunks
        let chunksProcessed = 0;
        while (hasNextPage) {
            logText.innerHTML = `<i class="fa-solid fa-spinner fa-spin me-2"></i>Processing batch ${chunksProcessed + 1} (Offset: ${offset})...`;
            
            const res = await fetch(`${window.BASE_URL}api/shopee/fetch_products.php?offset=${offset}&mode=${mode}&queue_id=${queueId}`);
            const data = await res.json();
            
            if (data.success) {
                totalItems += data.total_items || 0;
                totalRows += data.total_rows || 0;
                totalInserted += data.inserted || 0;
                totalUpdated += data.updated || 0;
                totalMatched += data.auto_matched || 0;
                
                hasNextPage = data.has_next_page;
                offset = data.next_offset;
                chunksProcessed++;

                // Update Progress (Fake estimation based on pagination, or actual if API provides total)
                progCnt.textContent = `${offset} processed`;
                progBar.style.width = hasNextPage ? Math.min(100, (chunksProcessed * 15)) + '%' : '100%';
            } else {
                throw new Error(data.error);
            }
        }
        
        // Step 3: Complete Queue
        await fetch(`${window.BASE_URL}api/shopee/sync_complete.php`, {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({queue_id: queueId, status: 'completed'})
        });
        
        progBar.style.background = 'var(--sp-success)';
        progLbl.textContent = 'Sync Completed Successfully!';
        logText.className = 'alert alert-success py-2 small mb-0';
        logText.innerHTML = `<i class="fa-solid fa-check-circle me-2"></i><strong>Done!</strong><br>New: ${totalInserted} · Updated: ${totalUpdated}`;
        EllaToast.success(`Smart Sync (${mode}) completed successfully!`);
        
    } catch (e) {
        if (queueId) {
            fetch(`${window.BASE_URL}api/shopee/sync_complete.php`, {
                method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({queue_id: queueId, status: 'failed', error: e.message})
            });
        }
        progBar.style.background = 'var(--sp-danger)';
        progLbl.textContent = 'Sync Failed';
        logText.className = 'alert alert-danger py-2 small mb-0';
        logText.innerHTML = `<i class="fa-solid fa-xmark me-2"></i>${e.message}`;
        EllaToast.error('Sync error: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-play me-2"></i>Start Sync Again';
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
            
            // Access Token
            const tokenVal = data.access_token || '';
            document.getElementById('tokenValue').value = tokenVal;
            
            const activeStatusEl = document.getElementById('tokenActiveStatus');
            const copyBtn = document.getElementById('copyTokenHeaderBtn');
            
            if (data.token_status === 'valid' && tokenVal) {
                activeStatusEl.className = 'sp-badge sp-badge-success';
                activeStatusEl.textContent = 'Active';
                if (copyBtn) copyBtn.style.display = 'inline-block';
            } else {
                activeStatusEl.className = 'sp-badge sp-badge-danger';
                activeStatusEl.textContent = 'Inactive';
                if (copyBtn) copyBtn.style.display = 'none';
            }

            // Live Countdown Timer for Expiry
            if (data.token_expires) {
                rawTokenExpiresStr = data.token_expires;
                tokenExpiresTime = new Date(data.token_expires.replace(/-/g, "/")).getTime();
                startExpiryCountdown();
            } else {
                tokenExpiresTime = null;
                rawTokenExpiresStr = "";
                startExpiryCountdown();
            }

            document.getElementById('tokenEnvBadge').className = 'sp-badge sp-badge-' + (data.environment === 'test' ? 'info' : 'success');
            document.getElementById('tokenEnvBadge').textContent = data.environment.toUpperCase();

            // Import card and Reset card
            importCard.style.display = 'block';
            const resetCard = document.getElementById('resetCard');
            if (resetCard) resetCard.style.display = 'block';

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

function copyAccessToken() {
    const val = document.getElementById('tokenValue').value;
    if (!val) {
        EllaToast.error('No access token available to copy.');
        return;
    }
    navigator.clipboard.writeText(val);
    EllaToast.success('Access token copied to clipboard!');
}

async function resetIntegrationData() {
    if (!confirm('WARNING: Are you absolutely sure you want to delete all cached Shopee products, variants, mapping history, error logs, and sync logs? This action is permanent and cannot be undone.')) {
        return;
    }
    
    const btn = document.getElementById('btnResetData');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Wiping Data...';
    
    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/reset_integration.php`, {
            method: 'POST'
        });
        const data = await res.json();
        if (data.success) {
            EllaToast.success(data.message);
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            EllaToast.error(data.error || 'Wipe failed');
        }
    } catch (e) {
        EllaToast.error('Network error: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-trash-can me-2"></i>Reset & Start Fresh';
    }
}

document.addEventListener('DOMContentLoaded', loadStatus);
</script>

<?php require_once '../../includes/footer.php'; ?>
