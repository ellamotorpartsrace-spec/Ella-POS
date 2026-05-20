<?php
$page_title = 'Shopee Sync — Product Mapping';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    denyAccess("You do not have permission to access Shopee Sync.");
}
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$db = new Database();
$conn = $db->getConnection();
$rows = $conn->query("SELECT * FROM shopee_product_mappings ORDER BY shopee_product_name ASC, shopee_variation_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$posRows = $conn->query("
    SELECT v.variation_id as id,
        p.product_name,
        v.variation_name,
        v.sku,
        COALESCE(p.brand_name, '') as brand,
        COALESCE(v.barcode, '') as barcode
    FROM product_variations v JOIN products p ON v.product_id = p.product_id
    WHERE v.status='active' ORDER BY p.product_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
$usedPosIds = array_filter(array_column($rows, 'pos_product_id'));

// Group Shopee items by item_id
$groups = [];
foreach ($rows as $r) {
    $iid = $r['shopee_item_id'];
    if (!isset($groups[$iid])) {
        $groups[$iid] = ['itemId'=>$iid,'name'=>$r['shopee_product_name'],'parentSku'=>$r['shopee_parent_sku']??'','imageUrl'=>$r['shopee_image_url']??'','variations'=>[]];
    }
    $groups[$iid]['variations'][] = [
        'id'=>(int)$r['id'],'varName'=>$r['shopee_variation_name']??'',
        'parentSku'=>$r['shopee_parent_sku']??'','variationSku'=>$r['shopee_variation_sku']??'',
        'hasVariation'=>(bool)$r['has_variation'],'mapped'=>in_array($r['mapping_status'],['auto','manual']),
        'posId'=>$r['pos_product_id']?(int)$r['pos_product_id']:null,'mapStatus'=>$r['mapping_status'],
    ];
}
$posJson = [];
foreach ($posRows as $p) {
    $posJson[] = [
        'id' => (int)$p['id'],
        'product_name' => $p['product_name'],
        'variation_name' => $p['variation_name'] ?? '',
        'name' => $p['product_name'] . ($p['variation_name'] ? ' (' . $p['variation_name'] . ')' : ''),
        'sku' => $p['sku'],
        'brand' => $p['brand'],
        'barcode' => $p['barcode'],
        'used' => in_array($p['id'], $usedPosIds)
    ];
}
$hasProducts = count($rows) > 0;
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/shopee-sync.css?v=<?= filemtime(__DIR__.'/../../assets/css/shopee-sync.css') ?>">
<style>
.match-hero { text-align:center; padding:4rem 2rem; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--sp-radius-lg); box-shadow:var(--sp-shadow-sm); }
.match-hero-icon { width:80px;height:80px;border-radius:50%;margin:0 auto 1.5rem;background:var(--shopee-light);display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--shopee-primary); }
.map-panel { max-height: 350px; overflow-y: auto; padding-right: .5rem; }
.map-item { padding:.75rem 1rem; border:1px solid var(--border-color); border-radius:var(--sp-radius-sm); margin-bottom:.4rem; cursor:pointer; transition:all .2s; display:flex; align-items:center; gap:.75rem; }
.map-item:hover,.map-item.selected { border-color:var(--shopee-primary); background:var(--shopee-light); }
.map-item.selected { box-shadow:0 0 0 2px rgba(238,77,45,.2); }
</style>

<div class="sp-page sp-animate">
<div class="sp-breadcrumb">
    <a href="<?= BASE_URL ?>views/shopee/index.php">Shopee Sync</a>
    <i class="fa-solid fa-chevron-right" style="font-size:.6rem"></i><span>Product Mapping</span>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h1 class="sp-title mb-0"><i class="fa-solid fa-link text-shopee me-2"></i>Product Mapping</h1>
        <p class="sp-subtitle mb-0">Match Shopee products to POS inventory by SKU. Products and their variations are grouped together.</p>
    </div>
    <div class="d-flex gap-2" id="headerBtns">
        <button class="btn btn-outline-shopee" onclick="runAutoMatch()"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>Auto-Match</button>
        <button class="btn btn-shopee" onclick="saveMappings(this)"><i class="fa-solid fa-cloud-arrow-up me-2"></i>Save Mappings</button>
    </div>
</div>

