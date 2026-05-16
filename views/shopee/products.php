<?php
// views/shopee/products.php — Shopee Products
$page_title = 'Shopee Sync — Products';
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
    .sku-type-badge { font-size:0.6rem; padding:0.15rem 0.4rem; border-radius:4px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; }
    .sku-type-variation { background:var(--sp-info-bg); color:var(--sp-info); }
    .sku-type-parent { background:var(--sp-warning-bg); color:var(--sp-warning); }
</style>

<div class="sp-page sp-animate">
    <div class="sp-breadcrumb">
        <a href="<?= BASE_URL ?>views/shopee/index.php">Shopee Sync</a>
        <i class="fa-solid fa-chevron-right" style="font-size:0.6rem"></i>
        <span>Shopee Products</span>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="sp-title mb-0"><i class="fa-solid fa-bag-shopping text-shopee me-2"></i>Shopee Products</h1>
            <p class="sp-subtitle mb-0">Imported products from your Shopee store</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-shopee" onclick="importProducts(this)"><i class="fa-solid fa-cloud-arrow-down me-2"></i>Import from Shopee</button>
            <button class="btn btn-shopee" onclick="syncNow(this)"><i class="fa-solid fa-rotate me-2"></i>Sync All</button>
        </div>
    </div>

    <div class="sp-test-banner bg-success-subtle text-success border-success">
        <i class="fa-solid fa-cloud-arrow-down"></i>
        <div class="flex-grow-1">Displaying actual products imported from Shopee.</div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="sp-stat-card accent-shopee">
                <div class="sp-stat-icon" style="background:var(--shopee-light);color:var(--shopee-primary)"><i class="fa-solid fa-cloud-arrow-down"></i></div>
                <div><div class="sp-stat-label">Total Fetched</div><div class="sp-stat-value" id="kpiTotal">0</div></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="sp-stat-card accent-success">
                <div class="sp-stat-icon" style="background:var(--sp-success-bg);color:var(--sp-success)"><i class="fa-solid fa-link"></i></div>
                <div><div class="sp-stat-label">Mapped</div><div class="sp-stat-value" id="kpiMapped">0</div></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="sp-stat-card accent-warning">
                <div class="sp-stat-icon" style="background:var(--sp-warning-bg);color:var(--sp-warning)"><i class="fa-solid fa-link-slash"></i></div>
                <div><div class="sp-stat-label">Unmapped</div><div class="sp-stat-value" id="kpiUnmapped">0</div></div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="sp-stat-card accent-danger">
                <div class="sp-stat-icon" style="background:var(--sp-danger-bg);color:var(--sp-danger)"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div><div class="sp-stat-label">Issues</div><div class="sp-stat-value" id="kpiIssues">0</div></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="sp-card mb-4">
        <div class="sp-card-body d-flex flex-wrap align-items-center gap-3" style="padding:0.85rem 1.25rem">
            <div class="sp-search flex-grow-1" style="max-width:350px">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="prodSearch" placeholder="Search products..." oninput="renderProducts()">
            </div>
            <div class="sp-filter-pills">
                <button class="sp-pill active" onclick="setProdFilter('all',this)">All</button>
                <button class="sp-pill" onclick="setProdFilter('mapped',this)"><i class="fa-solid fa-link me-1"></i>Mapped</button>
                <button class="sp-pill" onclick="setProdFilter('unmapped',this)"><i class="fa-solid fa-link-slash me-1"></i>Unmapped</button>
                <button class="sp-pill" onclick="setProdFilter('with_var',this)">With Variations</button>
                <button class="sp-pill" onclick="setProdFilter('no_var',this)">No Variations</button>
            </div>
        </div>
    </div>

    <!-- Products Table -->
    <div class="sp-card">
        <div class="sp-card-body p-0 sp-table-wrap">
            <table class="sp-table">
                <thead>
                    <tr>
                        <th style="width:40px">Img</th>
                        <th>Shopee Product</th>
                        <th>Variation</th>
                        <th class="sp-hide-mobile">Model ID</th>
                        <th>Parent SKU</th>
                        <th>Variation SKU</th>
                        <th class="text-center">Stock</th>
                        <th class="text-center">Price</th>
                        <th>Fetch Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="prodBody"></tbody>
            </table>
        </div>
    </div>
</div>

