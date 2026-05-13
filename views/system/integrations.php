<?php
// views/system/integrations.php - Platform Integrations Configuration
require_once '../../config/config.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die("Permission Denied");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<style>
    .integration-card {
        border: none;
        border-radius: 16px;
        overflow: hidden;
        transition: all 0.3s ease;
        background: #fff;
    }

    .integration-card:hover {
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        transform: translateY(-2px);
    }

    .integration-header {
        padding: 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 16px;
        background: linear-gradient(to right, rgba(255, 255, 255, 0), rgba(255, 255, 255, 1));
    }

    .shopee-header {
        background: linear-gradient(135deg, #FF6633 0%, #FF8A50 100%);
        color: white;
    }

    .shopee-header .text-muted {
        color: rgba(255, 255, 255, 0.8) !important;
    }

    .integration-logo {
        width: 56px;
        height: 56px;
        background: #fff;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .integration-body {
        padding: 24px;
    }

    .form-control:focus {
        border-color: #FF6633;
        box-shadow: 0 0 0 0.25rem rgba(255, 102, 51, 0.25);
    }

    .btn-shopee {
        background-color: #FF6633;
        color: white;
        border: none;
        font-weight: 600;
    }

    .btn-shopee:hover {
        background-color: #e55a2d;
        color: white;
    }
</style>

<div class="container-fluid p-3 p-lg-4">

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="fa-solid fa-plug text-primary me-2"></i>Platform Integrations
            </h4>
            <p class="text-muted mb-0 small">Connect Ella POS to external online marketplaces and APIs.</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Shopee Integration Card -->
        <div class="col-12 col-xl-6">
            <div class="card integration-card shadow-sm border-0 h-100">
                <div class="integration-header shopee-header">
                    <div class="integration-logo text-shopee" style="color: #FF6633;">
                        <i class="fa-solid fa-bag-shopping"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0">Shopee Open Platform</h5>
                        <div class="small text-muted mt-1">Official API V2 Integration</div>
                    </div>
                    <div class="ms-auto" id="shopee-status-badge">
                        <span
                            class="badge bg-dark bg-opacity-25 text-white border border-white border-opacity-25 py-2 px-3 rounded-pill">
                            <i class="fa-solid fa-spinner fa-spin me-1"></i> Checking...
                        </span>
                    </div>
                </div>

                <div class="integration-body bg-light">
                    <div class="alert alert-info border-0 shadow-sm d-flex gap-3 align-items-center bg-white rounded-3">
                        <i class="fa-solid fa-circle-info fa-2x text-info"></i>
                        <div class="small">
                            <strong>Setup Steps:</strong><br>
                            Register on the <a href="https://open.shopee.com/" target="_blank" class="fw-bold">Shopee
                                Developer Console</a> as a Seller In-house app. Once approved, paste your keys here.
                        </div>
                    </div>

                    <form id="form-shopee" class="mt-4" onsubmit="saveShopeeConfig(event)">
                        <div class="mb-3">
                            <label
                                class="form-label fw-bold text-secondary small text-uppercase d-block">Environment</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="shopee-env" id="shopee-env-sandbox"
                                    value="1" checked>
                                <label class="btn btn-outline-secondary fw-bold" for="shopee-env-sandbox">
                                    <i class="fa-solid fa-flask me-1"></i> Sandbox
                                </label>
                                <input type="radio" class="btn-check" name="shopee-env" id="shopee-env-live" value="0">
                                <label class="btn btn-outline-success fw-bold" for="shopee-env-live">
                                    <i class="fa-solid fa-tower-broadcast me-1"></i> Live Production
                                </label>
                            </div>
                            <div class="form-text small">Use Sandbox for testing without real orders.</div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-dark fw-bold px-4">
                                <i class="fa-solid fa-save me-1"></i> Save Keys
                            </button>
                            <button type="button" class="btn btn-shopee fw-bold flex-grow-1" id="btn-auth-shopee"
                                onclick="authorizeShopee()" disabled>
                                <i class="fa-solid fa-link me-1"></i> Authorize Shop
                            </button>
                        </div>

                        <div class="mt-3 d-none" id="test-connection-section">
                            <button type="button" class="btn btn-outline-primary fw-bold w-100 py-2"
                                id="btn-test-shopee" onclick="testShopeeConnection()">
                                <i class="fa-solid fa-vial me-1"></i> Test Connection
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Website Sync Integration Card -->
        <div class="col-12 col-xl-6">
            <div class="card integration-card shadow-sm border-0 h-100">
                <div class="integration-header bg-dark text-white">
                    <div class="integration-logo text-dark">
                        <i class="fa-solid fa-globe"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0">Website Sync (React/Node.js)</h5>
                        <div class="small text-white-50 mt-1">External Catalog API</div>
                    </div>
                    <div class="ms-auto" id="website-status-badge">
                        <span
                            class="badge bg-white bg-opacity-25 text-white border border-white border-opacity-25 py-2 px-3 rounded-pill">
                            <i class="fa-solid fa-circle-dot me-1"></i> Active
                        </span>
                    </div>
                </div>

                <div class="integration-body bg-light">
                    <div
                        class="alert alert-primary border-0 shadow-sm d-flex gap-3 align-items-center bg-white rounded-3">
                        <i class="fa-solid fa-cloud-arrow-up fa-2x text-primary"></i>
                        <div class="small">
                            <strong>External Sync:</strong><br>
                            Use this API Key in your Node.js/Vercel website to fetch product data, prices, and stock
                            levels in real-time.
                        </div>
                    </div>

                    <form id="form-website" class="mt-4" onsubmit="saveWebsiteConfig(event)">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small text-uppercase">API Endpoint</label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-white" id="website-api-url" readonly
                                    value="<?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/api/external/sync_catalog.php'; ?>">
                                <button class="btn btn-outline-secondary" type="button"
                                    onclick="copyToClipboard('website-api-url')">
                                    <i class="fa-solid fa-copy"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small text-uppercase">API Secret Key
                                (x-api-key)</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="website-api-key"
                                    placeholder="Enter a secure sync key">
                                <button class="btn btn-outline-secondary" type="button"
                                    onclick="togglePassword('website-api-key')">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-secondary" type="button" onclick="generateApiKey()">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                </button>
                            </div>
                            <div class="form-text small">Keep this key secret. Your website must send this in the
                                <code>x-api-key</code> header.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary small text-uppercase">Live Sync Webhook
                                URL</label>
                            <div class="input-group">
                                <input type="url" class="form-control" id="website-webhook-url"
                                    placeholder="https://your-website.com/api/sync/webhook">
                            </div>
                            <div class="form-text small">The POS will send stock updates to this URL instantly whenever
                                a sale is made.</div>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="website-active" checked>
                            <label class="form-check-label fw-bold text-secondary small text-uppercase"
                                for="website-active">Enable External Sync</label>
                        </div>

                        <button type="submit" class="btn btn-primary fw-bold px-4 w-100 py-2 shadow-sm"
                            id="btn-save-website">
                            <i class="fa-solid fa-save me-1"></i> Save Website Config
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', loadIntegrations);

    async function loadIntegrations() {
        try {
            const res = await fetch('../../api/system/get_integrations.php');
            const data = await res.json();

            if (data.success) {
                // Load Shopee
                if (data.data.shopee) {
                    const sh = data.data.shopee;
                    if (sh.partner_id) document.getElementById('shopee-partner-id').value = sh.partner_id;
                    if (sh.partner_key) document.getElementById('shopee-partner-key').value = sh.partner_key;
                    if (sh.shop_id) document.getElementById('shopee-shop-id').value = sh.shop_id;

                    if (sh.is_test == "0") {
                        document.getElementById('shopee-env-live').checked = true;
                    } else {
                        document.getElementById('shopee-env-sandbox').checked = true;
                    }

                    const authBtn = document.getElementById('btn-auth-shopee');
                    const badge = document.getElementById('shopee-status-badge');
                    const testSection = document.getElementById('test-connection-section');

                    if (sh.partner_id && sh.partner_key) {
                        authBtn.disabled = false;
                    }

                    if (sh.is_active) {
                        testSection.classList.remove('d-none');
                        if (sh.is_expired) {
                            badge.innerHTML = `<span class="badge bg-warning text-dark border border-warning py-2 px-3 rounded-pill fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i> Token Expired</span>`;
                        } else {
                            badge.innerHTML = `<span class="badge bg-white text-success fw-bold py-2 px-3 rounded-pill shadow-sm"><i class="fa-solid fa-circle-check me-1"></i> Connected</span>`;
                            authBtn.innerHTML = '<i class="fa-solid fa-rotate me-1"></i> Re-Authorize';
                        }
                    } else {
                        badge.innerHTML = `<span class="badge bg-dark bg-opacity-25 text-white border border-white border-opacity-25 py-2 px-3 rounded-pill fw-bold"><i class="fa-solid fa-link-slash me-1"></i> Disconnected</span>`;
                        testSection.classList.add('d-none');
                    }
                }

                // Load Website
                if (data.data.website) {
                    const ws = data.data.website;
                    if (ws.partner_key) document.getElementById('website-api-key').value = ws.partner_key;
                    if (ws.webhook_url) document.getElementById('website-webhook-url').value = ws.webhook_url;
                    document.getElementById('website-active').checked = ws.is_active;

                    const wsBadge = document.getElementById('website-status-badge');
                    if (ws.is_active) {
                        wsBadge.innerHTML = `<span class="badge bg-white text-success fw-bold py-2 px-3 rounded-pill shadow-sm"><i class="fa-solid fa-circle-check me-1"></i> Active</span>`;
                    } else {
                        wsBadge.innerHTML = `<span class="badge bg-dark bg-opacity-25 text-white border border-white border-opacity-25 py-2 px-3 rounded-pill fw-bold"><i class="fa-solid fa-circle-pause me-1"></i> Inactive</span>`;
                    }
                }
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function saveShopeeConfig(e) {
        e.preventDefault();

        const btn = e.submitter;
        const ogContent = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        btn.disabled = true;

        const payload = {
            platform: 'shopee',
            partner_id: document.getElementById('shopee-partner-id').value,
            partner_key: document.getElementById('shopee-partner-key').value,
            shop_id: document.getElementById('shopee-shop-id').value,
            is_test: document.querySelector('input[name="shopee-env"]:checked').value
        };

        try {
            const res = await fetch('../../api/system/save_integrations.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (data.success) {
                alert('Shopee credentials saved!');
                document.getElementById('btn-auth-shopee').disabled = false;
                loadIntegrations();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Request failed');
        } finally {
            btn.innerHTML = ogContent;
            btn.disabled = false;
        }
    }

    function authorizeShopee() {
        // Redirect to our backend which generates the Shopee URL
        window.location.href = '../../api/system/shopee_auth_redirect.php';
    }

    async function saveWebsiteConfig(e) {
        e.preventDefault();

        const btn = document.getElementById('btn-save-website');
        const ogContent = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;

        const payload = {
            platform: 'website',
            partner_id: 'website_sync',
            partner_key: document.getElementById('website-api-key').value,
            webhook_url: document.getElementById('website-webhook-url').value,
            is_active: document.getElementById('website-active').checked ? 1 : 0
        };

        try {
            const res = await fetch('../../api/system/save_integrations.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (data.success) {
                if (typeof EllaToast !== 'undefined') {
                    EllaToast.success('Website sync configuration updated!');
                } else {
                    alert('Website sync configuration updated!');
                }
                loadIntegrations();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Request failed');
        } finally {
            btn.innerHTML = ogContent;
            btn.disabled = false;
        }
    }

    function togglePassword(id) {
        const input = document.getElementById(id);
        input.type = input.type === 'password' ? 'text' : 'password';
    }

    function generateApiKey() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let key = 'ella_';
        for (let i = 0; i < 24; i++) {
            key += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('website-api-key').value = key;
        document.getElementById('website-api-key').type = 'text';
    }

    function copyToClipboard(id) {
        const input = document.getElementById(id);
        input.select();
        document.execCommand('copy');
        if (typeof EllaToast !== 'undefined') {
            EllaToast.success('Copied to clipboard!');
        } else {
            alert('Copied to clipboard!');
        }
    }

    async function testShopeeConnection() {
        const btn = document.getElementById('btn-test-shopee');
        const ogContent = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testing Connection...';
        btn.disabled = true;

        try {
            const res = await fetch('../../api/system/test_shopee.php');
            const data = await res.json();

            if (data.success) {
                const shop = data.data;
                const msg = `Success! Connected to Shopee.\nShop: ${shop.shop_name}\nRegion: ${shop.region}`;
                alert(msg); // Will replace with EllaToast if available
                if (typeof EllaToast !== 'undefined') {
                    EllaToast.success('Shopee Connection Verified!');
                }
            } else {
                alert('Testing Failed: ' + data.message);
                if (typeof EllaToast !== 'undefined') {
                    EllaToast.error(data.message);
                }
            }
        } catch (err) {
            alert('Failed to reach server');
        } finally {
            btn.innerHTML = ogContent;
            btn.disabled = false;
        }
    }
</script>

<?php require_once '../../includes/footer.php'; ?>