<!-- Stats (hidden until match runs) -->
<div class="row g-3 mb-4" id="statsRow" style="display:none">
    <div class="col-md-3 col-6">
        <div class="sp-stat-card accent-success">
            <div class="sp-stat-icon" style="background:var(--sp-success-bg);color:var(--sp-success)"><i class="fa-solid fa-link"></i></div>
            <div><div class="sp-stat-label">Total Matched</div><div class="sp-stat-value" id="cntMatched">0</div></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="sp-stat-card accent-warning">
            <div class="sp-stat-icon" style="background:var(--sp-warning-bg);color:var(--sp-warning)"><i class="fa-solid fa-link-slash"></i></div>
            <div><div class="sp-stat-label">Total Unmatched</div><div class="sp-stat-value" id="cntUnmatched">0</div></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="sp-stat-card accent-shopee">
            <div class="sp-stat-icon" style="background:var(--shopee-light);color:var(--shopee-primary)"><i class="fa-solid fa-clone"></i></div>
            <div><div class="sp-stat-label">Duplicate SKUs</div><div class="sp-stat-value" id="cntDupes">0</div></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="sp-stat-card accent-danger">
            <div class="sp-stat-icon" style="background:var(--sp-danger-bg);color:var(--sp-danger)"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div><div class="sp-stat-label">Missing SKUs</div><div class="sp-stat-value" id="cntMissing">0</div></div>
        </div>
    </div>
</div>

<!-- Idle State -->
<?php if (!$hasProducts): ?>
<div id="idleState">
    <div class="match-hero">
        <div class="match-hero-icon"><i class="fa-solid fa-bag-shopping"></i></div>
        <h4 class="fw-bold mb-2">No Shopee Products Found</h4>
        <p class="text-secondary mb-4">Sync products from Shopee first before mapping.</p>
        <a href="<?= BASE_URL ?>views/shopee/products.php" class="btn btn-shopee"><i class="fa-solid fa-bag-shopping me-2"></i>Go to Products</a>
    </div>
</div>
<?php endif; ?>

<!-- Results State -->
<div id="resultsState" style="display:none">
    <!-- Filter -->
    <div class="sp-card mb-4">
        <div class="sp-card-body d-flex flex-wrap align-items-center gap-3" style="padding:.85rem 1.25rem">
            <div class="sp-search flex-grow-1" style="max-width:340px">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="mapSearch" placeholder="Search by name or SKU..." oninput="debouncedRender()">
            </div>
            <div class="sp-filter-pills ms-2">
                <button class="sp-pill active" onclick="setFilter('all',this)">All</button>
                <button class="sp-pill" onclick="setFilter('mapped',this)"><i class="fa-solid fa-link me-1"></i>Matched</button>
                <button class="sp-pill" onclick="setFilter('unmapped',this)"><i class="fa-solid fa-link-slash me-1"></i>Unmatched</button>
                <button class="sp-pill" onclick="setFilter('dupes',this)"><i class="fa-solid fa-clone me-1"></i>Duplicate SKUs</button>
                <button class="sp-pill" onclick="setFilter('missing',this)"><i class="fa-solid fa-triangle-exclamation me-1"></i>Missing SKUs</button>
            </div>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-outline-shopee btn-sm" onclick="runAutoMatch(true)"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Re-run Auto-Match</button>
            </div>
        </div>
    </div>

    <!-- Mapping Table (Grouped) -->
    <div class="sp-card mb-4">
        <div class="sp-card-body p-0 sp-table-wrap">
            <table class="sp-table">
                <thead>
                    <tr>
                        <th style="min-width:200px">Product / Variation</th>
                        <th>Parent SKU & Item ID</th>
                        <th>Variation SKU</th>
                        <th class="text-center"><i class="fa-solid fa-arrows-left-right"></i></th>
                        <th>POS Match</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="mapTableBody"></tbody>
            </table>
        </div>
        <!-- Premium Pagination Footer -->
        <div class="sp-card-footer d-flex align-items-center justify-content-between flex-wrap gap-3 border-top p-3">
            <div class="d-flex align-items-center gap-2">
                <span class="text-secondary small">Items per page:</span>
                <select class="form-select form-select-sm" id="itemsPerPageSelect" onchange="changeItemsPerPage(this.value)" style="width: auto;">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="text-secondary small fw-bold" id="paginationStatus">Page 1 of 1 (0 items)</div>
            <div class="d-flex align-items-center gap-1" id="paginationButtons"></div>
        </div>
    </div>

</div> <!-- Closes #resultsState -->
</div> <!-- Closes .sp-page (removes relative z-index context to prevent modal backdrop bugs) -->

<!-- Manual Map Modal -->
<div class="modal fade sp-modal" id="manualMapModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-link text-shopee me-2"></i>Map to POS Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:var(--btn-close-filter)"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 p-3 rounded" style="background:var(--shopee-light); border:1px solid rgba(238,77,45,0.2)">
                    <div class="small fw-bold text-shopee mb-1">Shopee Item to Map:</div>
                    <div class="fw-bold fs-6" id="mmShopeeName"></div>
                    <div class="text-secondary small mt-1">SKU: <span id="mmShopeeSku" class="sp-sku-code"></span></div>
                </div>
                
                <h6 class="fw-bold mb-2"><i class="fa-solid fa-boxes-stacked me-2" style="color:var(--sp-info)"></i>Select POS Product</h6>
                <div class="sp-search mb-3">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" id="mmPosSearch" placeholder="Search POS by name or SKU..." oninput="renderModalPos()">
                </div>
                
                <div id="mmPosPanel" class="map-panel"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-shopee" id="mmLinkBtn" disabled onclick="linkFromModal()"><i class="fa-solid fa-link me-2"></i>Link Selected</button>
            </div>
        </div>
    </div>
