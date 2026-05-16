<?php
// views/shopee/mapping.php — Product Mapping (Shopee ↔ POS)
$page_title = 'Shopee Sync — Product Mapping';
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
<style>
    .map-panel { min-height: 400px; max-height: 500px; overflow-y: auto; }
    .map-item { padding:0.85rem 1rem; border:1px solid var(--border-color); border-radius:var(--sp-radius-sm); margin-bottom:0.5rem; cursor:pointer; transition:all 0.2s; display:flex; align-items:center; gap:0.75rem; }
    .map-item:hover { border-color:var(--shopee-primary); background:var(--shopee-light); }
    .map-item.selected { border-color:var(--shopee-primary); background:var(--shopee-light); box-shadow:0 0 0 2px rgba(238,77,45,0.2); }
    .map-arrow { display:flex; align-items:center; justify-content:center; font-size:1.5rem; color:var(--shopee-primary); }
    .sku-type-badge { font-size:0.6rem; padding:0.15rem 0.4rem; border-radius:4px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; }
    .sku-type-variation { background:var(--sp-info-bg); color:var(--sp-info); }
    .sku-type-parent { background:var(--sp-warning-bg); color:var(--sp-warning); }
    .match-rule-box { background:var(--bg-body); border:1px solid var(--border-color); border-radius:var(--sp-radius-md); padding:1rem 1.25rem; margin-bottom:1.5rem; }
    .match-rule-box code { background:var(--shopee-light); color:var(--shopee-primary); padding:0.15rem 0.4rem; border-radius:4px; font-weight:600; }
</style>

