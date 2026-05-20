<?php
$page_title = 'Shopee Sync — Products';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    denyAccess("You do not have permission to access Shopee Sync.");
}
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

$db   = new Database();
$conn = $db->getConnection();
$rows = $conn->query("
    SELECT id, shopee_item_id, shopee_model_id, shopee_product_name,
        shopee_variation_name, shopee_parent_sku, shopee_variation_sku,
        shopee_stock, shopee_price, has_variation, last_synced_at, shopee_image_url
    FROM shopee_product_mappings
    ORDER BY shopee_product_name ASC, shopee_variation_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Group by parent product (shopee_item_id)
$groups = [];
foreach ($rows as $r) {
    $iid = $r['shopee_item_id'];
    if (!isset($groups[$iid])) {
        $groups[$iid] = [
            'itemId'       => $iid,
            'name'         => $r['shopee_product_name'],
            'parentSku'    => $r['shopee_parent_sku'] ?? '',
            'hasVariation' => (bool)$r['has_variation'],
            'imageUrl'     => $r['shopee_image_url'] ?? '',
            'variations'   => [],
        ];
    }
    $groups[$iid]['variations'][] = [
        'id'       => (int)$r['id'],
        'modelId'  => $r['shopee_model_id'],
        'varName'  => $r['shopee_variation_name'] ?? '',
        'varSku'   => $r['shopee_variation_sku']  ?? '',
        'stock'    => (int)$r['shopee_stock'],
        'price'    => (float)$r['shopee_price'],
        'synced'   => $r['last_synced_at'] ? date('M d, Y H:i', strtotime($r['last_synced_at'])) : 'N/A',
    ];
}

$totalParents = count($groups);
$totalVars    = count($rows);
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/shopee-sync.css?v=<?= filemtime(__DIR__.'/../../assets/css/shopee-sync.css') ?>">
<style>
.sp-item-id  { font-size: 0.75rem; color: var(--text-secondary); font-family: monospace; font-weight: 600; }
.sp-model-id { font-size: 0.75rem; color: var(--text-secondary); font-family: monospace; font-weight: 600; }
</style>

<div class="sp-page sp-animate">
<div class="sp-breadcrumb">
    <a href="<?= BASE_URL ?>views/shopee/index.php">Shopee Sync</a>
    <i class="fa-solid fa-chevron-right" style="font-size:.6rem"></i>
    <span>Shopee Products</span>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h1 class="sp-title mb-0"><i class="fa-solid fa-bag-shopping text-shopee me-2"></i>Shopee Products</h1>
        <p class="sp-subtitle mb-0">Live product data fetched from your Shopee store, organized by product and variation.</p>
    </div>
    <button class="btn btn-shopee" id="btnSync" onclick="syncProducts(this)">
        <i class="fa-solid fa-rotate me-2"></i>Sync Products
    </button>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="sp-stat-card accent-shopee">
            <div class="sp-stat-icon" style="background:var(--shopee-light);color:var(--shopee-primary)"><i class="fa-solid fa-bag-shopping"></i></div>
            <div><div class="sp-stat-label">Total Products</div><div class="sp-stat-value"><?= number_format($totalParents) ?></div></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="sp-stat-card">
            <div class="sp-stat-icon" style="background:var(--sp-info-bg);color:var(--sp-info)"><i class="fa-solid fa-layer-group"></i></div>
            <div><div class="sp-stat-label">Total Variations</div><div class="sp-stat-value"><?= number_format($totalVars) ?></div></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="sp-stat-card">
            <div class="sp-stat-icon" style="background:var(--sp-success-bg);color:var(--sp-success)"><i class="fa-solid fa-circle-check"></i></div>
            <div><div class="sp-stat-label">In Stock</div><div class="sp-stat-value" id="kInStock">—</div></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="sp-stat-card">
            <div class="sp-stat-icon" style="background:var(--sp-danger-bg);color:var(--sp-danger)"><i class="fa-solid fa-box-open"></i></div>
            <div><div class="sp-stat-label">Out of Stock</div><div class="sp-stat-value" id="kOos">—</div></div>
        </div>
    </div>
</div>

<!-- Sync Status -->
<div id="syncStatus" class="mb-3" style="display:none"></div>

<!-- Filter Bar -->
<div class="sp-card mb-4">
    <div class="sp-card-body d-flex flex-wrap align-items-center gap-3" style="padding:.85rem 1.25rem">
        <div class="sp-search flex-grow-1" style="max-width:380px">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="prodSearch" placeholder="Search by product name or SKU..." oninput="debouncedRender()">
        </div>
        <div class="sp-filter-pills">
            <button class="sp-pill active" onclick="setFilter('all',this)">All</button>
            <button class="sp-pill" onclick="setFilter('in_stock',this)"><i class="fa-solid fa-circle-check me-1"></i>In Stock</button>
            <button class="sp-pill" onclick="setFilter('low',this)"><i class="fa-solid fa-triangle-exclamation me-1"></i>Low Stock</button>
            <button class="sp-pill" onclick="setFilter('oos',this)"><i class="fa-solid fa-box-open me-1"></i>Out of Stock</button>
        </div>
        <div class="text-secondary small ms-auto fw-bold" id="prodCount"></div>
    </div>
</div>

<!-- Products Table (Grouped) -->
<div class="sp-card">
    <div class="sp-card-body p-0 sp-table-wrap">
        <table class="sp-table" id="productsTable">
            <thead>
                <tr>
                    <th style="min-width:200px">Product / Variation</th>
                    <th>Item ID</th>
                    <th>Parent SKU</th>
                    <th>Variation SKU</th>
                    <th>Model ID</th>
                    <th class="text-center">Stock</th>
                    <th class="text-end">Price</th>
                </tr>
            </thead>
            <tbody id="prodBody">
                <tr><td colspan="7"><div class="sp-empty"><i class="fa-solid fa-spinner fa-spin d-block"></i><h5>Loading...</h5></div></td></tr>
            </tbody>
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
</div>

<script>
const GROUPS = <?= json_encode(array_values($groups)) ?>;
let activeFilter = 'all';
let renderTimeout = null;
let currentPage = 1;
let itemsPerPage = 25;

function debouncedRender() {
    currentPage = 1; // Reset page on search
    clearTimeout(renderTimeout);
    renderTimeout = setTimeout(() => renderProducts(), 250);
}

function escHtml(s) { if(!s)return''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function stockPill(qty) {
    if (qty===0)  return `<span class="sp-stock-pill stock-zero"><i class="fa-solid fa-times"></i>${qty}</span>`;
    if (qty<=5)   return `<span class="sp-stock-pill stock-low"><i class="fa-solid fa-triangle-exclamation"></i>${qty}</span>`;
    return             `<span class="sp-stock-pill stock-high"><i class="fa-solid fa-check"></i>${qty.toLocaleString()}</span>`;
}

function setFilter(f, btn) {
    activeFilter = f;
    currentPage = 1; // Reset page on filter change
    document.querySelectorAll('.sp-filter-pills .sp-pill').forEach(p=>p.classList.remove('active'));
    btn.classList.add('active');
    renderProducts();
}

function changeItemsPerPage(val) {
    itemsPerPage = parseInt(val, 10);
    currentPage = 1;
    renderProducts();
}

function goToPage(page) {
    currentPage = page;
    renderProducts();
}

function renderProducts() {
    const q    = (document.getElementById('prodSearch')?.value||'').toLowerCase().trim();
    const body = document.getElementById('prodBody');
    let inStock=0, oos=0;
    
    // First, filter groups
    const matchedGroups = [];
    GROUPS.forEach(g => {
        // Count stats (from all variations, not filtered)
        g.variations.forEach(v => { if(v.stock>0) inStock++; else oos++; });

        const vars = g.variations.filter(v => {
            if (q && !g.name.toLowerCase().includes(q) &&
                !(g.parentSku||'').toLowerCase().includes(q) &&
                !(v.varName||'').toLowerCase().includes(q) &&
                !(v.varSku||'').toLowerCase().includes(q)) return false;
            if (activeFilter==='in_stock' && v.stock<=5)  return false;
            if (activeFilter==='low'      && (v.stock===0||v.stock>5)) return false;
            if (activeFilter==='oos'      && v.stock!==0)  return false;
            return true;
        });

        if (vars.length > 0) {
            matchedGroups.push({ group: g, matchedVars: vars });
        }
    });

    // Update stock counters (only run once on full data)
    if (!q && activeFilter==='all') {
        document.getElementById('kInStock').textContent = inStock.toLocaleString();
        document.getElementById('kOos').textContent     = oos.toLocaleString();
    }

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

        const isSimple = g.variations.length === 1 && (!g.variations[0].varName || g.variations[0].varName.toLowerCase() === 'main item');

        if (isSimple) {
            const v = vars[0];
            if (v) {
                const vSku = v.varSku
                    ? `<span class="sp-sku-code">${escHtml(v.varSku)}</span>`
                    : `<span class="text-danger" style="font-size:.75rem;font-style:italic">empty</span>`;
                const mId = v.modelId
                    ? `<span class="sp-model-id">${escHtml(String(v.modelId))}</span>`
                    : `<span class="text-secondary">—</span>`;

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
                    <td><span class="sp-item-id">${escHtml(String(g.itemId))}</span></td>
                    <td>${parentSkuHtml}</td>
                    <td>${vSku}</td>
                    <td>${mId}</td>
                    <td class="text-center">${stockPill(v.stock)}</td>
                    <td class="text-end fw-bold" style="font-size:.85rem">₱${v.price.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
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
                <td><span class="sp-item-id">${escHtml(String(g.itemId))}</span></td>
                <td>${parentSkuHtml}</td>
                <td><span class="text-secondary">—</span></td>
                <td><span class="text-secondary">—</span></td>
                <td class="text-center"><span class="text-secondary">—</span></td>
                <td class="text-end"><span class="text-secondary">—</span></td>
            </tr>`;

            // Variation Rows
            vars.forEach(v => {
                const vSku = v.varSku
                    ? `<span class="sp-sku-code">${escHtml(v.varSku)}</span>`
                    : `<span class="text-secondary">—</span>`;
                const mId = v.modelId
                    ? `<span class="sp-model-id">${escHtml(String(v.modelId))}</span>`
                    : `<span class="text-secondary">—</span>`;
                
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
                    <td><span class="text-secondary">—</span></td>
                    <td>${vSku}</td>
                    <td>${mId}</td>
                    <td class="text-center">${stockPill(v.stock)}</td>
                    <td class="text-end fw-bold" style="font-size:.85rem">₱${v.price.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
                </tr>`;
            });
        }
    });

    if (!GROUPS.length) {
        body.innerHTML = `<tr><td colspan="7"><div class="sp-empty">
            <i class="fa-solid fa-bag-shopping d-block"></i>
            <h5>No products synced yet</h5>
            <p>Click <strong>Sync Products</strong> above to fetch your Shopee listings.</p>
        </div></td></tr>`;
        document.getElementById('paginationStatus').textContent = 'Page 1 of 1 (0 items)';
        document.getElementById('paginationButtons').innerHTML = '';
        return;
    }
    if (!totalItems) {
        body.innerHTML = `<tr><td colspan="7"><div class="sp-empty"><i class="fa-solid fa-magnifying-glass d-block"></i><h5>No results</h5><p>Try adjusting your search or filter.</p></div></td></tr>`;
        document.getElementById('paginationStatus').textContent = 'Page 1 of 1 (0 items)';
        document.getElementById('paginationButtons').innerHTML = '';
        return;
    }

    body.innerHTML = html;
    
    // Update labels and pagination status
    document.getElementById('prodCount').innerHTML = `Showing ${start + 1}-${Math.min(end, totalItems)} of ${totalItems} products`;
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

async function syncProducts(btn) {
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Syncing...';
    const statusEl = document.getElementById('syncStatus');
    statusEl.style.display = 'block';

    let totalItems=0, totalRows=0, inserted=0, updated=0, offset=0, hasNext=true;
    try {
        while (hasNext) {
            statusEl.innerHTML = `<div class="sp-alert-card info" style="border-radius:var(--sp-radius-md)"><i class="fa-solid fa-spinner fa-spin"></i><span class="small fw-bold">Fetching page ${Math.floor(offset/50)+1}... (${totalRows} rows so far)</span></div>`;
            const res  = await fetch(`${window.BASE_URL}api/shopee/fetch_products.php?offset=${offset}`);
            const data = await res.json();
            if (data.success) {
                totalItems += data.total_items||0; totalRows += data.total_rows||0;
                inserted += data.inserted||0; updated += data.updated||0;
                hasNext = data.has_next_page; offset = data.next_offset;
            } else {
                statusEl.innerHTML = `<div class="sp-alert-card danger" style="border-radius:var(--sp-radius-md)"><i class="fa-solid fa-circle-xmark"></i><span class="small fw-bold">${data.error}</span></div>`;
                hasNext = false;
                if (typeof EllaToast!=='undefined') EllaToast.error(data.error);
                return;
            }
        }
        statusEl.innerHTML = `<div class="sp-alert-card success" style="border-radius:var(--sp-radius-md)"><i class="fa-solid fa-circle-check"></i><span class="small fw-bold">Sync complete! ${totalRows} rows synced (${inserted} new, ${updated} updated). Reloading...</span></div>`;
        if (typeof EllaToast!=='undefined') EllaToast.success(`Synced ${totalRows} products from Shopee`);
        setTimeout(()=>location.reload(), 1500);
    } catch(e) {
        statusEl.innerHTML = `<div class="sp-alert-card danger" style="border-radius:var(--sp-radius-md)"><i class="fa-solid fa-circle-xmark"></i><span class="small fw-bold">Network error: ${e.message}</span></div>`;
        if (typeof EllaToast!=='undefined') EllaToast.error('Network error');
    } finally {
        btn.disabled = false; btn.innerHTML = orig;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Init stock counters
    let inStock=0, oos=0;
    GROUPS.forEach(g => g.variations.forEach(v => { if(v.stock>0) inStock++; else oos++; }));
    document.getElementById('kInStock').textContent = inStock.toLocaleString();
    document.getElementById('kOos').textContent     = oos.toLocaleString();
    renderProducts();
});
</script>

<?php require_once '../../includes/footer.php'; ?>