</div>

<script>
let manualMapModal;
document.addEventListener('DOMContentLoaded', () => {
    manualMapModal = new bootstrap.Modal(document.getElementById('manualMapModal'));
    if(ALL_ITEMS.length > 0) showResults();
});
// Flatten all variations into a flat list for matching logic, keep groups for display
const GROUPS   = <?= json_encode(array_values($groups)) ?>;
const POS_ITEMS= <?= json_encode($posJson) ?>;

// Build flat item list from groups (preserving object references to ensure UI updates automatically without reload)
const ALL_ITEMS = [];
GROUPS.forEach(g => g.variations.forEach(v => {
    v.groupName = g.name;
    v.itemId = g.itemId;
    ALL_ITEMS.push(v);
}));

let activeFilter='all', selectedShopee=null, selectedPos=null;
let shopeeSkuCounts = {};
let renderTimeout = null;
let currentPage = 1;
let itemsPerPage = 25;

function debouncedRender() {
    currentPage = 1; // Reset page on search
    clearTimeout(renderTimeout);
    renderTimeout = setTimeout(() => renderTable(), 250);
}

function escHtml(s){if(!s)return'';return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function getMatchKey(v){return v.hasVariation?(v.variationSku||''):(v.parentSku||'');}

function showResults(){
    const idle = document.getElementById('idleState');
    if (idle) idle.style.display='none';
    document.getElementById('resultsState').style.display='block';
    document.getElementById('statsRow').style.display='flex';
    document.getElementById('headerBtns').style.display='flex';
    updateCounts();renderTable();
}
function showManualOnly(){showResults();}

async function runAutoMatch(isReRun = false) {
    let count = 0;
    const autoMatchedList = [];
    
    ALL_ITEMS.filter(v => !v.mapped && v.mapStatus !== 'missing_sku').forEach(v => {
        const key = getMatchKey(v);
        if (!key) return;
        const matches = POS_ITEMS.filter(p => !p.used && p.sku && p.sku.toLowerCase() === key.toLowerCase());
        if (matches.length === 1) {
            v.mapped = true; v.posId = matches[0].id; v.mapStatus = 'auto'; matches[0].used = true;
            autoMatchedList.push({
                id: v.id,
                posSku: matches[0].sku,
                posId: matches[0].id,
                status: 'auto'
            });
            count++;
        } else if (matches.length > 1) {
            v.mapStatus = 'duplicate';
        }
    });
    
    if (count === 0) {
        if (typeof EllaToast !== 'undefined') EllaToast.warning('No new auto-match opportunities found.');
        return;
    }
    
    // Call the backend API to securely save these auto-matches permanently!
    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/save_mappings.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                mappings: autoMatchedList,
                trigger: isReRun ? 're_run_auto_match' : 'auto_match'
            })
        });
        const data = await res.json();
        if (data.success) {
            showResults();
            if (typeof EllaToast !== 'undefined') {
                EllaToast.success(`Successfully auto-matched & saved ${count} variation(s) by SKU!`);
            }
        } else {
            if (typeof EllaToast !== 'undefined') EllaToast.error(data.error || 'Failed to save auto-matches');
        }
    } catch (e) {
        if (typeof EllaToast !== 'undefined') EllaToast.error('Network error during auto-match save');
    }
}

function setFilter(f,btn){
    activeFilter=f;
    currentPage = 1; // Reset page on filter change
    document.querySelectorAll('.sp-filter-pills .sp-pill').forEach(p=>p.classList.remove('active'));
    btn.classList.add('active');renderTable();
}

function changeItemsPerPage(val) {
    itemsPerPage = parseInt(val, 10);
    currentPage = 1;
    renderTable();
}

function goToPage(page) {
    currentPage = page;
    renderTable();
}

