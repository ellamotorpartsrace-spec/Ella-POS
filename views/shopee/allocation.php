<?php
// views/shopee/allocation.php — Stock Allocation (Most Important Page)
$page_title = 'Shopee Sync — Stock Allocation';
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
        <span>Stock Allocation</span>
    </div>

    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="sp-title mb-0"><i class="fa-solid fa-sliders text-shopee me-2"></i>Stock Allocation</h1>
            <p class="sp-subtitle mb-0">Control how much inventory Shopee can sell. <strong>Remaining = Total Stock − Online Stock</strong></p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-shopee" onclick="openBulkModal()"><i class="fa-solid fa-list-check me-2"></i>Bulk Allocate</button>
            <button class="btn btn-shopee" onclick="syncAllAllocations(this)"><i class="fa-solid fa-rotate me-2"></i>Sync All Stock</button>
        </div>
    </div>

    <div class="sp-test-banner bg-success-subtle text-success border-success" id="testBanner">
        <i class="fa-solid fa-sliders"></i>
        <div class="flex-grow-1"><strong>Allocation Ready.</strong> Stock allocations will automatically sync to Shopee.</div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="sp-stat-card accent-shopee">
                <div class="sp-stat-icon" style="background:var(--shopee-light);color:var(--shopee-primary)"><i class="fa-solid fa-boxes-stacked"></i></div>
                <div>
                    <div class="sp-stat-label">Total Stock (POS)</div>
                    <div class="sp-stat-value" id="sumTotal">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="sp-stat-card accent-info">
                <div class="sp-stat-icon" style="background:var(--sp-info-bg);color:var(--sp-info)"><i class="fa-solid fa-globe"></i></div>
                <div>
                    <div class="sp-stat-label">Allocated Online</div>
                    <div class="sp-stat-value text-shopee" id="sumOnline">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="sp-stat-card accent-success">
                <div class="sp-stat-icon" style="background:var(--sp-success-bg);color:var(--sp-success)"><i class="fa-solid fa-store"></i></div>
                <div>
                    <div class="sp-stat-label">Internal Stock</div>
                    <div class="sp-stat-value" id="sumRemaining">0</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="sp-stat-card accent-warning">
                <div class="sp-stat-icon" style="background:var(--sp-warning-bg);color:var(--sp-warning)"><i class="fa-solid fa-percent"></i></div>
                <div>
                    <div class="sp-stat-label">Avg. Allocation</div>
                    <div class="sp-stat-value" id="sumPercent">0%</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="sp-card mb-4">
        <div class="sp-card-body d-flex flex-wrap align-items-center gap-3" style="padding:0.85rem 1.25rem">
            <div class="sp-search flex-grow-1" style="max-width:350px">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="allocSearch" placeholder="Search by SKU or product name..." oninput="filterTable()">
            </div>
            <div class="sp-filter-pills" id="filterPills">
                <button class="sp-pill active" onclick="setFilter('all',this)">All</button>
                <button class="sp-pill" onclick="setFilter('synced',this)">Synced</button>
                <button class="sp-pill" onclick="setFilter('low',this)">Low Stock</button>
                <button class="sp-pill" onclick="setFilter('unallocated',this)">Unallocated</button>
                <button class="sp-pill" onclick="setFilter('over',this)">Over-allocated</button>
            </div>
        </div>
    </div>

    <!-- Main Allocation Table -->
    <div class="sp-card">
        <div class="sp-card-body p-0 sp-table-wrap">
            <table class="sp-table" id="allocTable">
                <thead>
                    <tr>
                        <th style="width:30px"><input type="checkbox" id="checkAll" onchange="toggleAll(this)"></th>
                        <th>SKU & Product</th>
                        <th class="text-center">Total Stock<br><span class="text-muted fw-normal text-capitalize">POS Inventory</span></th>
                        <th class="text-center" style="min-width:160px">Online Stock<br><span class="text-shopee fw-normal text-capitalize">Shopee Allocation</span></th>
                        <th class="text-center">Remaining<br><span class="text-muted fw-normal text-capitalize">Internal Use</span></th>
                        <th class="text-center sp-hide-mobile" style="min-width:130px">Allocation %</th>
                        <th>Sync Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="allocBody">
                    <!-- JS rendered -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════ -->