<div class="sp-page sp-animate">
    <div class="sp-breadcrumb">
        <a href="<?= BASE_URL ?>views/shopee/index.php">Shopee Sync</a>
        <i class="fa-solid fa-chevron-right" style="font-size:0.6rem"></i>
        <span>Product Mapping</span>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="sp-title mb-0"><i class="fa-solid fa-link text-shopee me-2"></i>Product Mapping</h1>
            <p class="sp-subtitle mb-0">Map Shopee products to your POS SKUs — uses <strong>Variation SKU</strong> or <strong>Parent SKU</strong></p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-shopee" onclick="autoMatch()"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Auto-Match</button>
            <button class="btn btn-shopee" onclick="saveMappings(this)">
                <i class="fa-solid fa-cloud-arrow-up me-2"></i>Save Mappings
            </button>
        </div>
    </div>

    <div class="sp-test-banner bg-success-subtle text-success border-success">
        <i class="fa-solid fa-link"></i>
        <div class="flex-grow-1">Connecting actual POS inventory with Shopee products.</div>
    </div>

    <!-- Matching Rule Explainer -->
    <div class="match-rule-box">
        <div class="d-flex align-items-start gap-3">
            <i class="fa-solid fa-circle-info text-shopee mt-1"></i>
            <div>
                <div class="fw-bold mb-1">SKU Matching Rules</div>
                <div class="small text-secondary">
                    <strong>Case A:</strong> Shopee product <strong>with variations</strong> → match using <code>Variation SKU</code> → POS SKU<br>
                    <strong>Case B:</strong> Shopee product <strong>without variations</strong> → match using <code>Parent SKU</code> → POS SKU
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="sp-stat-card accent-shopee">
                <div class="sp-stat-icon" style="background:var(--shopee-light);color:var(--shopee-primary)"><i class="fa-solid fa-bag-shopping"></i></div>
                <div><div class="sp-stat-label">Shopee Items</div><div class="sp-stat-value" id="totalCount">0</div></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="sp-stat-card accent-success">
                <div class="sp-stat-icon" style="background:var(--sp-success-bg);color:var(--sp-success)"><i class="fa-solid fa-link"></i></div>
                <div><div class="sp-stat-label">Matched</div><div class="sp-stat-value" id="mappedCount">0</div></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="sp-stat-card accent-warning">
                <div class="sp-stat-icon" style="background:var(--sp-warning-bg);color:var(--sp-warning)"><i class="fa-solid fa-link-slash"></i></div>
                <div><div class="sp-stat-label">Unmatched</div><div class="sp-stat-value" id="unmappedCount">0</div></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="sp-stat-card accent-danger">
                <div class="sp-stat-icon" style="background:var(--sp-danger-bg);color:var(--sp-danger)"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div><div class="sp-stat-label">Issues</div><div class="sp-stat-value" id="issueCount">0</div></div>
            </div>
        </div>
    </div>

    <!-- Filter Pills -->
    <div class="sp-card mb-4">
        <div class="sp-card-body d-flex flex-wrap align-items-center gap-3" style="padding:0.85rem 1.25rem">
            <div class="sp-search flex-grow-1" style="max-width:320px">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="mapSearch" placeholder="Search by product name or SKU..." oninput="renderAll()">
            </div>
            <div class="sp-filter-pills">
                <button class="sp-pill active" onclick="setFilter('all',this)">All</button>
                <button class="sp-pill" onclick="setFilter('mapped',this)"><i class="fa-solid fa-link me-1"></i>Matched</button>
                <button class="sp-pill" onclick="setFilter('unmapped',this)"><i class="fa-solid fa-link-slash me-1"></i>Unmatched</button>
                <button class="sp-pill" onclick="setFilter('has_variation',this)">With Variations</button>
                <button class="sp-pill" onclick="setFilter('no_variation',this)">No Variations</button>
                <button class="sp-pill" onclick="setFilter('issues',this)"><i class="fa-solid fa-triangle-exclamation me-1"></i>Issues</button>
            </div>
        </div>
    </div>

    <!-- Mapping Table -->
    <div class="sp-card mb-4">
        <div class="sp-card-header">
            <h5><i class="fa-solid fa-table text-shopee me-2"></i>Product Mapping Table</h5>
        </div>
        <div class="sp-card-body p-0 sp-table-wrap">
            <table class="sp-table">
                <thead>
                    <tr>
                        <th>Shopee Product</th>
                        <th>Variation</th>
                        <th>Parent SKU</th>
                        <th>Variation SKU</th>
                        <th>Match Key</th>
                        <th class="text-center"><i class="fa-solid fa-arrows-left-right"></i></th>
                        <th>POS SKU Match</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="mapTableBody"></tbody>
            </table>
        </div>
    </div>

    <!-- Manual Mapping Panel -->
    <div class="sp-card">
        <div class="sp-card-header">
            <h5><i class="fa-solid fa-hand-pointer text-shopee me-2"></i>Manual Mapping</h5>
            <span class="sp-badge sp-badge-info">Select one from each side, then click "Link"</span>
        </div>
        <div class="sp-card-body">
            <div class="row g-4">
                <div class="col-md-5">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-bag-shopping text-shopee me-2"></i>Unmatched Shopee Items</h6>
                    <div class="sp-search mb-3">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" id="shopeeSearch" placeholder="Search Shopee items..." oninput="renderPanels()">
                    </div>
                    <div id="shopeePanel" class="map-panel"></div>
                </div>
                <div class="col-md-2 d-flex align-items-center justify-content-center">
                    <div class="text-center">
                        <div class="map-arrow mb-3"><i class="fa-solid fa-arrows-left-right"></i></div>
                        <button class="btn btn-shopee" id="linkBtn" disabled onclick="linkSelected()">
                            <i class="fa-solid fa-link me-2"></i>Link
                        </button>
                    </div>
                </div>
                <div class="col-md-5">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--sp-info)"></i>Available POS Products</h6>
                    <div class="sp-search mb-3">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" id="posSearch" placeholder="Search POS products..." oninput="renderPanels()">
                    </div>
                    <div id="posPanel" class="map-panel"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * ══════════════════════════════════════════════
 *  SHOPEE PRODUCT MAPPING — CORRECT LOGIC
 * ══════════════════════════════════════════════
 *
 * RULE:
 *   - Product WITH variations  → use variationSku to match POS SKU
 *   - Product WITHOUT variations → use parentSku to match POS SKU
 *
 * The "matchKey" is the SKU used for matching:
 *   matchKey = hasVariation ? variationSku : parentSku
 */

<?php
$db = new Database();
$conn = $db->getConnection();

$shopeeStmt = $conn->query("SELECT * FROM shopee_product_mappings");
$shopeeList = $shopeeStmt->fetchAll(PDO::FETCH_ASSOC);