function renderTable(){
    const q=(document.getElementById('mapSearch')?.value||'').toLowerCase();
    const body=document.getElementById('mapTableBody');

    // First, filter groups
    const matchedGroups = [];
    GROUPS.forEach(g=>{
        const vars=g.variations.filter(v=>{
            if(q && !String(g.name || '').toLowerCase().includes(q) && !String(v.variationSku || '').toLowerCase().includes(q) && !String((g.variations[0] && g.variations[0].parentSku) || '').toLowerCase().includes(q) && !String(v.varName || '').toLowerCase().includes(q)) return false;
            if(activeFilter==='mapped'&&!v.mapped)return false;
            if(activeFilter==='unmapped'&&v.mapped)return false;
            if(activeFilter==='dupes') {
                const key = (getMatchKey(v) || '').toLowerCase().trim();
                if (!key || !shopeeSkuCounts[key] || shopeeSkuCounts[key] <= 1) return false;
            }
            if(activeFilter==='missing'&&v.mapStatus!=='missing_sku')return false;
            return true;
        });
        if(vars.length > 0) {
            matchedGroups.push({ group: g, matchedVars: vars });
        }
    });

    const totalItems = matchedGroups.length;
    const totalPages = Math.ceil(totalItems / itemsPerPage) || 1;
    if (currentPage > totalPages) currentPage = totalPages;

    const start = (currentPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const slice = matchedGroups.slice(start, end);

    let html = '';
    slice.forEach(item => {
        const g = item.group;
        const vars = item.matchedVars;

        const parentSkuHtml = g.variations[0] && g.variations[0].parentSku
            ? `<span class="sp-sku-code">${escHtml(g.variations[0].parentSku)}</span>`
            : `<span class="text-danger" style="font-size:.75rem;font-style:italic">empty</span>`;

        const imgHtml = g.imageUrl
            ? `<img src="${escHtml(g.imageUrl)}" class="sp-product-img" alt="Product Image">`
            : `<div class="sp-img-placeholder"><i class="fa-solid fa-image"></i></div>`;

        const isSimple = g.variations.length === 1 && (!g.variations[0].varName || g.variations[0].varName.toLowerCase() === 'main item');

        if (isSimple) {
            const v = vars[0];
            if (v) {
                const pos=v.posId?POS_ITEMS.find(p=>p.id===v.posId):null;
                let statusBadge='';
                switch(v.mapStatus){
                    case 'auto':        statusBadge=`<span class="sp-badge sp-badge-success"><i class="fa-solid fa-wand-magic-sparkles"></i> Auto</span>`;break;
                    case 'manual':      statusBadge=`<span class="sp-badge sp-badge-info"><i class="fa-solid fa-hand-pointer"></i> Manual</span>`;break;
                    case 'unmapped':    statusBadge=`<span class="sp-badge sp-badge-warning"><i class="fa-solid fa-link-slash"></i> Unmatched</span>`;break;
                    case 'duplicate':   statusBadge=`<span class="sp-badge sp-badge-danger"><i class="fa-solid fa-clone"></i> Dup SKU</span>`;break;
                    case 'missing_sku': statusBadge=`<span class="sp-badge sp-badge-danger"><i class="fa-solid fa-triangle-exclamation"></i> No SKU</span>`;break;
                    default:            statusBadge=`<span class="sp-badge sp-badge-neutral">${escHtml(v.mapStatus)}</span>`;
                }
                const posCell=pos?`<span class="sp-badge sp-badge-success">${escHtml(pos.sku)}</span>`:`<span class="text-secondary">—</span>`;
                const linkIcon=v.mapped?`<i class="fa-solid fa-link text-shopee"></i>`:`<i class="fa-solid fa-link-slash text-secondary" style="opacity:.3"></i>`;
                const actionBtn = v.mapped 
                    ? `<button class="btn btn-sm btn-ghost text-danger" onclick="unlinkItem(${v.id})"><i class="fa-solid fa-unlink me-1"></i>Unlink</button>`
                    : `<button class="btn btn-sm btn-outline-shopee" onclick="openManualMap(${v.id})"><i class="fa-solid fa-link me-1"></i>Map</button>`;

                html += `<tr class="sp-group-start">
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            ${imgHtml}
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="fa-brands fa-shopee" style="color:var(--shopee-primary);font-size:.85rem;flex-shrink:0"></i>
                                    <span class="fw-bold" style="font-size:.9rem">${escHtml(g.name)}</span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-column">
                            ${parentSkuHtml}
                            <span class="small text-secondary" style="font-size:0.72rem;margin-top:2px">ID: ${escHtml(g.itemId)}</span>
                        </div>
                    </td>
                    <td>${(() => {
                        const key = (getMatchKey(v) || '').toLowerCase().trim();
                        const isDuplicate = key && shopeeSkuCounts[key] > 1;
                        return isDuplicate
                            ? `<span class="sp-sku-code">${escHtml(v.variationSku || v.parentSku)}</span><span class="badge bg-danger-light text-danger ms-1" style="font-size:0.65rem; border:1px solid rgba(220,53,69,0.3);"><i class="fa-solid fa-clone"></i> Shared SKU</span>`
                            : `<span class="sp-sku-code">${escHtml(v.variationSku || v.parentSku)}</span>`;
                    })()}</td>
                    <td class="text-center">${linkIcon}</td>
                    <td>${posCell}</td>
                    <td>${statusBadge}</td>
                    <td class="text-end">${actionBtn}</td>
                </tr>`;
            }
        } else {
            // Parent Row
            html += `<tr class="sp-group-start">
                <td>
                    <div class="d-flex align-items-center gap-3">
                        ${imgHtml}
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="fa-brands fa-shopee" style="color:var(--shopee-primary);font-size:.85rem;flex-shrink:0"></i>
                                <span class="fw-bold" style="font-size:.9rem">${escHtml(g.name)}</span>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-column">
                        ${parentSkuHtml}
                        <span class="small text-secondary" style="font-size:0.72rem;margin-top:2px">ID: ${escHtml(g.itemId)}</span>
                    </div>
                </td>
                <td><span class="text-secondary">—</span></td>
                <td class="text-center"><span class="text-secondary">—</span></td>
                <td><span class="text-secondary">—</span></td>
                <td><span class="text-secondary">—</span></td>
                <td class="text-end"><span class="text-secondary">—</span></td>
            </tr>`;

            // Variation Rows
            vars.forEach(v=>{
                const pos=v.posId?POS_ITEMS.find(p=>p.id===v.posId):null;
                let statusBadge='';
                switch(v.mapStatus){
                    case 'auto':        statusBadge=`<span class="sp-badge sp-badge-success"><i class="fa-solid fa-wand-magic-sparkles"></i> Auto</span>`;break;
                    case 'manual':      statusBadge=`<span class="sp-badge sp-badge-info"><i class="fa-solid fa-hand-pointer"></i> Manual</span>`;break;
                    case 'unmapped':    statusBadge=`<span class="sp-badge sp-badge-warning"><i class="fa-solid fa-link-slash"></i> Unmatched</span>`;break;
                    case 'duplicate':   statusBadge=`<span class="sp-badge sp-badge-danger"><i class="fa-solid fa-clone"></i> Dup SKU</span>`;break;
                    case 'missing_sku': statusBadge=`<span class="sp-badge sp-badge-danger"><i class="fa-solid fa-triangle-exclamation"></i> No SKU</span>`;break;
                    default:            statusBadge=`<span class="sp-badge sp-badge-neutral">${escHtml(v.mapStatus)}</span>`;
                }
                const posCell=pos?`<span class="sp-badge sp-badge-success">${escHtml(pos.sku)}</span>`:`<span class="text-secondary">—</span>`;
                const linkIcon=v.mapped?`<i class="fa-solid fa-link text-shopee"></i>`:`<i class="fa-solid fa-link-slash text-secondary" style="opacity:.3"></i>`;
                const actionBtn = v.mapped 
                    ? `<button class="btn btn-sm btn-ghost text-danger" onclick="unlinkItem(${v.id})"><i class="fa-solid fa-unlink me-1"></i>Unlink</button>`
                    : `<button class="btn btn-sm btn-outline-shopee" onclick="openManualMap(${v.id})"><i class="fa-solid fa-link me-1"></i>Map</button>`;
                
                const vNameHtml = v.varName
                    ? `<span class="sp-var-name-text">${escHtml(v.varName)}</span>`
                    : `<span class="sp-var-name-text text-secondary fst-italic">Main Item</span>`;

                html += `<tr>
                    <td class="sp-tree-indent">
                        <div class="d-flex align-items-center">
                            ${vNameHtml}
                        </div>
                    </td>
                    <td><span class="text-secondary">—</span></td>
                    <td>${(() => {
                        const key = (getMatchKey(v) || '').toLowerCase().trim();
                        const isDuplicate = key && shopeeSkuCounts[key] > 1;
                        return isDuplicate
                            ? `<span class="sp-sku-code">${escHtml(v.variationSku || v.parentSku)}</span><span class="badge bg-danger-light text-danger ms-1" style="font-size:0.65rem; border:1px solid rgba(220,53,69,0.3);"><i class="fa-solid fa-clone"></i> Shared SKU</span>`
                            : `<span class="sp-sku-code">${escHtml(v.variationSku || v.parentSku)}</span>`;
                    })()}</td>
                    <td class="text-center">${linkIcon}</td>
                    <td>${posCell}</td>
                    <td>${statusBadge}</td>
                    <td class="text-end">${actionBtn}</td>
                </tr>`;
            });
        }
    });

    if(!totalItems){
        body.innerHTML=`<tr><td colspan="7"><div class="sp-empty"><i class="fa-solid fa-filter d-block"></i><h5>No items match this filter</h5></div></td></tr>`;
        document.getElementById('paginationStatus').textContent = 'Page 1 of 1 (0 items)';
        document.getElementById('paginationButtons').innerHTML = '';
        return;
    }
    
    body.innerHTML=html;

    document.getElementById('paginationStatus').textContent = `Page ${currentPage} of ${totalPages} (${totalItems} products)`;
    renderPaginationButtons(totalItems, totalPages);
}

function renderPaginationButtons(totalItems, totalPages) {
    const container = document.getElementById('paginationButtons');
    if (!container) return;
    
    let html = '';
    html += `<button class="btn btn-sm btn-outline-shopee-secondary px-2 py-1" ${currentPage === 1 ? 'disabled' : ''} onclick="goToPage(1)"><i class="fa-solid fa-angles-left"></i></button>`;
    html += `<button class="btn btn-sm btn-outline-shopee-secondary px-2 py-1" ${currentPage === 1 ? 'disabled' : ''} onclick="goToPage(${currentPage - 1})"><i class="fa-solid fa-angle-left"></i></button>`;
    
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    if (endPage - startPage < maxVisiblePages - 1) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    for (let p = startPage; p <= endPage; p++) {
        if (p === currentPage) {
            html += `<button class="btn btn-sm btn-shopee px-3 py-1 active">${p}</button>`;
        } else {
            html += `<button class="btn btn-sm btn-outline-shopee-secondary px-3 py-1" onclick="goToPage(${p})">${p}</button>`;
        }
    }
    
    html += `<button class="btn btn-sm btn-outline-shopee-secondary px-2 py-1" ${currentPage === totalPages ? 'disabled' : ''} onclick="goToPage(${currentPage + 1})"><i class="fa-solid fa-angle-right"></i></button>`;
    html += `<button class="btn btn-sm btn-outline-shopee-secondary px-2 py-1" ${currentPage === totalPages ? 'disabled' : ''} onclick="goToPage(${totalPages})"><i class="fa-solid fa-angles-right"></i></button>`;
    
    container.innerHTML = html;
}

// Modal Manual Mapping Logic
function openManualMap(id) {
    selectedShopee = id;
    selectedPos = null;
    const v = ALL_ITEMS.find(x => x.id === id);
    if (!v) return;
    
    document.getElementById('mmShopeeName').textContent = v.groupName + (v.varName ? ` — ${v.varName}` : '');
    document.getElementById('mmShopeeSku').textContent = getMatchKey(v) || 'No SKU';
    document.getElementById('mmPosSearch').value = '';
    
    // Proactively restore single-item linking handler
    document.getElementById('mmLinkBtn').onclick = linkFromModal;
    document.getElementById('mmLinkBtn').innerHTML = '<i class="fa-solid fa-link me-2"></i>Link Selected';
    
    renderModalPos();
    manualMapModal.show();
}

function renderModalPos() {
    const ps = (document.getElementById('mmPosSearch')?.value || '').trim().toLowerCase();
    const pp = document.getElementById('mmPosPanel');
    
    // Find current Shopee item SKU
    let shopeeSku = '';
    if (typeof selectedShopee === 'number') {
        const v = ALL_ITEMS.find(x => x.id === selectedShopee);
        if (v) shopeeSku = getMatchKey(v);
    }
    
    // Filter POS items: Support global keyword matching across name, SKU, brand, and barcode
    let avail = [];
    if (ps) {
        const keywords = ps.split(/\s+/).filter(k => k.length > 0);
        avail = POS_ITEMS.filter(p => {
            const name = p.name.toLowerCase();
            const sku = (p.sku || '').toLowerCase();
            const brand = (p.brand || '').toLowerCase();
            const barcode = (p.barcode || '').toLowerCase();
            return keywords.every(k => 
                name.includes(k) || 
                sku.includes(k) || 
                brand.includes(k) || 
                barcode.includes(k)
            );
        });
    } else {
        avail = POS_ITEMS;
    }
    
    let html = '';
    
    // Render Suggested Match section if searching is empty and there's a SKU
    if (!ps && shopeeSku) {
        const exactMatches = POS_ITEMS.filter(p => p.sku && p.sku.toLowerCase() === shopeeSku.toLowerCase());
        if (exactMatches.length > 0) {
            html += `<div class="small fw-bold text-success mb-2"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Suggested SKU Match:</div>`;
            exactMatches.forEach(p => {
                const varBadge = p.variation_name 
                    ? `<span class="badge bg-light text-dark border ms-2 small" style="font-size: 0.7rem; font-weight: normal;">${escHtml(p.variation_name)}</span>` 
                    : '';
                
                html += `
                <div class="map-item border-success bg-success-light ${selectedPos === p.id ? 'selected' : ''}" onclick="selectModalPos(${p.id})" style="padding: 0.75rem 1rem;">
                    <div style="width:30px;height:30px;background:var(--sp-success-bg);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fa-solid fa-circle-check" style="color:var(--sp-success);font-size:.75rem"></i></div>
                    <div class="flex-grow-1" style="min-width:0; line-height: 1.4;">
                        <div class="fw-bold text-success" style="font-size: 0.85rem; word-break: break-word;">
                            ${escHtml(p.product_name)} ${varBadge}
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-1" style="font-size:.72rem;">
                            <span class="text-secondary">SKU:</span>
                            <strong class="sp-sku-code border-success text-success" style="font-family: monospace; font-size: 0.75rem;">${escHtml(p.sku)}</strong>
                            ${p.brand ? `<span class="text-secondary">| Brand: <strong class="text-dark">${escHtml(p.brand)}</strong></span>` : ''}
                            ${p.barcode ? `<span class="text-secondary">| Barcode: <strong class="text-dark">${escHtml(p.barcode)}</strong></span>` : ''}
                        </div>
                    </div>
                    ${selectedPos === p.id ? `<i class="fa-solid fa-circle-check text-success fs-5"></i>` : `<span class="badge bg-success text-white small px-2 py-1" style="font-size:0.6rem">SKU Match</span>`}
                </div>`;
            });
            html += `<hr style="margin:0.75rem 0; opacity:0.1">`;
        }
    }
    
    // Show only the first 50 suggestions when search is empty; show all matching results when searching
    const topAvail = ps ? avail : avail.slice(0, 50);
    
    html += topAvail.length ? topAvail.map(p => {
        // Skip rendering in general list if already shown in suggested section
        const isExactMatch = !ps && shopeeSku && p.sku && p.sku.toLowerCase() === shopeeSku.toLowerCase();
        if (isExactMatch) return '';
        
        const usedBadge = p.used ? `<span class="sp-badge sp-badge-neutral ms-auto" style="font-size:0.62rem; flex-shrink:0;"><i class="fa-solid fa-link me-1"></i>Linked</span>` : '';
        const varBadge = p.variation_name 
            ? `<span class="badge bg-light text-dark border ms-2 small" style="font-size: 0.7rem; font-weight: normal;">${escHtml(p.variation_name)}</span>` 
            : '';
        
        return `
        <div class="map-item ${selectedPos === p.id ? 'selected' : ''}" onclick="selectModalPos(${p.id})" style="padding: 0.75rem 1rem;">
            <div style="width:30px;height:30px;background:var(--sp-info-bg);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fa-solid fa-boxes-stacked" style="color:var(--sp-info);font-size:.75rem"></i></div>
            <div class="flex-grow-1" style="min-width:0; line-height: 1.4;">
                <div class="fw-bold text-dark" style="font-size: 0.85rem; word-break: break-word;">
                    ${escHtml(p.product_name)} ${varBadge}
                </div>
                <div class="d-flex align-items-center gap-2 mt-1" style="font-size:.72rem;">
                    <span class="text-secondary">SKU:</span>
                    <strong class="sp-sku-code text-shopee" style="font-family: monospace; font-size: 0.75rem;">${escHtml(p.sku)}</strong>
                    ${p.brand ? `<span class="text-secondary">| Brand: <strong class="text-dark">${escHtml(p.brand)}</strong></span>` : ''}
                    ${p.barcode ? `<span class="text-secondary">| Barcode: <strong class="text-dark">${escHtml(p.barcode)}</strong></span>` : ''}
                </div>
            </div>
            ${usedBadge}
            ${selectedPos === p.id ? `<i class="fa-solid fa-circle-check text-shopee ms-2 fs-5"></i>` : ''}
        </div>`;
    }).join('') : `<div class="sp-empty py-4"><i class="fa-solid fa-boxes-stacked d-block"></i><h6>No POS products found</h6></div>`;
        
    pp.innerHTML = html;
    document.getElementById('mmLinkBtn').disabled = !selectedPos;
}

function selectModalPos(id) {
    selectedPos = selectedPos === id ? null : id;
    renderModalPos();
}

async function linkFromModal() {
    if (!selectedShopee || !selectedPos) return;
    const v = ALL_ITEMS.find(x => x.id === selectedShopee);
    const p = POS_ITEMS.find(x => x.id === selectedPos);
    if (!v || !p) return;
    
    const btn = document.getElementById('mmLinkBtn');
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Saving...';
    
    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/save_mappings.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                mappings: [{
                    id: v.id,
                    posSku: p.sku,
                    posId: p.id,
                    status: 'manual'
                }],
                trigger: 'manual_link'
            })
        });
        const data = await res.json();
        if (data.success) {
            v.mapped = true; v.posId = p.id; v.mapStatus = 'manual'; p.used = true;
            manualMapModal.hide();
            updateCounts(); renderTable();
            if(typeof EllaToast!=='undefined') EllaToast.success(`Successfully Linked: ${p.sku}`);
        } else {
            EllaToast.error(data.error || 'Failed to save mapping');
        }
    } catch (e) {
        EllaToast.error('Network error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        selectedShopee = null; selectedPos = null;
    }
}