<!-- EDIT ALLOCATION MODAL -->
<!-- ═══════════════════════════════════════ -->
<div class="modal fade sp-modal" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-sliders text-shopee me-2"></i>Update Allocation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:var(--btn-close-filter)"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="fw-bold fs-5" id="mName">Product</div>
                    <div class="text-secondary small">SKU: <span id="mSku">—</span></div>
                </div>

                <!-- Visual Formula -->
                <div class="d-flex justify-content-between align-items-center p-3 rounded mb-3" style="background:var(--bg-body);border:1px solid var(--border-color)">
                    <div class="text-center">
                        <div class="text-secondary small mb-1">Total Stock</div>
                        <div class="fw-bold fs-4" id="mTotal">0</div>
                    </div>
                    <div class="text-secondary"><i class="fa-solid fa-minus"></i></div>
                    <div class="text-center">
                        <div class="text-shopee small mb-1 fw-bold">Online Stock</div>
                        <div class="sp-qty-control">
                            <button class="sp-qty-btn" onclick="adjQty(-1)"><i class="fa-solid fa-minus"></i></button>
                            <input type="number" class="sp-qty-input" id="mOnline" value="0" min="0" oninput="calcModal()">
                            <button class="sp-qty-btn" onclick="adjQty(1)"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </div>
                    <div class="text-secondary"><i class="fa-solid fa-equals"></i></div>
                    <div class="text-center">
                        <div class="text-secondary small mb-1">Remaining</div>
                        <div class="fw-bold fs-4" id="mRemaining">0</div>
                    </div>
                </div>

                <!-- Slider -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary">Allocation Slider</label>
                    <input type="range" class="sp-allocation-slider w-100" id="mSlider" min="0" max="100" value="0" oninput="sliderChange()">
                    <div class="d-flex justify-content-between small text-secondary mt-1">
                        <span>0%</span><span id="mSliderVal">0%</span><span>100%</span>
                    </div>
                </div>

                <!-- Quick Presets -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-secondary">Quick Presets</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="sp-pill" onclick="presetPercent(5)">5%</button>
                        <button class="sp-pill" onclick="presetPercent(10)">10%</button>
                        <button class="sp-pill" onclick="presetPercent(15)">15%</button>
                        <button class="sp-pill" onclick="presetPercent(20)">20%</button>
                        <button class="sp-pill" onclick="presetPercent(25)">25%</button>
                        <button class="sp-pill" onclick="presetPercent(50)">50%</button>
                    </div>
                </div>

                <!-- Warning -->
                <div id="mWarning" class="d-none alert py-2 small mb-0">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><span id="mWarnText"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-shopee" onclick="saveAllocation(this)"><i class="fa-solid fa-check me-2"></i>Save & Sync</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════ -->
<!-- BULK ALLOCATION MODAL -->
<!-- ═══════════════════════════════════════ -->
<div class="modal fade sp-modal" id="bulkModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-layer-group text-shopee me-2"></i>Bulk Allocation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:var(--btn-close-filter)"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary small">Apply allocation to all selected products at once.</p>

                <div class="mb-3">
                    <label class="form-label fw-bold small">Allocation Method</label>
                    <select class="form-select" id="bulkMethod" onchange="toggleBulkInput()">
                        <option value="percent">Percentage of Total Stock</option>
                        <option value="fixed">Fixed Quantity</option>
                    </select>
                </div>

                <div class="mb-3" id="bulkPercentGroup">
                    <label class="form-label fw-bold small">Percentage (%)</label>
                    <input type="number" class="form-control" id="bulkPercent" value="10" min="0" max="100">
                </div>
                <div class="mb-3 d-none" id="bulkFixedGroup">
                    <label class="form-label fw-bold small">Fixed Quantity</label>
                    <input type="number" class="form-control" id="bulkFixed" value="10" min="0">
                </div>

                <div class="alert alert-warning py-2 small">
                    <i class="fa-solid fa-info-circle me-2"></i>
                    Products with insufficient stock will be capped automatically.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-shopee" onclick="applyBulk()"><i class="fa-solid fa-check me-2"></i>Apply to Selected</button>
            </div>
        </div>
    </div>