<?php
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("
    SELECT *
    FROM shopee_product_mappings 
    ORDER BY shopee_product_name ASC, shopee_variation_name ASC
");
$shopeeItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$productsJson = [];
foreach ($shopeeItems as $item) {
    $img = $item['shopee_image_url'] ? '<img src="'.htmlspecialchars($item['shopee_image_url']).'" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">' : '📦';

    $productsJson[] = [
        'id'        => (int) $item['id'],
        'img'       => $img,
        'name'      => $item['shopee_product_name'],
        'variation' => $item['shopee_variation_name'],
        'modelId'   => $item['shopee_model_id'] ? 'SP-'.$item['shopee_model_id'] : 'SP-'.$item['shopee_item_id'],
        'parentSku' => $item['shopee_parent_sku'],
        'varSku'    => $item['shopee_variation_sku'],
        'hasVar'    => (bool) $item['has_variation'],
        'mapped'    => in_array($item['mapping_status'], ['auto','manual']),
        'stock'     => (int) $item['shopee_stock'],
        'price'     => (float) $item['shopee_price'],
        'status'    => $item['mapping_status']
    ];
}
?>
<script>
const PRODUCTS = <?= json_encode($productsJson) ?>;

let prodFilter = 'all';

function setProdFilter(f, btn) {
    prodFilter = f;
    document.querySelectorAll('.sp-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    renderProducts();
}

function renderProducts() {
    const search = (document.getElementById('prodSearch')?.value || '').toLowerCase();
    const body = document.getElementById('prodBody');

    let mappedCount = 0;
    let unmappedCount = 0;
    let issueCount = 0;

    let items = PRODUCTS.filter(p => {
        if (p.mapped) mappedCount++;
        else if (p.status === 'missing_sku' || p.status === 'duplicate') issueCount++;
        else unmappedCount++;

        if (search && !p.name.toLowerCase().includes(search) && !(p.parentSku||'').toLowerCase().includes(search) && !(p.varSku||'').toLowerCase().includes(search)) return false;
        if (prodFilter === 'mapped' && !p.mapped) return false;
        if (prodFilter === 'unmapped' && p.mapped) return false;
        if (prodFilter === 'with_var' && !p.hasVar) return false;
        if (prodFilter === 'no_var' && p.hasVar) return false;
        return true;
    });

    document.getElementById('kpiTotal').textContent = PRODUCTS.length;
    document.getElementById('kpiMapped').textContent = mappedCount;
    document.getElementById('kpiUnmapped').textContent = unmappedCount;
    document.getElementById('kpiIssues').textContent = issueCount;

    if (!items.length) {
        body.innerHTML = '<tr><td colspan="10"><div class="sp-empty"><i class="fa-solid fa-bag-shopping d-block"></i><h5>No products found</h5><p>Adjust filters or import from Shopee.</p></div></td></tr>';
        return;
    }

    body.innerHTML = items.map(p => {
        // Fetch status badge (Mapping Status in DB)
        let badge = '';
        switch(p.status) {
            case 'auto':
            case 'manual':     badge = '<span class="sp-badge sp-badge-success"><i class="fa-solid fa-link"></i> Mapped</span>'; break;
            case 'unmapped':   badge = '<span class="sp-badge sp-badge-warning"><i class="fa-solid fa-link-slash"></i> Unmapped</span>'; break;
            case 'missing_sku':badge = '<span class="sp-badge sp-badge-danger"><i class="fa-solid fa-triangle-exclamation"></i> Missing SKU</span>'; break;
            case 'duplicate':  badge = '<span class="sp-badge sp-badge-danger"><i class="fa-solid fa-clone"></i> Duplicate</span>'; break;
            default:           badge = '<span class="sp-badge sp-badge-neutral">' + p.status + '</span>';
        }

        return `<tr>
            <td><div style="width:32px;height:32px;background:var(--sp-neutral-bg);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1rem">${p.img}</div></td>
            <td><div class="fw-bold text-truncate" style="max-width:200px">${p.name}</div></td>
            <td>${p.variation ? `<span class="small">${p.variation}</span>` : '<span class="text-secondary fst-italic">None</span>'}</td>
            <td class="sp-hide-mobile"><code class="small">${p.modelId}</code></td>
            <td><code class="small">${p.parentSku || '<span class="text-danger">empty</span>'}</code></td>
            <td>${p.varSku ? `<code class="small">${p.varSku}</code>` : '<span class="text-secondary">—</span>'}</td>
            <td class="text-center fw-bold text-shopee">${p.stock.toLocaleString()}</td>
            <td class="text-center">₱${p.price.toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
            <td>${badge}</td>
            <td class="text-end">
                <div class="d-flex gap-1 justify-content-end">
                    <button class="btn btn-sm btn-ghost" onclick="syncIndividual(${p.id}, this)" title="Sync Now"><i class="fa-solid fa-rotate"></i></button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

document.addEventListener('DOMContentLoaded', renderProducts);

async function importProducts(btn) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Importing...';

    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/fetch_products.php`);
        const data = await res.json();
        if (data.success) {
            EllaToast.success('Product import completed');
            location.reload(); // Refresh to show new items
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

async function syncIndividual(id, btn) {
    const icon = btn.querySelector('i');
    icon.classList.add('fa-spin');
    btn.disabled = true;

    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/sync_individual.php?id=${id}`);
        const data = await res.json();
        if (data.success) {
            EllaToast.success('Sync successful');
        } else {
            EllaToast.error(data.error);
        }
    } catch (e) {
        EllaToast.error('Network error');
    } finally {
        icon.classList.remove('fa-spin');
        btn.disabled = false;
    }
}

async function syncNow(btn) { 
    if (!btn) btn = event?.currentTarget;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Syncing...';

    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/fetch_products.php`);
        const data = await res.json();
        if (data.success) {
            EllaToast.success('Full sync completed');
            location.reload();
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
</script>

<?php require_once '../../includes/footer.php'; ?>