async function unlinkItem(id) {
    if (!confirm('Are you sure you want to unlink this item?')) return;
    const v = ALL_ITEMS.find(x => x.id === id);
    if (!v) return;
    
    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/save_mappings.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                mappings: [{
                    id: v.id,
                    posSku: null,
                    posId: null,
                    status: 'unmapped'
                }],
                trigger: 'unlink'
            })
        });
        const data = await res.json();
        if (data.success) {
            const p = POS_ITEMS.find(x => x.id === v.posId);
            if (p) p.used = false;
            
            v.mapped = false; v.posId = null; v.mapStatus = 'unmapped';
            updateCounts(); renderTable();
            showUndoToast('unlink', [id], null);
            if(typeof EllaToast!=='undefined') EllaToast.warning(`Unlinked: ${v.groupName}${v.varName ? ' — ' + v.varName : ''}`);
        } else {
            EllaToast.error(data.error || 'Failed to unlink item');
        }
    } catch (e) {
        EllaToast.error('Network error');
    }
}
function updateCounts(){
    // First, count all SKU uses within Shopee (ALL_ITEMS) to accurately detect duplicates on the Shopee side
    shopeeSkuCounts = {};
    ALL_ITEMS.forEach(item => {
        const sku = (getMatchKey(item) || '').toLowerCase().trim();
        if (sku) {
            shopeeSkuCounts[sku] = (shopeeSkuCounts[sku] || 0) + 1;
        }
    });

    // Re-evaluate mapStatus dynamically for all unmapped variations based on standard conflict rules
    ALL_ITEMS.forEach(v => {
        if (!v.mapped) {
            const key = (getMatchKey(v) || '').toLowerCase().trim();
            if (!key) {
                v.mapStatus = 'missing_sku';
            } else if (shopeeSkuCounts[key] > 1) {
                v.mapStatus = 'duplicate';
            } else {
                v.mapStatus = 'unmapped';
            }
        }
    });

    const matched=ALL_ITEMS.filter(v=>v.mapped).length;
    const missing=ALL_ITEMS.filter(v=>v.mapStatus==='missing_sku').length;
    
    // We count all duplicate variations (both mapped and unmapped) to compute unique duplicate SKUs
    const duplicateVars = ALL_ITEMS.filter(v => {
        const key = (getMatchKey(v) || '').toLowerCase().trim();
        return key && shopeeSkuCounts[key] > 1;
    });

    // But for the total unmatched count logic, we only count the UNMAPPED duplicate variations
    const unmappedDupVarsCount = ALL_ITEMS.filter(v => {
        const key = (getMatchKey(v) || '').toLowerCase().trim();
        return !v.mapped && key && shopeeSkuCounts[key] > 1;
    }).length;

    // Calculate unique duplicate SKUs (matching standard calculation of Resolution Center)
    const uniqueDupes = new Set();
    duplicateVars.forEach(v => {
        const sku = (getMatchKey(v) || '').toLowerCase().trim();
        if (sku) uniqueDupes.add(sku);
    });
    const uniqueDupCount = uniqueDupes.size;
    
    document.getElementById('cntMatched').textContent=matched;
    document.getElementById('cntUnmatched').textContent=Math.max(0,ALL_ITEMS.length-matched-missing-unmappedDupVarsCount);
    if(document.getElementById('cntMissing')) document.getElementById('cntMissing').textContent=missing;
    if(document.getElementById('cntDupes')) document.getElementById('cntDupes').textContent=uniqueDupCount;
}

