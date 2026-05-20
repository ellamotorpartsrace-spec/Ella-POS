<?php
$page_title = 'Shopee Sync — Stock Allocation';
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

$mappedRows = $conn->query("
    SELECT m.id, m.shopee_item_id, m.shopee_product_name, m.shopee_variation_name,
        m.shopee_model_id, m.shopee_stock, m.mapping_status, m.shopee_image_url,
        m.stock_allocation_ratio,
        (COALESCE(i1.quantity,0) + COALESCE(i2.quantity,0)) as pos_qty,
        COALESCE(v.sku, m.matched_pos_sku, m.shopee_variation_sku, m.shopee_parent_sku) as sku
    FROM shopee_product_mappings m
    LEFT JOIN product_variations v ON m.pos_product_id = v.variation_id
    LEFT JOIN inventory i1 ON v.variation_id = i1.variation_id AND i1.store_id = 1
    LEFT JOIN inventory i2 ON v.variation_id = i2.variation_id AND i2.store_id = 2
    WHERE m.mapping_status IN ('auto','manual')
    ORDER BY m.shopee_product_name ASC, m.shopee_variation_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$unmappedRows = $conn->query("
    SELECT id, shopee_item_id, shopee_product_name, shopee_variation_name, shopee_stock, shopee_image_url,
        COALESCE(shopee_variation_sku, shopee_parent_sku,'') as sku
    FROM shopee_product_mappings
    WHERE mapping_status NOT IN ('auto','manual')
    ORDER BY shopee_product_name ASC, shopee_variation_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Count SKU frequencies
$skuCounts = [];
foreach ($mappedRows as $r) {
    $sku = trim($r['sku'] ?? '');
    if ($sku !== '') {
        $skuCounts[$sku] = ($skuCounts[$sku] ?? 0) + 1;
    }
}

// Map SKUs to their lists
$skuMap = [];
foreach ($mappedRows as $r) {
    $sku = trim($r['sku'] ?? '');
    if ($sku !== '') {
        $skuMap[$sku][] = [
            'id' => (int)$r['id'],
            'name' => $r['shopee_product_name'],
            'varName' => $r['shopee_variation_name'] ?? '',
            'itemId' => $r['shopee_item_id'],
            'ratio' => (int)($r['stock_allocation_ratio'] ?? 100),
            'online' => (int)$r['shopee_stock']
        ];
    }
}

// Group mapped rows
$mappedGroups = [];
foreach ($mappedRows as $r) {
    $iid = $r['shopee_item_id'];
    if (!isset($mappedGroups[$iid])) $mappedGroups[$iid] = ['itemId'=>$iid,'name'=>$r['shopee_product_name'],'imageUrl'=>$r['shopee_image_url']??'','vars'=>[]];
    
    $sku = trim($r['sku'] ?? '');
    $isDup = ($sku !== '' && ($skuCounts[$sku] ?? 0) > 1);
    
    $dupDetails = [];
    if ($isDup && isset($skuMap[$sku])) {
        foreach ($skuMap[$sku] as $other) {
            if ($other['id'] !== (int)$r['id']) {
                $dupDetails[] = $other;
            }
        }
    }
    
    $st = $r['shopee_stock']==0?'unallocated':($r['shopee_stock']<=5?'low':'synced');
    $mappedGroups[$iid]['vars'][] = [
        'id'=>(int)$r['id'],'varName'=>$r['shopee_variation_name']??'','sku'=>$r['sku']??'',
        'total'=>(int)$r['pos_qty'],'online'=>(int)$r['shopee_stock'],'status'=>$st,
        'itemId'=>(int)$r['shopee_item_id'],'modelId'=>$r['shopee_model_id'],
        'ratio'=>(int)($r['stock_allocation_ratio'] ?? 100),
        'isDuplicate'=>$isDup,
        'dupDetails'=>$dupDetails
    ];
}

// Group unmapped rows
$unmappedGroups = [];
foreach ($unmappedRows as $r) {
    $iid = $r['shopee_item_id'];
    if (!isset($unmappedGroups[$iid])) $unmappedGroups[$iid] = ['itemId'=>$iid,'name'=>$r['shopee_product_name'],'imageUrl'=>$r['shopee_image_url']??'','vars'=>[]];
    $unmappedGroups[$iid]['vars'][] = ['id'=>(int)$r['id'],'varName'=>$r['shopee_variation_name']??'','sku'=>$r['sku'],'online'=>(int)$r['shopee_stock']];
}

$totalMapped   = count($mappedRows);
$totalUnmapped = count($unmappedRows);
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/shopee-sync.css?v=<?= filemtime(__DIR__.'/../../assets/css/shopee-sync.css') ?>">
<style>
.sp-tab-btn{padding:.45rem 1.1rem;border-radius:var(--sp-radius-sm);border:1.5px solid var(--border-color);background:transparent;font-weight:600;font-size:.82rem;color:var(--text-secondary);cursor:pointer;transition:all .2s;}
.sp-tab-btn.active{background:var(--shopee-gradient);color:#fff;border-color:transparent;box-shadow:0 2px 8px rgba(238,77,45,.25);}
.sp-tab-btn:not(.active):hover{border-color:var(--shopee-primary);color:var(--shopee-primary);}
</style>

<div class="sp-page sp-animate">
<div class="sp-breadcrumb">
    <a href="<?= BASE_URL ?>views/shopee/index.php">Shopee Sync</a>
    <i class="fa-solid fa-chevron-right" style="font-size:.6rem"></i><span>Stock Allocation</span>
</div>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h1 class="sp-title mb-0"><i class="fa-solid fa-sliders text-shopee me-2"></i>Stock Allocation</h1>
        <p class="sp-subtitle mb-0">Allocate POS inventory to Shopee per variation. Only mapped products sync stock to Shopee.</p>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="sp-stat-card accent-shopee">
            <div class="sp-stat-icon" style="background:var(--shopee-light);color:var(--shopee-primary)"><i class="fa-solid fa-link"></i></div>
            <div><div class="sp-stat-label">Mapped Products</div><div class="sp-stat-value"><?= count($mappedGroups) ?></div></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="sp-stat-card">
            <div class="sp-stat-icon" style="background:var(--sp-info-bg);color:var(--sp-info)"><i class="fa-solid fa-layer-group"></i></div>
            <div><div class="sp-stat-label">Mapped Variations</div><div class="sp-stat-value"><?= $totalMapped ?></div></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="sp-stat-card">
            <div class="sp-stat-icon" style="background:var(--sp-success-bg);color:var(--sp-success)"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div><div class="sp-stat-label">Total POS Stock</div><div class="sp-stat-value" id="sumTotal">0</div></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="sp-stat-card">
            <div class="sp-stat-icon" style="background:var(--shopee-light);color:var(--shopee-primary)"><i class="fa-solid fa-globe"></i></div>
            <div><div class="sp-stat-label">Allocated to Shopee</div><div class="sp-stat-value text-shopee" id="sumOnline">0</div></div>
        </div>
    </div>
</div>

<!-- Tab Toggle -->
<div class="d-flex align-items-center gap-2 mb-3">
    <button class="sp-tab-btn active" onclick="switchTab('mapped',this)">
        <i class="fa-solid fa-link me-1"></i>Mapped <span class="sp-badge sp-badge-success ms-1"><?= $totalMapped ?></span>
    </button>
    <button class="sp-tab-btn" onclick="switchTab('unmapped',this)">
        <i class="fa-solid fa-link-slash me-1"></i>Unmapped <span class="sp-badge sp-badge-neutral ms-1"><?= $totalUnmapped ?></span>
    </button>
</div>

<!-- Mapped Section -->
<div id="mappedSection">
    <!-- Global Filters -->
    <div class="sp-card mb-4" id="globalFilterCard">
        <div class="sp-card-body d-flex flex-wrap align-items-center gap-3" style="padding:.85rem 1.25rem">
            <div class="sp-search flex-grow-1" style="max-width:340px">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="allocSearch" placeholder="Search by name or SKU..." oninput="debouncedRender()">
            </div>
            
            <div class="sp-filter-pills">
                <button class="sp-pill active" onclick="setAllocFilter('all',this)">All</button>
                <button class="sp-pill" onclick="setAllocFilter('duplicate',this)"><i class="fa-solid fa-clone me-1 text-danger"></i>Shared SKUs</button>
                <button class="sp-pill" onclick="setAllocFilter('synced',this)">Allocated</button>
                <button class="sp-pill" onclick="setAllocFilter('low',this)">Low Stock</button>
                <button class="sp-pill" onclick="setAllocFilter('unallocated',this)">Unallocated</button>
            </div>
        </div>
    </div>
    <div class="sp-card">
        <div class="sp-card-body p-0 sp-table-wrap">
            <table class="sp-table">
                <thead>
                    <tr>
                        <th style="min-width:180px">Product / Variation</th>
                        <th>Parent SKU & Item ID</th>
                        <th>Variation SKU</th>
                        <th class="text-center">POS Stock</th>
                        <th class="text-center">Shopee Allocation</th>
                        <th class="text-center">Remaining</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="allocBody"></tbody>
            </table>
        </div>
        <!-- Premium Pagination Footer (Mapped) -->
        <div class="sp-card-footer d-flex align-items-center justify-content-between flex-wrap gap-3 border-top p-3" id="mappedPaginationFooter">
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
</div>

<!-- Unmapped Table -->
<div id="unmappedSection" style="display:none">
    <div class="sp-card">
        <div class="sp-card-body p-0 sp-table-wrap">
            <table class="sp-table">
                <thead>
                    <tr>
                        <th style="min-width:180px">Product / Variation</th>
                        <th>Parent SKU & Item ID</th>
                        <th>Variation SKU</th>
                        <th class="text-center">Current Shopee Stock</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="unmappedBody"></tbody>
            </table>
        </div>
        <!-- Premium Pagination Footer (Unmapped) -->
        <div class="sp-card-footer d-flex align-items-center justify-content-between flex-wrap gap-3 border-top p-3" id="unmappedPaginationFooter">
            <div class="d-flex align-items-center gap-2">
                <span class="text-secondary small">Items per page:</span>
                <select class="form-select form-select-sm" id="unmappedItemsPerPageSelect" onchange="changeUnmappedItemsPerPage(this.value)" style="width: auto;">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="text-secondary small fw-bold" id="unmappedPaginationStatus">Page 1 of 1 (0 items)</div>
            <div class="d-flex align-items-center gap-1" id="unmappedPaginationButtons"></div>
        </div>
    </div>
</div>
</div>

<!-- Edit Modal -->
<div class="modal fade sp-modal" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-sliders text-shopee me-2"></i>Set Shopee Stock Allocation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:var(--btn-close-filter)"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><div class="fw-bold fs-6" id="mName"></div><div class="text-secondary small">SKU: <span id="mSku">—</span></div></div>
                <div class="d-flex justify-content-between align-items-center p-3 rounded mb-3" style="background:var(--bg-body);border:1px solid var(--border-color)">
                    <div class="text-center">
                        <div class="text-secondary small mb-1">POS Physical Stock</div>
                        <div class="fw-bold fs-4" id="mTotal">0</div>
                    </div>
                    <div class="text-secondary"><i class="fa-solid fa-arrow-right"></i></div>
                    <div class="text-center">
                        <div class="text-shopee small mb-1 fw-bold">Shopee Allocated Stock</div>
                        <div class="d-flex align-items-center justify-content-center">
                            <div class="sp-qty-control" style="width: 130px;">
                                <button class="sp-qty-btn" onclick="adjStock(-1)"><i class="fa-solid fa-minus"></i></button>
                                <input type="number" class="sp-qty-input fw-bold text-shopee" id="mOnlineStock" value="0" min="0" oninput="calcModal()">
                                <button class="sp-qty-btn" onclick="adjStock(1)"><i class="fa-solid fa-plus"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="text-secondary"><i class="fa-solid fa-arrow-right-long"></i></div>
                    <div class="text-center">
                        <div class="text-secondary small mb-1">Remaining POS Stock</div>
                        <div class="fw-bold fs-4 text-dark" id="mRemainingCalc">0</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary">Quick Splits (% of POS stock)</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="sp-pill" onclick="presetStock(25)">25%</button>
                        <button class="sp-pill" onclick="presetStock(50)">50%</button>
                        <button class="sp-pill" onclick="presetStock(100)">100%</button>
                    </div>
                </div>
                <div class="alert py-2 small mb-0" style="background: var(--shopee-light); color: var(--shopee-primary); border: 1px solid rgba(238, 77, 45, 0.2);">
                    <i class="fa-solid fa-info-circle me-1"></i> Stock allocation is pushed directly to Shopee. The system automatically maintains this proportional split if POS stock changes.
                </div>
                <div id="mWarning" class="d-none alert py-2 small mt-2 mb-0"></div>
                <div id="mSharedDetailsContainer" class="d-none"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-shopee" onclick="saveAllocation(this)"><i class="fa-solid fa-check me-2"></i>Save & Sync</button>
            </div>
        </div>
    </div>
</div>

<script>
const MAPPED_GROUPS   = <?= json_encode(array_values($mappedGroups)) ?>;
const UNMAPPED_GROUPS = <?= json_encode(array_values($unmappedGroups)) ?>;

// Flat list for easier lookup
const MAPPED_FLAT = [];
MAPPED_GROUPS.forEach(g => g.vars.forEach(v => {
    v.groupName = g.name;
    MAPPED_FLAT.push(v);
}));

let currentEdit=null, editModal, allocFilter='all';
let currentPage = 1;
let itemsPerPage = 25;
let unmappedCurrentPage = 1;
let unmappedItemsPerPage = 25;

function escHtml(s){if(!s)return'';return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

let renderTimeout = null;

function debouncedRender() {
    currentPage = 1; // Reset page on search
    clearTimeout(renderTimeout);
    renderTimeout = setTimeout(() => renderMapped(), 250);
}

document.addEventListener('DOMContentLoaded',()=>{
    editModal=new bootstrap.Modal(document.getElementById('editModal'));
    renderMapped(); renderUnmapped(); updateSummary();
});

function switchTab(tab, btn){
    document.querySelectorAll('.sp-tab-btn').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('mappedSection').style.display  = tab==='mapped'   ?'block':'none';
    document.getElementById('unmappedSection').style.display= tab==='unmapped' ?'block':'none';
}
function setAllocFilter(f,btn){
    allocFilter=f;
    currentPage = 1; // Reset page on filter change
    document.querySelectorAll('#mappedSection .sp-filter-pills .sp-pill').forEach(p=>p.classList.remove('active'));
    btn.classList.add('active');
    renderMapped();
}

function stockPill(online){
    if(online===0)  return`<span class="sp-stock-pill s-zero"><i class="fa-solid fa-times"></i>${online}</span>`;
    if(online<=5)   return`<span class="sp-stock-pill s-low"><i class="fa-solid fa-triangle-exclamation"></i>${online}</span>`;
    return               `<span class="sp-stock-pill s-high"><i class="fa-solid fa-check"></i>${online.toLocaleString()}</span>`;
}

function changeItemsPerPage(val) {
    itemsPerPage = parseInt(val, 10);
    currentPage = 1;
    renderMapped();
}

function goToPage(page) {
    currentPage = page;
    renderMapped();
}

function changeUnmappedItemsPerPage(val) {
    unmappedItemsPerPage = parseInt(val, 10);
    unmappedCurrentPage = 1;
    renderUnmapped();
}

function goToUnmappedPage(page) {
    unmappedCurrentPage = page;
    renderUnmapped();
}

function renderModalSharedDetails(v) {
    const container = document.getElementById('mSharedDetailsContainer');
    if (!v.isDuplicate || !v.dupDetails || !v.dupDetails.length) {
        container.innerHTML = '';
        container.className = 'd-none';
        return;
    }

    container.className = 'mt-3 p-3 rounded text-start';
    container.style.background = 'var(--bg-body)';
    container.style.border = '1px dashed rgba(238,77,45,0.4)';

    let itemsHtml = '';
    const currentInputVal = parseInt(document.getElementById('mOnlineStock').value) || 0;
    const currentRatio = v.total > 0 ? Math.round((currentInputVal / v.total) * 100) : 100;
    let totalOnlineStock = currentInputVal;
    
    // Add current listing detail
    itemsHtml += `
    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-dashed" style="border-color:var(--border-color) !important;">
        <span class="fw-semibold text-dark"><i class="fa-solid fa-angle-right me-1 text-shopee"></i>This Listing (Active)</span>
        <span class="fw-bold text-shopee" style="font-size:0.85rem;" id="mSharedCurrentOnline">${currentInputVal} Qty <span class="text-secondary font-normal" style="font-size:0.75rem;">(${currentRatio}%)</span></span>
    </div>`;

    // Add other listings
    v.dupDetails.forEach(d => {
        const dName = escHtml(d.name) + (d.varName ? ` (${escHtml(d.varName)})` : '');
        itemsHtml += `
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-secondary small text-truncate" style="max-width: 250px;"><i class="fa-solid fa-angle-right me-1 text-secondary"></i>${dName}</span>
            <span class="fw-semibold text-secondary" style="font-size:0.8rem;">${d.online} Qty <span class="text-secondary font-normal" style="font-size:0.72rem;">(${d.ratio}%)</span></span>
        </div>`;
        totalOnlineStock += d.online;
    });

    const isExceeded = totalOnlineStock > v.total;
    const alertClass = isExceeded ? 'alert-danger' : 'alert-success';
    const alertIcon = isExceeded ? 'fa-triangle-exclamation' : 'fa-circle-check';
    const alertMsg = isExceeded 
        ? `Warning: Total allocated stock across shared listings (${totalOnlineStock} Qty) exceeds POS physical stock (${v.total} Qty)!`
        : `Stock is safely allocated across all shared listings (Total: ${totalOnlineStock}/${v.total} Qty).`;

    container.innerHTML = `
        <div class="fw-bold text-secondary mb-2 d-flex justify-content-between small">
            <span><i class="fa-solid fa-clone me-1 text-shopee"></i> Shared SKU Allocation Splits:</span>
            <span class="fw-bold text-dark" id="mSharedTotalOnline">${totalOnlineStock} / ${v.total} Total Qty</span>
        </div>
        <div class="small mb-3">${itemsHtml}</div>
        <div class="alert ${alertClass} py-2 px-3 small mb-0 d-flex align-items-center gap-2">
            <i class="fa-solid ${alertIcon}"></i>
            <span>${alertMsg}</span>
        </div>
    `;
    container.classList.remove('d-none');
}

function updateModalSharedDetailsAlert() {
    const currentInputVal = parseInt(document.getElementById('mOnlineStock').value) || 0;
    const currentRatio = currentEdit.total > 0 ? Math.round((currentInputVal / currentEdit.total) * 100) : 100;
    
    // Update active row text in modal
    const activeRow = document.getElementById('mSharedCurrentOnline');
    if (activeRow) {
        activeRow.innerHTML = `${currentInputVal} Qty <span class="text-secondary font-normal" style="font-size:0.75rem;">(${currentRatio}%)</span>`;
    }

    let totalOnlineStock = currentInputVal;
    currentEdit.dupDetails.forEach(d => {
        totalOnlineStock += d.online;
    });

    const totalLabel = document.getElementById('mSharedTotalOnline');
    if (totalLabel) {
        totalLabel.textContent = `${totalOnlineStock} / ${currentEdit.total} Total Qty`;
    }

    const alertContainer = document.querySelector('#mSharedDetailsContainer .alert');
    if (alertContainer) {
        const isExceeded = totalOnlineStock > currentEdit.total;
        alertContainer.className = `alert ${isExceeded ? 'alert-danger' : 'alert-success'} py-2 px-3 small mb-0 d-flex align-items-center gap-2`;
        alertContainer.querySelector('i').className = `fa-solid ${isExceeded ? 'fa-triangle-exclamation' : 'fa-circle-check'}`;
        alertContainer.querySelector('span').textContent = isExceeded 
            ? `Warning: Total allocated stock across shared listings (${totalOnlineStock} Qty) exceeds POS physical stock (${currentEdit.total} Qty)!`
            : `Stock is safely allocated across all shared listings (Total: ${totalOnlineStock}/${currentEdit.total} Qty).`;
    }
}

function renderMapped(){
    const q=(document.getElementById('allocSearch')?.value||'').toLowerCase();
    const body=document.getElementById('allocBody');

    // First, filter groups
    const matchedGroups = [];
    MAPPED_GROUPS.forEach(g=>{
        const vars=g.vars.filter(v=>{
            if(q&&!g.name.toLowerCase().includes(q)&&!(v.sku||'').toLowerCase().includes(q)&&!(v.varName||'').toLowerCase().includes(q))return false;
            if(allocFilter==='synced'&&v.online===0)return false;
            if(allocFilter==='duplicate'&&!v.isDuplicate)return false;
            if(allocFilter==='low'&&v.status!=='low')return false;
            if(allocFilter==='unallocated'&&v.online!==0)return false;
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

        const parentSkuHtml = g.parentSku
            ? `<span class="sp-sku-code">${escHtml(g.parentSku)}</span>`
            : `<span class="text-danger" style="font-size:.75rem;font-style:italic">empty</span>`;

        const imgHtml = g.imageUrl
            ? `<img src="${escHtml(g.imageUrl)}" class="sp-product-img" alt="Product Image">`
            : `<div class="sp-img-placeholder"><i class="fa-solid fa-image"></i></div>`;

        const isSimple = g.vars.length === 1 && (!g.vars[0].varName || g.vars[0].varName.toLowerCase() === 'main item');

        if (isSimple) {
            const v = vars[0];
            if (v) {
                const rem=v.total-v.online;
                const remCls=rem<0?'text-danger fw-bold':rem<=5?'text-warning fw-bold':'fw-bold';
                let badge='';
                if(v.online===0)badge=`<span class="sp-badge sp-badge-neutral"><i class="fa-solid fa-minus-circle"></i> Unallocated</span>`;
                else if(rem<0)  badge=`<span class="sp-badge sp-badge-danger"><i class="fa-solid fa-exclamation"></i> Over</span>`;
                else if(v.status==='low')badge=`<span class="sp-badge sp-badge-warning"><i class="fa-solid fa-triangle-exclamation"></i> Low</span>`;
                else badge=`<span class="sp-badge sp-badge-success"><i class="fa-solid fa-check"></i> OK</span>`;

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
                    <td>
                        ${v.sku?`<span class="sp-sku-code">${escHtml(v.sku)}</span>`:`<span class="text-secondary">—</span>`}
                        ${v.isDuplicate?`<span class="badge bg-danger-light text-danger ms-1" style="font-size:0.68rem; border:1px solid rgba(220,53,69,0.25); cursor:pointer;" onclick="openEdit(${v.id})" title="Click to view shared allocation details"><i class="fa-solid fa-clone me-1"></i>Shared (${(v.ratio + v.dupDetails.reduce((acc, d) => acc + d.ratio, 0))}% Allocated)</span>`:''}
                    </td>
                    <td class="text-center fw-bold">${v.total.toLocaleString()}</td>
                    <td class="text-center">
                        <span class="fw-bold text-shopee d-block">${v.online.toLocaleString()}</span>
                        <span class="text-secondary small font-normal" style="font-size:0.72rem;">(${v.ratio}%)</span>
                    </td>
                    <td class="text-center ${remCls}">${rem.toLocaleString()}</td>
                    <td>${badge}</td>
                    <td class="text-end"><button class="btn btn-sm btn-outline-shopee px-3" onclick="openEdit(${v.id})"><i class="fa-solid fa-pen-to-square me-1"></i>Edit</button></td>
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
                <td class="text-center"><span class="text-secondary">—</span></td>
                <td class="text-center"><span class="text-secondary">—</span></td>
                <td><span class="text-secondary">—</span></td>
                <td class="text-end"><span class="text-secondary">—</span></td>
            </tr>`;

            // Variation Rows
            vars.forEach(v=>{
                const rem=v.total-v.online;
                const remCls=rem<0?'text-danger fw-bold':rem<=5?'text-warning fw-bold':'fw-bold';
                let badge='';
                if(v.online===0)badge=`<span class="sp-badge sp-badge-neutral"><i class="fa-solid fa-minus-circle"></i> Unallocated</span>`;
                else if(rem<0)  badge=`<span class="sp-badge sp-badge-danger"><i class="fa-solid fa-exclamation"></i> Over</span>`;
                else if(v.status==='low')badge=`<span class="sp-badge sp-badge-warning"><i class="fa-solid fa-triangle-exclamation"></i> Low</span>`;
                else badge=`<span class="sp-badge sp-badge-success"><i class="fa-solid fa-check"></i> OK</span>`;

                const vNameHtml = v.varName
                    ? `<span class="sp-var-name-text">${escHtml(v.varName)}</span>`
                    : `<span class="sp-var-name-text text-secondary fst-italic">Main Item</span>`;

                html += `<tr>
                    <td class="sp-tree-indent">
                        <div class="d-flex align-items-center">
                            <div>
                                ${vNameHtml}
                            </div>
                        </div>
                    </td>
                    <td><span class="text-secondary">—</span></td>
                    <td>
                        ${v.sku?`<span class="sp-sku-code">${escHtml(v.sku)}</span>`:`<span class="text-secondary">—</span>`}
                        ${v.isDuplicate?`<span class="badge bg-danger-light text-danger ms-1" style="font-size:0.68rem; border:1px solid rgba(220,53,69,0.25); cursor:pointer;" onclick="openEdit(${v.id})" title="Click to view shared allocation details"><i class="fa-solid fa-clone me-1"></i>Shared (${(v.ratio + v.dupDetails.reduce((acc, d) => acc + d.ratio, 0))}% Allocated)</span>`:''}
                    </td>
                    <td class="text-center fw-bold">${v.total.toLocaleString()}</td>
                    <td class="text-center">
                        <span class="fw-bold text-shopee d-block">${v.online.toLocaleString()}</span>
                        <span class="text-secondary small font-normal" style="font-size:0.72rem;">(${v.ratio}%)</span>
                    </td>
                    <td class="text-center ${remCls}">${rem.toLocaleString()}</td>
                    <td>${badge}</td>
                    <td class="text-end"><button class="btn btn-sm btn-outline-shopee px-3" onclick="openEdit(${v.id})"><i class="fa-solid fa-pen-to-square me-1"></i>Edit</button></td>
                </tr>`;
            });
        }
    });

    if(!totalItems){
        const msg=MAPPED_FLAT.length===0
            ?`<div class="sp-empty"><i class="fa-solid fa-link d-block"></i><h5>No mapped products yet</h5><p>Go to <a href="${window.BASE_URL}views/shopee/mapping.php" class="text-shopee fw-bold">Product Mapping</a> first.</p></div>`
            :`<div class="sp-empty"><i class="fa-solid fa-filter d-block"></i><h5>No products match this filter</h5></div>`;
        body.innerHTML=`<tr><td colspan="8">${msg}</td></tr>`;
        document.getElementById('paginationStatus').textContent = 'Page 1 of 1 (0 items)';
        document.getElementById('paginationButtons').innerHTML = '';
        return;
    }
    
    body.innerHTML=html;

    document.getElementById('paginationStatus').textContent = `Page ${currentPage} of ${totalPages} (${totalItems} products)`;
    renderPaginationButtons(totalItems, totalPages);
}

function renderUnmapped(){
    const body=document.getElementById('unmappedBody');
    if(!UNMAPPED_GROUPS.length){
        body.innerHTML=`<tr><td colspan="5"><div class="sp-empty"><i class="fa-solid fa-circle-check d-block" style="color:var(--sp-success)"></i><h5>All products are mapped!</h5></div></td></tr>`;
        document.getElementById('unmappedPaginationStatus').textContent = 'Page 1 of 1 (0 items)';
        document.getElementById('unmappedPaginationButtons').innerHTML = '';
        return;
    }

    const totalItems = UNMAPPED_GROUPS.length;
    const totalPages = Math.ceil(totalItems / unmappedItemsPerPage) || 1;
    if (unmappedCurrentPage > totalPages) unmappedCurrentPage = totalPages;

    const start = (unmappedCurrentPage - 1) * unmappedItemsPerPage;
    const end = start + unmappedItemsPerPage;
    const slice = UNMAPPED_GROUPS.slice(start, end);

    let html='';
    slice.forEach(g=>{
        const isSimple = g.vars.length === 1 && (!g.vars[0].varName || g.vars[0].varName.toLowerCase() === 'main item');

        const imgHtml = g.imageUrl
            ? `<img src="${escHtml(g.imageUrl)}" class="sp-product-img" alt="Product Image">`
            : `<div class="sp-img-placeholder"><i class="fa-solid fa-image"></i></div>`;

        if (isSimple) {
            const v = g.vars[0];
            if (v) {
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
                            <span class="small text-secondary" style="font-size:0.72rem">ID: ${escHtml(g.itemId)}</span>
                        </div>
                    </td>
                    <td>${v.sku?`<span class="sp-sku-code">${escHtml(v.sku)}</span>`:`<span class="text-secondary">—</span>`}</td>
                    <td class="text-center">${stockPill(v.online)}</td>
                    <td class="text-end"><a href="${window.BASE_URL}views/shopee/mapping.php" class="btn btn-sm btn-outline-shopee px-3"><i class="fa-solid fa-link me-1"></i>Map Now</a></td>
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
                        <span class="small text-secondary" style="font-size:0.72rem">ID: ${escHtml(g.itemId)}</span>
                    </div>
                </td>
                <td><span class="text-secondary">—</span></td>
                <td class="text-center"><span class="text-secondary">—</span></td>
                <td class="text-end"><span class="text-secondary">—</span></td>
            </tr>`;

            // Variation Rows
            g.vars.forEach(v=>{
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
                    <td>${v.sku?`<span class="sp-sku-code">${escHtml(v.sku)}</span>`:`<span class="text-secondary">—</span>`}</td>
                    <td class="text-center">${stockPill(v.online)}</td>
                    <td class="text-end"><a href="${window.BASE_URL}views/shopee/mapping.php" class="btn btn-sm btn-outline-shopee px-3"><i class="fa-solid fa-link me-1"></i>Map Now</a></td>
                </tr>`;
            });
        }
    });

    body.innerHTML=html;

    document.getElementById('unmappedPaginationStatus').textContent = `Page ${unmappedCurrentPage} of ${totalPages} (${totalItems} products)`;
    renderUnmappedPaginationButtons(totalItems, totalPages);
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

function renderUnmappedPaginationButtons(totalItems, totalPages) {
    const container = document.getElementById('unmappedPaginationButtons');
    if (!container) return;
    
    let html = '';
    html += `<button class="btn btn-sm btn-outline-shopee-secondary px-2 py-1" ${unmappedCurrentPage === 1 ? 'disabled' : ''} onclick="goToUnmappedPage(1)"><i class="fa-solid fa-angles-left"></i></button>`;
    html += `<button class="btn btn-sm btn-outline-shopee-secondary px-2 py-1" ${unmappedCurrentPage === 1 ? 'disabled' : ''} onclick="goToUnmappedPage(${unmappedCurrentPage - 1})"><i class="fa-solid fa-angle-left"></i></button>`;
    
    const maxVisiblePages = 5;
    let startPage = Math.max(1, unmappedCurrentPage - 2);
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    if (endPage - startPage < maxVisiblePages - 1) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    for (let p = startPage; p <= endPage; p++) {
        if (p === unmappedCurrentPage) {
            html += `<button class="btn btn-sm btn-shopee px-3 py-1 active">${p}</button>`;
        } else {
            html += `<button class="btn btn-sm btn-outline-shopee-secondary px-3 py-1" onclick="goToUnmappedPage(${p})">${p}</button>`;
        }
    }
    
    html += `<button class="btn btn-sm btn-outline-shopee-secondary px-2 py-1" ${unmappedCurrentPage === totalPages ? 'disabled' : ''} onclick="goToUnmappedPage(${unmappedCurrentPage + 1})"><i class="fa-solid fa-angle-right"></i></button>`;
    html += `<button class="btn btn-sm btn-outline-shopee-secondary px-2 py-1" ${unmappedCurrentPage === totalPages ? 'disabled' : ''} onclick="goToUnmappedPage(${totalPages})"><i class="fa-solid fa-angles-right"></i></button>`;
    
    container.innerHTML = html;
}
function updateSummary(){
    const t=MAPPED_FLAT.reduce((a,v)=>{a.total+=v.total;a.online+=v.online;return a;},{total:0,online:0});
    document.getElementById('sumTotal').textContent =t.total.toLocaleString();
    document.getElementById('sumOnline').textContent=t.online.toLocaleString();
}

function openEdit(id){
    currentEdit=MAPPED_FLAT.find(v=>v.id===id);
    document.getElementById('mName').textContent =currentEdit.groupName+(currentEdit.varName?' — '+currentEdit.varName:'');
    document.getElementById('mSku').textContent  =currentEdit.sku||'—';
    document.getElementById('mTotal').textContent=currentEdit.total;
    document.getElementById('mOnlineStock').value =currentEdit.online !== undefined ? currentEdit.online : currentEdit.total;
    calcModal();
    renderModalSharedDetails(currentEdit);
    editModal.show();
}

function adjStock(d){
    const inp=document.getElementById('mOnlineStock');
    const newVal = Math.max(0, Math.min(currentEdit.total, (parseInt(inp.value)||0)+d));
    inp.value=newVal;
    calcModal();
}

function presetStock(pct){
    const computedVal = Math.floor(currentEdit.total * (pct / 100));
    document.getElementById('mOnlineStock').value = computedVal;
    calcModal();
}

function calcModal(){
    const onlineVal=parseInt(document.getElementById('mOnlineStock').value)||0;
    const remaining = Math.max(0, currentEdit.total - onlineVal);
    const warn=document.getElementById('mWarning');
    
    document.getElementById('mRemainingCalc').textContent=remaining;
    
    if(onlineVal > currentEdit.total){
        warn.className='alert alert-danger py-2 small mb-0 mt-2';
        warn.innerHTML=`<i class="fa-solid fa-triangle-exclamation me-2"></i>Allocated stock cannot exceed physical POS stock (${currentEdit.total})`;
    } else if(onlineVal === 0 && currentEdit.total > 0){
        warn.className='alert alert-warning py-2 small mb-0 mt-2';
        warn.innerHTML=`<i class="fa-solid fa-triangle-exclamation me-2"></i>Shopee will receive 0 stock.`;
    } else {
        warn.className='d-none';
    }

    if (currentEdit.isDuplicate) {
        updateModalSharedDetailsAlert();
    }
}

async function saveAllocation(btn){
    const onlineVal=parseInt(document.getElementById('mOnlineStock').value)||0;
    if(onlineVal < 0){
        EllaToast.error('Stock quantity cannot be negative.');
        return;
    }
    if(onlineVal > currentEdit.total){
        EllaToast.error(`Allocated stock cannot exceed physical POS stock (${currentEdit.total}).`);
        return;
    }
    
    const orig=btn.innerHTML;
    btn.disabled=true;
    btn.innerHTML='<i class="fa-solid fa-spinner fa-spin me-2"></i>Syncing...';
    try{
        const res=await fetch(`${window.BASE_URL}api/shopee/update_allocation.php`,{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({id:currentEdit.id,online_stock:onlineVal})
        });
        const data=await res.json();
        if(data.success){
            const newRatio = currentEdit.total > 0 ? Math.round((onlineVal / currentEdit.total) * 100) : 100;
            currentEdit.ratio=newRatio;
            currentEdit.online=onlineVal;
            currentEdit.status=onlineVal===0?'unallocated':(onlineVal<=5?'low':'synced');
            
            renderMapped();
            updateSummary();
            editModal.hide();
            EllaToast.success(data.message);
        }else {
            EllaToast.error(data.error);
        }
    }catch(e){
        EllaToast.error('Network error');
    }finally{
        btn.disabled=false;
        btn.innerHTML=orig;
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