</div>

<script>
<?php
$db = new Database();
$conn = $db->getConnection();
// Join with products table to get POS stock for mapped items
$stmt = $conn->query("
    SELECT m.*, COALESCE(i.quantity, 0) as pos_qty, v.sku as pos_sku, 
    CONCAT(p.product_name, IF(v.variation_name != '', CONCAT(' (', v.variation_name, ')'), '')) as pos_name,
    'synced' as sync_status
    FROM shopee_product_mappings m 
    LEFT JOIN product_variations v ON m.pos_product_id = v.variation_id 
    LEFT JOIN products p ON v.product_id = p.product_id
    LEFT JOIN inventory i ON v.variation_id = i.variation_id AND i.store_id = 1
    WHERE m.mapping_status IN ('auto', 'manual')
    ORDER BY m.shopee_product_name ASC
");
$shopeeItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$productsJson = [];
foreach ($shopeeItems as $item) {
    $status = $item['sync_status'];
    if ($status === 'synced' && $item['shopee_stock'] <= 5) $status = 'low';

    $productsJson[] = [
        'id'     => (int) $item['id'],
        'name'   => $item['shopee_product_name'] . ($item['shopee_variation_name'] ? ' — ' . $item['shopee_variation_name'] : ''),
        'sku'    => $item['matched_pos_sku'] ?: ($item['shopee_variation_sku'] ?: $item['shopee_parent_sku']),
        'total'  => (int) ($item['pos_qty'] ?? 0),
        'online' => (int) $item['shopee_stock'],
        'status' => $status
    ];
}
?>
const PRODUCTS = <?= json_encode($productsJson) ?>;

let currentEdit = null;
let editModalInst, bulkModalInst;
let activeFilter = 'all';

document.addEventListener('DOMContentLoaded', () => {
    if (typeof bootstrap !== 'undefined') {
        const editEl = document.getElementById('editModal');
        const bulkEl = document.getElementById('bulkModal');
        if (editEl) editModalInst = new bootstrap.Modal(editEl);
        if (bulkEl) bulkModalInst = new bootstrap.Modal(bulkEl);
    }
    renderTable();
});

// ── Render Table ──
function renderTable() {
    const search = (document.getElementById('allocSearch')?.value || '').toLowerCase();
    const body = document.getElementById('allocBody');
    let html = '';
    let filtered = PRODUCTS.filter(p => {
        if (search && !p.name.toLowerCase().includes(search) && !p.sku.toLowerCase().includes(search)) return false;
        if (activeFilter === 'synced' && p.status !== 'synced') return false;
        if (activeFilter === 'low' && p.status !== 'low') return false;
        if (activeFilter === 'unallocated' && p.online !== 0) return false;
        if (activeFilter === 'over' && p.online < p.total) return false;
        return true;
    });

    if (filtered.length === 0) {
        body.innerHTML = `<tr><td colspan="8"><div class="sp-empty"><i class="fa-solid fa-filter d-block"></i><h5>No products found</h5><p>Try adjusting your search or filter.</p></div></td></tr>`;
        updateSummary();
        return;
    }

    filtered.forEach(p => {
        const rem = p.total - p.online;
        const pct = p.total > 0 ? Math.round((p.online / p.total) * 100) : 0;
        const barClass = pct > 80 ? 'critical' : pct > 50 ? 'low' : 'healthy';

        let remClass = '';
        if (rem < 0) remClass = 'text-danger fw-bold';
        else if (rem <= 5) remClass = 'text-warning fw-bold';
        else remClass = 'fw-bold';

        let badge = '';
        if (p.online === 0) badge = '<span class="sp-badge sp-badge-neutral"><i class="fa-solid fa-minus-circle"></i> Unallocated</span>';
        else if (rem < 0) badge = '<span class="sp-badge sp-badge-danger"><i class="fa-solid fa-exclamation"></i> Over-allocated</span>';
        else if (p.status === 'low') badge = '<span class="sp-badge sp-badge-warning"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock</span>';
        else badge = '<span class="sp-badge sp-badge-success"><i class="fa-solid fa-check"></i> Synced</span>';

        html += `<tr data-id="${p.id}">
            <td><input type="checkbox" class="row-check" value="${p.id}"></td>
            <td>
                <div class="fw-bold">${p.name}</div>
                <div class="small text-secondary">${p.sku}</div>
            </td>
            <td class="text-center fw-bold fs-6">${p.total.toLocaleString()}</td>
            <td class="text-center">
                <span class="fw-bold fs-6 text-shopee">${p.online.toLocaleString()}</span>
            </td>
            <td class="text-center ${remClass} fs-6">${rem.toLocaleString()}</td>
            <td class="text-center sp-hide-mobile">
                <div class="d-flex align-items-center gap-2 justify-content-center">
                    <div class="sp-stock-bar-wrap">
                        <div class="sp-stock-bar"><div class="sp-stock-bar-fill ${barClass}" style="width:${Math.min(pct,100)}%"></div></div>
                    </div>
                    <span class="small fw-bold" style="min-width:32px">${pct}%</span>
                </div>
            </td>
            <td>${badge}</td>
            <td class="text-end">
                <button class="btn btn-sm btn-outline-shopee px-3" onclick="openEdit(${p.id})">
                    <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                </button>
            </td>
        </tr>`;
    });
    body.innerHTML = html;
    updateSummary();
}

function updateSummary() {
    const totals = PRODUCTS.reduce((acc, p) => {
        acc.total += p.total; acc.online += p.online;
        return acc;
    }, { total: 0, online: 0 });
    document.getElementById('sumTotal').textContent = totals.total.toLocaleString();
    document.getElementById('sumOnline').textContent = totals.online.toLocaleString();
    document.getElementById('sumRemaining').textContent = (totals.total - totals.online).toLocaleString();
    document.getElementById('sumPercent').textContent = (totals.total > 0 ? ((totals.online / totals.total) * 100).toFixed(1) : 0) + '%';
}

// ── Filters ──
function setFilter(f, btn) {
    activeFilter = f;
    document.querySelectorAll('.sp-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    renderTable();
}
function filterTable() { renderTable(); }

// ── Edit Modal ──
function openEdit(id) {
    currentEdit = PRODUCTS.find(p => p.id === id);
    document.getElementById('mName').textContent = currentEdit.name;
    document.getElementById('mSku').textContent = currentEdit.sku;
    document.getElementById('mTotal').textContent = currentEdit.total;
    document.getElementById('mOnline').value = currentEdit.online;
    document.getElementById('mSlider').max = currentEdit.total;
    document.getElementById('mSlider').value = currentEdit.online;
    calcModal();
    editModalInst.show();
}

function adjQty(d) {
    const inp = document.getElementById('mOnline');
    let v = parseInt(inp.value) || 0;
    v = Math.max(0, v + d);
    inp.value = v;
    document.getElementById('mSlider').value = v;
    calcModal();
}

function sliderChange() {
    const v = parseInt(document.getElementById('mSlider').value);
    document.getElementById('mOnline').value = v;
    calcModal();
}

function presetPercent(pct) {
    const v = Math.floor(currentEdit.total * pct / 100);
    document.getElementById('mOnline').value = v;
    document.getElementById('mSlider').value = v;
    calcModal();
}

function calcModal() {
    const total = currentEdit.total;
    const online = parseInt(document.getElementById('mOnline').value) || 0;
    const rem = total - online;
    const pct = total > 0 ? Math.round((online / total) * 100) : 0;

    const remEl = document.getElementById('mRemaining');
    const warn = document.getElementById('mWarning');
    const warnText = document.getElementById('mWarnText');

    remEl.textContent = rem;
    document.getElementById('mSliderVal').textContent = pct + '%';

    if (rem < 0) {
        remEl.className = 'fw-bold fs-4 text-danger';
        warn.className = 'alert alert-danger py-2 small mb-0';
        warnText.textContent = 'Cannot allocate more than available stock (' + total + ')';
    } else if (rem <= 5 && rem >= 0) {
        remEl.className = 'fw-bold fs-4 text-warning';
        warn.className = 'alert alert-warning py-2 small mb-0';
        warnText.textContent = 'Warning: Store will have very little internal stock remaining.';
    } else {
        remEl.className = 'fw-bold fs-4 text-success';
        warn.className = 'd-none';
    }
}

async function saveAllocation(btnEl) {
    const online = parseInt(document.getElementById('mOnline').value) || 0;
    if (online > currentEdit.total) {
        EllaToast.error('Cannot allocate more than total stock.');
        return;
    }

    const btn = btnEl || (typeof event !== 'undefined' ? event.currentTarget : null);
    const originalText = btn ? btn.innerHTML : 'Save & Sync';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Syncing...';
    }

    try {
        const res = await fetch(`${window.BASE_URL}api/shopee/update_allocation.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: currentEdit.id, online_stock: online })
        });
        const data = await res.json();
        if (data.success) {
            currentEdit.online = online;
            currentEdit.status = online === 0 ? 'unallocated' : (currentEdit.total - online <= 5 ? 'low' : 'synced');
            renderTable();
            editModalInst.hide();
            EllaToast.success(data.message);
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

// ── Bulk ──
function toggleAll(cb) { document.querySelectorAll('.row-check').forEach(c => c.checked = cb.checked); }
function openBulkModal() { bulkModalInst.show(); }
function toggleBulkInput() {
    const m = document.getElementById('bulkMethod').value;
    document.getElementById('bulkPercentGroup').classList.toggle('d-none', m !== 'percent');
    document.getElementById('bulkFixedGroup').classList.toggle('d-none', m !== 'fixed');
}

function applyBulk() {
    const checked = [...document.querySelectorAll('.row-check:checked')].map(c => parseInt(c.value));
    if (checked.length === 0) {
        if (typeof EllaToast !== 'undefined') EllaToast.warning('Please select at least one product.');
        return;
    }
    const method = document.getElementById('bulkMethod').value;
    const pct = parseInt(document.getElementById('bulkPercent').value) || 0;
    const fixed = parseInt(document.getElementById('bulkFixed').value) || 0;

    checked.forEach(id => {
        const p = PRODUCTS.find(x => x.id === id);
        if (!p) return;
        let newOnline = method === 'percent' ? Math.floor(p.total * pct / 100) : Math.min(fixed, p.total);
        p.online = newOnline;
        p.status = newOnline === 0 ? 'unallocated' : (p.total - newOnline <= 5 ? 'low' : 'synced');
    });

    renderTable();
    bulkModalInst.hide();
    if (typeof EllaToast !== 'undefined') EllaToast.success(`Bulk allocation applied to ${checked.length} products`);
}

async function syncAllAllocations(btnEl) {
    const btn = btnEl || (typeof event !== 'undefined' ? event.currentTarget : null);
    const originalText = btn ? btn.innerHTML : 'Sync All Stock';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Syncing All...';
    }

    try {
        // For bulk sync, we'll just trigger the fetch products which also updates mappings if we implement it that way
        // But better is to loop through and sync each or have a bulk sync API.
        // For now, let's just trigger a full product import/sync.
        const res = await fetch(`${window.BASE_URL}api/shopee/fetch_products.php`);
        const data = await res.json();
        if (data.success) {
            EllaToast.success('All stock levels synced to Shopee');
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