async function saveMappings(btn){
    const toSave=ALL_ITEMS.map(v=>({id:v.id,posSku:v.posId?(POS_ITEMS.find(p=>p.id===v.posId)?.sku||null):null,posId:v.posId||null,status:v.mapStatus}));
    if(!toSave.length){EllaToast.warning('No items.');return;}
    const orig=btn.innerHTML;btn.disabled=true;btn.innerHTML='<i class="fa-solid fa-spinner fa-spin me-2"></i>Saving...';
    try{
        const res=await fetch(`${window.BASE_URL}api/shopee/save_mappings.php`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({mappings:toSave,trigger:'bulk_save'})});
        const data=await res.json();
        if(data.success)EllaToast.success(data.message);else EllaToast.error(data.error);
    } catch (e) {
        EllaToast.error('Network error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

function toggleGroup(itemId, btn) {
    const rows = document.querySelectorAll('.group-vars-' + itemId);
    const icon = btn.querySelector('i');
    const isCollapsed = icon.classList.contains('fa-chevron-right');
    
    if (isCollapsed) {
        icon.classList.replace('fa-chevron-right', 'fa-chevron-down');
        rows.forEach(r => r.classList.remove('collapsed'));
    } else {
        icon.classList.replace('fa-chevron-down', 'fa-chevron-right');
        rows.forEach(r => r.classList.add('collapsed'));
    }
}




</script>

<?php require_once '../../includes/footer.php'; ?>