$posStmt = $conn->query("
    SELECT 
        v.variation_id as id, 
        CONCAT(p.product_name, IF(v.variation_name != '', CONCAT(' (', v.variation_name, ')'), '')) as name, 
        v.sku 
    FROM product_variations v
    JOIN products p ON v.product_id = p.product_id
    WHERE v.status = 'active'
    ORDER BY p.product_name ASC
");
$posList = $posStmt->fetchAll(PDO::FETCH_ASSOC);

$usedPosIds = array_filter(array_column($shopeeList, 'pos_product_id'));

$shopeeItemsJson = [];
foreach ($shopeeList as $item) {
    $shopeeItemsJson[] = [
        'id'           => (int) $item['id'],
        'itemId'       => (int) $item['shopee_item_id'],
        'name'         => $item['shopee_product_name'],
        'variation'    => $item['shopee_variation_name'],
        'parentSku'    => $item['shopee_parent_sku'],
        'variationSku' => $item['shopee_variation_sku'],
        'modelId'      => $item['shopee_model_id'] ? 'SP-'.$item['shopee_model_id'] : 'SP-'.$item['shopee_item_id'],
        'hasVariation' => (bool) $item['has_variation'],
        'mapped'       => in_array($item['mapping_status'], ['auto','manual']),
        'posId'        => $item['pos_product_id'] ? (int) $item['pos_product_id'] : null,
        'mapStatus'    => $item['mapping_status']
    ];
}

$posItemsJson = [];
foreach ($posList as $pos) {
    $posItemsJson[] = [
        'id'   => (int) $pos['id'],
        'name' => $pos['name'],
        'sku'  => $pos['sku'],
        'used' => in_array($pos['id'], $usedPosIds)
    ];
}
?>
const SHOPEE_ITEMS = <?= json_encode($shopeeItemsJson) ?>;
const POS_ITEMS = <?= json_encode($posItemsJson) ?>;

let selectedShopee = null, selectedPos = null, activeFilter = 'all';

// ── Get the SKU used for matching ──
function getMatchKey(item) {
    if (item.hasVariation) return item.variationSku || '';
    return item.parentSku || '';
}

// ── Auto-match: exact SKU match ONLY ──
function autoMatch() {
    let count = 0;
    SHOPEE_ITEMS.filter(s => !s.mapped && s.mapStatus !== 'missing_sku').forEach(s => {
        const key = getMatchKey(s);
        if (!key) return;
        // Check for duplicate POS SKUs
        const matches = POS_ITEMS.filter(p => !p.used && p.sku.toLowerCase() === key.toLowerCase());
        if (matches.length === 1) {
            s.mapped = true; s.posId = matches[0].id; s.mapStatus = 'auto';
            matches[0].used = true; count++;
        } else if (matches.length > 1) {
            s.mapStatus = 'duplicate';
        }
    });
    updateCounts(); renderAll();
    if (typeof EllaToast !== 'undefined') EllaToast.success(`Auto-matched ${count} product(s) using SKU matching`);
}

function setFilter(f, btn) {
    activeFilter = f;
    document.querySelectorAll('.sp-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    renderAll();
}

// ── Render Mapping Table ──
function renderMappingTable() {
    const search = (document.getElementById('mapSearch')?.value || '').toLowerCase();
    const body = document.getElementById('mapTableBody');

    let items = SHOPEE_ITEMS.filter(s => {
        if (search) {
            const key = getMatchKey(s);
            if (!s.name.toLowerCase().includes(search) && !key.toLowerCase().includes(search) && !(s.parentSku||'').toLowerCase().includes(search)) return false;
        }
        if (activeFilter === 'mapped' && !s.mapped) return false;
        if (activeFilter === 'unmapped' && s.mapped) return false;
        if (activeFilter === 'has_variation' && !s.hasVariation) return false;
        if (activeFilter === 'no_variation' && s.hasVariation) return false;
        if (activeFilter === 'issues' && !['missing_sku','duplicate'].includes(s.mapStatus)) return false;
        return true;
    });

    if (!items.length) {
        body.innerHTML = '<tr><td colspan="9"><div class="sp-empty"><i class="fa-solid fa-link d-block"></i><h5>No items match your filter</h5></div></td></tr>';
        return;
    }

    body.innerHTML = items.map(s => {
        const pos = s.posId ? POS_ITEMS.find(p => p.id === s.posId) : null;
        const matchKey = getMatchKey(s);
        const skuTypeBadge = s.hasVariation
            ? '<span class="sku-type-badge sku-type-variation">VAR SKU</span>'
            : '<span class="sku-type-badge sku-type-parent">PARENT SKU</span>';

        // Status badge
        let statusBadge = '';
        switch(s.mapStatus) {
            case 'auto':    statusBadge = '<span class="sp-badge sp-badge-success"><i class="fa-solid fa-wand-magic-sparkles"></i> Auto Matched</span>'; break;
            case 'manual':  statusBadge = '<span class="sp-badge sp-badge-info"><i class="fa-solid fa-hand-pointer"></i> Manual</span>'; break;
            case 'unmapped':statusBadge = '<span class="sp-badge sp-badge-warning"><i class="fa-solid fa-link-slash"></i> Unmatched</span>'; break;
            case 'duplicate':statusBadge = '<span class="sp-badge sp-badge-danger"><i class="fa-solid fa-clone"></i> Duplicate SKU</span>'; break;
            case 'missing_sku':statusBadge = '<span class="sp-badge sp-badge-danger"><i class="fa-solid fa-triangle-exclamation"></i> Missing SKU</span>'; break;
        }

        const posSkuCell = pos
            ? `<span class="sp-badge sp-badge-success">${pos.sku}</span>`
            : (s.mapStatus === 'missing_sku' ? '<span class="sp-badge sp-badge-danger">No SKU Available</span>' : '<span class="text-secondary fst-italic">—</span>');

        const linkIcon = s.mapped ? '<i class="fa-solid fa-link text-shopee"></i>' : '<i class="fa-solid fa-link-slash text-secondary" style="opacity:0.3"></i>';

        const actionBtn = s.mapped
            ? `<button class="btn btn-sm btn-ghost text-danger" onclick="unlinkMapping(${s.id})"><i class="fa-solid fa-unlink me-1"></i>Unlink</button>`
            : (s.mapStatus === 'missing_sku' ? '<span class="small text-danger">Incomplete</span>' : '');

        return `<tr>
            <td><div class="fw-bold">${s.name}</div><div class="small text-secondary">${s.modelId}</div></td>
            <td>${s.variation ? `<span class="small">${s.variation}</span>` : '<span class="text-secondary fst-italic">None</span>'}</td>
            <td><code class="small">${s.parentSku || '<span class="text-danger">empty</span>'}</code></td>
            <td>${s.variationSku ? `<code class="small">${s.variationSku}</code>` : '<span class="text-secondary">—</span>'}</td>
            <td><div class="d-flex align-items-center gap-2">${skuTypeBadge}<code class="small fw-bold">${matchKey || '⚠️'}</code></div></td>
            <td class="text-center">${linkIcon}</td>
            <td>${posSkuCell}</td>
            <td>${statusBadge}</td>
            <td class="text-end">${actionBtn}</td>
        </tr>`;
    }).join('');
}

// ── Render Manual Mapping Panels ──
function renderPanels() {
    const ss = (document.getElementById('shopeeSearch')?.value || '').toLowerCase();
    const ps = (document.getElementById('posSearch')?.value || '').toLowerCase();

    // Shopee unmapped panel
    const sp = document.getElementById('shopeePanel');
    const unmapped = SHOPEE_ITEMS.filter(s => !s.mapped && s.mapStatus !== 'missing_sku' && (!ss || s.name.toLowerCase().includes(ss) || getMatchKey(s).toLowerCase().includes(ss)));
    sp.innerHTML = unmapped.length ? unmapped.map(s => {
        const key = getMatchKey(s);
        const typeLbl = s.hasVariation ? 'Var SKU' : 'Parent SKU';
        return `<div class="map-item ${selectedShopee === s.id ? 'selected' : ''}" onclick="selectShopee(${s.id})">
            <div style="width:32px;height:32px;background:var(--shopee-light);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fa-solid fa-bag-shopping text-shopee" style="font-size:0.8rem"></i></div>
            <div class="flex-grow-1">
                <div class="fw-bold small">${s.name}${s.variation ? ` <span class="text-secondary fw-normal">— ${s.variation}</span>` : ''}</div>
                <div class="text-secondary" style="font-size:0.7rem">${typeLbl}: <strong>${key}</strong> · ${s.modelId}</div>
            </div>
        </div>`;
    }).join('') : '<div class="sp-empty"><i class="fa-solid fa-check-circle d-block"></i><h5>All matched!</h5><p>No unmatched Shopee items available.</p></div>';

    // POS available panel
    const pp = document.getElementById('posPanel');
    const avail = POS_ITEMS.filter(p => !p.used && (!ps || p.name.toLowerCase().includes(ps) || p.sku.toLowerCase().includes(ps)));
    pp.innerHTML = avail.length ? avail.map(p => `
        <div class="map-item ${selectedPos === p.id ? 'selected' : ''}" onclick="selectPos(${p.id})">
            <div style="width:32px;height:32px;background:var(--sp-info-bg);border-radius:8px;display:flex;align-items:center;justify-content:center"><i class="fa-solid fa-boxes-stacked" style="color:var(--sp-info);font-size:0.8rem"></i></div>
            <div class="flex-grow-1">
                <div class="fw-bold small">${p.name}</div>
                <div class="text-secondary" style="font-size:0.7rem">SKU: <strong>${p.sku}</strong></div>
            </div>
        </div>
    `).join('') : '<div class="sp-empty"><i class="fa-solid fa-boxes-stacked d-block"></i><h5>No available POS products</h5></div>';
}

function selectShopee(id) { selectedShopee = selectedShopee === id ? null : id; updateLinkBtn(); renderPanels(); }
function selectPos(id) { selectedPos = selectedPos === id ? null : id; updateLinkBtn(); renderPanels(); }
function updateLinkBtn() { document.getElementById('linkBtn').disabled = !(selectedShopee && selectedPos); }

function linkSelected() {
    const s = SHOPEE_ITEMS.find(x => x.id === selectedShopee);
    const p = POS_ITEMS.find(x => x.id === selectedPos);
    if (!s || !p) return;
    s.mapped = true; s.posId = p.id; s.mapStatus = 'manual'; p.used = true;
    selectedShopee = null; selectedPos = null;
    updateCounts(); renderAll();
    const key = getMatchKey(s);
    if (typeof EllaToast !== 'undefined') EllaToast.success(`Mapped: ${key} → ${p.sku} (${s.hasVariation ? 'Variation' : 'Parent'} SKU)`);
}

function unlinkMapping(shopeeId) {
    const s = SHOPEE_ITEMS.find(x => x.id === shopeeId);
    if (!s) return;
    const p = POS_ITEMS.find(x => x.id === s.posId);
    if (p) p.used = false;
    s.mapped = false; s.posId = null; s.mapStatus = 'unmapped';
    updateCounts(); renderAll();
    if (typeof EllaToast !== 'undefined') EllaToast.warning(`Mapping removed for ${s.name}${s.variation ? ' — ' + s.variation : ''}`);
}

function updateCounts() {
    const total = SHOPEE_ITEMS.length;
    const mapped = SHOPEE_ITEMS.filter(s => s.mapped).length;
    const issues = SHOPEE_ITEMS.filter(s => ['missing_sku','duplicate'].includes(s.mapStatus)).length;
    document.getElementById('totalCount').textContent = total;
    document.getElementById('mappedCount').textContent = mapped;
    document.getElementById('unmappedCount').textContent = total - mapped - issues;
    document.getElementById('issueCount').textContent = issues;
}

function renderAll() { renderMappingTable(); renderPanels(); updateCounts(); }

async function saveMappings(btn) {
    if (!btn) btn = event?.currentTarget || document.querySelector('.btn-shopee');
    const originalText = btn.innerHTML;

    // Collect ALL items to ensure DB is perfectly in sync with current UI state
    const toSave = SHOPEE_ITEMS.map(s => ({
        id: s.id,
        posSku: s.posId ? POS_ITEMS.find(p => p.id === s.posId)?.sku : null,
        posId: s.posId || null,
        status: s.mapStatus
    }));

    if (toSave.length === 0) {
        EllaToast.warning('No items found.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Saving...';

    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/save_mappings.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mappings: toSave })
        });
        const data = await res.json();
        if (data.success) {
            EllaToast.success(data.message);
            // Optionally reload or just reset the "changed" state
        } else {
            EllaToast.error(data.error);
        }
    } catch (e) {
        EllaToast.error('Network error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

document.addEventListener('DOMContentLoaded', renderAll);
</script>

<?php require_once '../../includes/footer.php'; ?>
