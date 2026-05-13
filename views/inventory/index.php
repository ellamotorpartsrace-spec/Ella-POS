<?php
// views/inventory/index.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    denyAccess("You do not have permission to access inventory.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// --- PAGINATION & SEARCH LOGIC ---
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 100;
$offset = ($page - 1) * $limit;

// Base Query
$baseSql = "
    FROM product_variations v
    JOIN products p ON v.product_id = p.product_id
    LEFT JOIN inventory i_phys ON v.variation_id = i_phys.variation_id AND i_phys.store_id = 1
    LEFT JOIN inventory i_online ON v.variation_id = i_online.variation_id AND i_online.store_id = 2
    WHERE v.status = 'active'
";

// Filters
$params = [];

if (!empty($search)) {
    $words = preg_split('/\s+/', $search);
    $validWords = [];
    foreach ($words as $word) {
        $word = trim($word);
        if (strlen($word) >= 1) {
            $validWords[] = $word;
        }
    }

    $wordConditions = [];
    if (!empty($validWords)) {
        foreach ($validWords as $idx => $word) {
            $wordConditions[] = "(p.product_name LIKE ? OR p.brand_name LIKE ? OR v.sku LIKE ? OR v.barcode LIKE ? OR v.variation_name LIKE ?)";
            $term = "%$word%";
            // Push term 5 times for 5 placeholders
            array_push($params, $term, $term, $term, $term, $term);
        }
    }

    if (!empty($wordConditions)) {
        // Also check if the entire search string is an exact barcode
        $baseSql .= " AND (v.barcode = ? OR (" . implode(' AND ', $wordConditions) . "))";
        array_unshift($params, $search); // Provide exactly one ? argument before the word loop
    }
}

if ($filter === 'low_stock') {
    $baseSql .= " AND COALESCE(i_phys.quantity, 0) <= v.low_stock_threshold";
}

// 1. Fetch Stats (Aggregates) for Info Cards
// We do this BEFORE the limit so the cards show totals for the whole search result
$sqlStats = "
    SELECT 
        COUNT(*) as total_items,
        SUM(v.price_capital * (COALESCE(i_phys.quantity, 0) + COALESCE(i_online.quantity, 0))) as total_asset_value,
        SUM(CASE WHEN COALESCE(i_phys.quantity, 0) <= v.low_stock_threshold THEN 1 ELSE 0 END) as low_stock_count
    " . $baseSql;

$stmtStats = $conn->prepare($sqlStats);
$stmtStats->execute($params);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

$total_items = $stats['total_items'] ?? 0;
$total_asset_value = $stats['total_asset_value'] ?? 0;
$low_stock_count = $stats['low_stock_count'] ?? 0;
$total_pages = ceil($total_items / $limit);

// 2. Fetch Paginated Data
$sqlProducts = "
    SELECT v.variation_id, v.variation_name, v.sku, v.unit_type,
           v.price_capital, v.price_retail, v.status, v.low_stock_threshold,
           p.product_name, p.brand_name, p.image_path,
           COALESCE(i_phys.quantity, 0) as physical_stock,
           COALESCE(i_online.quantity, 0) as online_stock,
           COALESCE(i_phys.quantity, 0) + COALESCE(i_online.quantity, 0) as current_stock
    " . $baseSql . "
    ORDER BY p.product_name ASC
    LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($sqlProducts);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<style>
    /* Mobile-first responsive styles */
    .inventory-card {
        transition: all 0.2s ease;
        border-left: 4px solid transparent;
    }

    .inventory-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    /* Status Colors for Borders */
    .inventory-card.status-active {
        border-left-color: var(--bs-success);
    }

    .inventory-card.status-inactive {
        border-left-color: var(--bs-secondary);
    }

    .inventory-card.stock-out {
        border-left-color: var(--bs-danger);
    }

    .inventory-card.stock-low {
        border-left-color: var(--bs-warning);
    }

    /* Multi-line truncation using line-clamp */
    .text-line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        word-break: break-word;
    }

    /* Responsiveness Logic */
    @media (min-width: 992px) {
        .mobile-cards {
            display: none !important;
        }

        .desktop-table {
            display: block !important;
        }
    }

    @media (max-width: 991.98px) {
        .mobile-cards {
            display: block !important;
        }

        .desktop-table {
            display: none !important;
        }

        .capital-col {
            display: none !important;
            /* Always hide capital in mobile cards to save space */
        }

        /* Prevent button overflow on very small devices */
        .mobile-card-footer {
            flex-wrap: wrap !important;
            gap: 0.75rem;
        }

        .mobile-card-footer>div {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        @media (max-width: 350px) {
            .mobile-card-footer {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .mobile-card-footer>div {
                justify-content: flex-start;
                gap: 0.5rem;
                flex-wrap: wrap;
            }
        }
    }
</style>

<div class="container-fluid p-4">

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-start border-4 border-primary h-100">
                <div class="card-body">
                    <div class="text-secondary small text-uppercase fw-bold">Total Products</div>
                    <div class="h3 mb-0 fw-bold" style="color: var(--text-primary);"><?= number_format($total_items) ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <div class="col-md-4 capital-col">
                <div class="card shadow-sm border-start border-4 border-success h-100">
                    <div class="card-body">
                        <div class="text-secondary small text-uppercase fw-bold">Total Stock Value (Cost)</div>
                        <div class="h3 mb-0 fw-bold" style="color: var(--text-primary);">
                            ₱<?= number_format($total_asset_value, 2) ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="col-md-4">
            <a href="index.php?filter=low_stock" class="text-decoration-none">
                <div
                    class="card shadow-sm border-start border-4 border-danger h-100 <?= $filter === 'low_stock' ? 'bg-danger-subtle' : '' ?> transition-hover">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-secondary small text-uppercase fw-bold">Low Stock Items</div>
                                <div class="h3 mb-0 text-danger fw-bold"><?= number_format($low_stock_count) ?></div>
                            </div>
                            <i class="fa-solid fa-triangle-exclamation fa-2x text-danger opacity-25"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body d-lg-flex justify-content-between align-items-center">

            <div class="d-flex mb-3 mb-lg-0" style="max-width: 450px; width: 100%;">
                <div class="input-group">
                    <span class="input-group-text"
                        style="background: var(--bg-surface); border-color: var(--border-color);">
                        <i class="fa-solid fa-barcode" style="color: var(--text-secondary);"></i>
                    </span>
                    <input type="text" id="inventory-search" class="form-control"
                        placeholder="Search products, brand, SKU, barcode..."
                        value="<?= htmlspecialchars($search ?? '') ?>" autofocus>
                    <span class="input-group-text d-none" id="search-spinner"
                        style="background: var(--bg-surface); border-color: var(--border-color);">
                        <i class="fa-solid fa-spinner fa-spin text-primary"></i>
                    </span>
                    <?php if (!empty($filter)): ?>
                        <a href="index.php" class="btn btn-outline-danger" title="Clear Low Stock Filter"><i
                                class="fa-solid fa-xmark"></i></a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-lg-end">
                <a href="batch_upload.php" class="btn btn-outline-primary" title="Batch Upload from CSV">
                    <i class="fa-solid fa-file-csv"></i> Batch Upload
                </a>
                <a href="archived.php" class="btn btn-outline-secondary" title="View Archived Products">
                    <i class="fa-solid fa-box-archive"></i> Archived
                </a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="mass_update.php" class="btn btn-outline-warning" title="Mass Update Products">
                        <i class="fa-solid fa-pen-to-square"></i> Mass Update
                    </a>
                <?php endif; ?>
                <a href="../../api/inventory/export_products.php?search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>"
                    class="btn btn-outline-success" id="export-csv-btn" title="Export to CSV">
                    <i class="fa-solid fa-file-export"></i> Export
                </a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <button class="btn btn-outline-secondary" id="btnToggleCost" title="Show/Hide Capital Cost">
                        <i class="fa-solid fa-eye-slash"></i>
                    </button>
                <?php endif; ?>

                <a href="restock.php" class="btn btn-outline-dark">
                    <i class="fa-solid fa-truck-ramp-box"></i> Restock
                </a>
                <a href="create.php" class="btn btn-success fw-bold px-4">
                    <i class="fa-solid fa-plus"></i> Add New
                </a>
            </div>

        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header py-3" style="background: var(--card-bg); border-bottom: 1px solid var(--border-color);">
            <h5 class="mb-0 fw-bold" style="color: var(--text-primary);"><i
                    class="fa-solid fa-boxes-stacked text-primary"></i> Master Inventory List</h5>
        </div>
        <div class="card-body p-0 desktop-table">
            <div class="table-responsive">
                <table id="inventory-table" class="table table-hover align-middle mb-0">
                    <thead style="background: var(--bg-surface); border-bottom: 2px solid var(--border-color);">
                        <tr>
                            <th class="ps-4" style="color: var(--text-primary);">Product Detail</th>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <th class="capital-col" style="color: var(--text-primary);">Cost (Capital)</th>
                            <?php endif; ?>
                            <th style="color: var(--text-primary);">SRP (Retail)</th>
                            <th style="color: var(--text-primary);">Stock Level</th>
                            <th style="color: var(--text-primary);">Status</th>
                            <th class="text-end pe-4" style="color: var(--text-primary);">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $row): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded d-flex align-items-center justify-content-center me-3 overflow-hidden border"
                                                style="width: 40px; height: 40px; background: var(--bg-surface); border-color: var(--border-color) !important;">
                                                <?php if (!empty($row['image_path'])): ?>
                                                    <img src="<?= BASE_URL . $row['image_path'] ?>"
                                                        style="width:100%; height:100%; object-fit:cover;">
                                                <?php else: ?>
                                                    <i class="fa-solid fa-cube text-secondary fa-lg"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold mb-0" style="color: var(--text-primary);">
                                                    <?= htmlspecialchars($row['product_name'] ?? '') ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($row['brand_name'] ?? '') ?> |
                                                    <span
                                                        class="text-primary"><?= htmlspecialchars($row['variation_name'] ?? '') ?></span>
                                                </small>
                                                <?php if (!empty($row['sku'])): ?>
                                                    <div class="badge border ms-1"
                                                        style="background: var(--bg-surface); color: var(--text-secondary); border-color: var(--border-color) !important;">
                                                        <?= htmlspecialchars($row['sku']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                        <td class="capital-col">
                                            <span class="text-muted small">₱</span> <?= number_format($row['price_capital'], 2) ?>
                                        </td>
                                    <?php endif; ?>

                                    <td>
                                        <span class="text-success fw-bold small">₱</span> <span class="fw-bold"
                                            style="color: var(--text-primary);"><?= number_format($row['price_retail'], 2) ?></span>
                                    </td>

                                    <td>
                                        <?php
                                        $qty = $row['current_stock'];
                                        $phys = $row['physical_stock'] ?? $qty;
                                        $online = $row['online_stock'] ?? 0;
                                        $thresh = $row['low_stock_threshold'];

                                        if ($qty == 0) {
                                            echo '<span class="badge bg-danger-subtle text-danger border border-danger"><i class="fa-solid fa-circle-xmark"></i> Out</span>';
                                        } elseif ($phys <= $thresh) {
                                            echo '<span class="badge bg-warning-subtle text-dark border border-warning" title="Physical: ' . $phys . ' | Online: ' . $online . '"><i class="fa-solid fa-triangle-exclamation"></i> Low: ' . $qty . '</span>';
                                        } else {
                                            echo '<span class="badge bg-success-subtle text-success border border-success" title="Physical: ' . $phys . ' | Online: ' . $online . '">' . $qty . ' ' . $row['unit_type'] . '</span>';
                                        }
                                        ?>
                                        <?php if ($online > 0): ?>
                                            <span class="badge bg-info-subtle text-info border border-info ms-1"
                                                title="Online Allocation">
                                                <i class="fa-solid fa-globe"></i> <?= $online ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if (($row['status'] ?? '') === 'active'): ?>
                                            <span class="text-success small"><i class="fa-solid fa-circle fa-xs"></i> Active</span>
                                        <?php else: ?>
                                            <span class="text-secondary small"><i class="fa-solid fa-ban fa-xs"></i> Inactive</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="edit.php?id=<?= $row['variation_id'] ?>"
                                                class="btn btn-sm btn-outline-primary" title="Update">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <a href="history.php?id=<?= $row['variation_id'] ?>"
                                                class="btn btn-sm btn-outline-info" title="History">
                                                <i class="fa-solid fa-clock-rotate-left"></i>
                                            </a>
                                            <button
                                                onclick="openAllocateModal(<?= $row['variation_id'] ?>, '<?= htmlspecialchars(addslashes($row['product_name'])) ?>', <?= $phys ?>, <?= $online ?>)"
                                                class="btn btn-sm btn-outline-success" title="Allocate">
                                                <i class="fa-solid fa-right-left"></i>
                                            </button>
                                            <button onclick="confirmDelete(<?= $row['variation_id'] ?>)"
                                                class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <h5 class="text-secondary">No products found</h5>
                                    <a href="create.php" class="btn btn-sm btn-primary mt-2">Add First Product</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile Card View -->
        <div class="card-body p-3 mobile-cards" style="display: none;">
            <div id="mobile-cards-container" class="d-flex flex-column gap-3">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $row):
                        $qty = $row['current_stock'];
                        $statusClass = $qty == 0 ? 'stock-out' : ($qty <= $row['low_stock_threshold'] ? 'stock-low' : 'status-active');
                        if ($row['status'] !== 'active')
                            $statusClass = 'status-inactive';
                        ?>
                        <div class="card inventory-card <?= $statusClass ?> border-0 shadow-sm">
                            <div class="card-body p-3">
                                <div class="d-flex mb-3">
                                    <!-- Image -->
                                    <div class="rounded d-flex align-items-center justify-content-center me-3 overflow-hidden border flex-shrink-0"
                                        style="width: 60px; height: 60px; background: var(--bg-surface);">
                                        <?php if (!empty($row['image_path'])): ?>
                                            <img src="<?= BASE_URL . $row['image_path'] ?>"
                                                style="width:100%; height:100%; object-fit:cover;">
                                        <?php else: ?>
                                            <i class="fa-solid fa-cube text-secondary fa-lg"></i>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Info -->
                                    <div class="flex-grow-1 min-width-0">
                                        <div class="fw-bold text-dark text-line-clamp-2">
                                            <?= htmlspecialchars($row['product_name']) ?>
                                        </div>
                                        <div class="small text-muted text-line-clamp-2">
                                            <?= htmlspecialchars($row['brand_name']) ?> | <span
                                                class="text-primary"><?= htmlspecialchars($row['variation_name']) ?></span>
                                        </div>
                                        <div class="d-flex align-items-center flex-wrap gap-2 mt-1">
                                            <span class="text-success fw-bold small">SRP:
                                                ₱<?= number_format($row['price_retail'], 2) ?></span>
                                            <?php if (!empty($row['sku'])): ?>
                                                <span
                                                    class="badge border text-secondary bg-light"><?= htmlspecialchars($row['sku']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="d-flex justify-content-between align-items-center pt-2 border-top mobile-card-footer">
                                    <div class="flex-shrink-0">
                                        <?php
                                        $phys = $row['physical_stock'] ?? $qty;
                                        $online = $row['online_stock'] ?? 0;

                                        if ($qty == 0) {
                                            echo '<span class="badge bg-danger-subtle text-danger"><i class="fa-solid fa-circle-xmark me-1"></i>Out of Stock</span>';
                                        } elseif ($phys <= $row['low_stock_threshold']) {
                                            echo '<span class="badge bg-warning-subtle text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i>Low: ' . $qty . '</span>';
                                        } else {
                                            echo '<span class="badge bg-success-subtle text-success">' . $qty . ' ' . $row['unit_type'] . '</span>';
                                        }
                                        ?>
                                    </div>
                                    <div class="d-flex gap-2 flex-wrap justify-content-end">
                                        <a href="edit.php?id=<?= $row['variation_id'] ?>" class="btn btn-sm btn-outline-primary"
                                            title="Update">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <a href="history.php?id=<?= $row['variation_id'] ?>" class="btn btn-sm btn-outline-info"
                                            title="History">
                                            <i class="fa-solid fa-clock-rotate-left"></i>
                                        </a>
                                        <button
                                            onclick="openAllocateModal(<?= $row['variation_id'] ?>, '<?= htmlspecialchars(addslashes($row['product_name'])) ?>', <?= $phys ?>, <?= $online ?>)"
                                            class="btn btn-sm btn-outline-success" title="Allocate">
                                            <i class="fa-solid fa-right-left"></i>
                                        </button>
                                        <button onclick="confirmDelete(<?= $row['variation_id'] ?>)"
                                            class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <h5 class="text-secondary">No products found</h5>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-footer bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <small class="text-muted" id="inventory-showing-text">
                Showing <?= count($products) > 0 ? $offset + 1 : 0 ?> to <?= min($offset + $limit, $total_items) ?> of
                <?= $total_items ?> items
            </small>

            <div class="d-flex align-items-center gap-2" id="inventory-pagination-container">
                <!-- Dropdown Pagination -->
                <label for="inventory-page-select" class="text-secondary small fw-bold text-nowrap">Go to Page:</label>
                <select id="inventory-page-select" class="form-select form-select-sm"
                    style="width: auto; min-width: 80px;">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <option value="<?= $i ?>" <?= $i == $page ? 'selected' : '' ?>><?= $i ?></option>
                    <?php endfor; ?>
                </select>
                <span class="text-muted small text-nowrap">of <span
                        id="inventory-total-pages"><?= $total_pages ?></span></span>
            </div>
        </div>
    </div>
</div>

<script>
    // 1. Logic to Toggle Cost Column
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('btnToggleCost');
        if (!btn) return; // If user doesn't have permission, skip this

        // Select both the column cells AND the Summary Card at the top
        const costElements = document.querySelectorAll('.capital-col');

        // Load Preference from Browser Memory
        // Default to 'false' (Visible) if not set
        let isHidden = localStorage.getItem('hide_cost') === 'true';

        // Initial Render
        updateVisibility();

        btn.addEventListener('click', function () {
            isHidden = !isHidden; // Toggle state
            localStorage.setItem('hide_cost', isHidden); // Save state
            updateVisibility();
        });

        function updateVisibility() {
            const currentCostElements = document.querySelectorAll('.capital-col');
            currentCostElements.forEach(el => {
                if (isHidden) {
                    el.classList.add('d-none'); // Bootstrap class to hide
                } else {
                    el.classList.remove('d-none');
                }
            });

            // Update Icon
            if (isHidden) {
                btn.innerHTML = '<i class="fa-solid fa-eye"></i>'; // Icon for "Show"
                btn.classList.add('btn-secondary');
                btn.classList.remove('btn-outline-secondary');
            } else {
                btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i>'; // Icon for "Hide"
                btn.classList.add('btn-outline-secondary');
                btn.classList.remove('btn-secondary');
            }
        }
    });

    function confirmDelete(id) {
        if (confirm("Are you sure? This will archive the product.")) {
            window.location.href = `delete.php?id=${id}`;
        }
    }

    // =====================================================
    // PROGRESSIVE SEARCH MODULE FOR INVENTORY
    // =====================================================
    const InventorySearch = {
        debounceTimer: null,
        currentFilter: '<?= $filter ?>',
        spinner: null,
        searchInput: null,
        tbody: null,
        mobileContainer: null,
        footerShowingText: null,
        pageSelect: null,
        totalPagesSpan: null,
        currentPage: <?= $page ?>,
        pageSize: 100,

        init() {
            this.searchInput = document.getElementById('inventory-search');
            this.spinner = document.getElementById('search-spinner');
            this.tbody = document.querySelector('#inventory-table tbody');
            this.mobileContainer = document.getElementById('mobile-cards-container');
            this.footerShowingText = document.getElementById('inventory-showing-text');
            this.pageSelect = document.getElementById('inventory-page-select');
            this.totalPagesSpan = document.getElementById('inventory-total-pages');

            if (!this.searchInput) return;

            // Debounced live search
            this.searchInput.addEventListener('input', (e) => {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => {
                    this.currentPage = 1; // Reset to page 1 on new search
                    this.performSearch(e.target.value.trim());
                }, 300);
            });

            // Handle Enter key
            this.searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(this.debounceTimer);
                    this.currentPage = 1; // Reset to page 1 on new search
                    this.performSearch(this.searchInput.value.trim());
                }
            });

            // Handle Page Selection
            if (this.pageSelect) {
                this.pageSelect.addEventListener('change', (e) => {
                    this.currentPage = parseInt(e.target.value);
                    this.performSearch(this.searchInput.value.trim());
                });
            }
        },

        performSearch(query) {
            // Build search URL with parameters
            let url = `../../api/inventory/search_products.php?q=${encodeURIComponent(query)}`;
            url += `&page=${this.currentPage}&limit=${this.pageSize}`;

            let exportUrl = `../../api/inventory/export_products.php?search=${encodeURIComponent(query)}`;

            if (this.currentFilter) {
                url += `&filter=${encodeURIComponent(this.currentFilter)}`;
                exportUrl += `&filter=${encodeURIComponent(this.currentFilter)}`;
            }

            const exportBtn = document.getElementById('export-csv-btn');
            if (exportBtn) {
                exportBtn.href = exportUrl;
            }

            // Sync browser URL to preserve state
            let queryParams = new URLSearchParams();
            if (query) queryParams.set('search', query);
            if (this.currentPage > 1) queryParams.set('page', this.currentPage);
            if (this.currentFilter) queryParams.set('filter', this.currentFilter);

            let browserUrl = window.location.pathname;
            if (queryParams.toString() !== '') browserUrl += '?' + queryParams.toString();
            history.replaceState(null, '', browserUrl);

            // Show spinner
            this.spinner?.classList.remove('d-none');
            // Disable inputs while searching
            if (this.pageSelect) this.pageSelect.disabled = true;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    this.renderResults(data.products || []);
                    this.updatePagination(data.pagination || {});
                })
                .catch(err => {
                    console.error('Search error:', err);
                })
                .finally(() => {
                    this.spinner?.classList.add('d-none');
                    if (this.pageSelect) this.pageSelect.disabled = false;
                });
        },

        renderResults(products) {
            if (!this.tbody) return;

            if (products.length === 0) {
                this.tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <h5 class="text-secondary">No products found</h5>
                            <a href="create.php" class="btn btn-sm btn-primary mt-2">Add First Product</a>
                        </td>
                    </tr>`;
                return;
            }

            const baseUrl = '<?= BASE_URL ?>';
            const isHidden = localStorage.getItem('hide_cost') === 'true';

            this.tbody.innerHTML = products.map(row => this.renderTableRow(row, baseUrl, isHidden)).join('');

            // Also render Mobile Cards
            if (this.mobileContainer) {
                this.mobileContainer.innerHTML = products.map(row => this.renderCard(row, baseUrl)).join('');
            }
        },

        renderTableRow(row, baseUrl, isHidden) {
            const qty = parseInt(row.current_stock) || 0;
            const phys = parseInt(row.physical_stock) || qty;
            const online = parseInt(row.online_stock) || 0;
            const thresh = parseInt(row.low_stock_threshold) || 0;

            const query = this.searchInput ? this.searchInput.value.trim() : '';
            const safeQuery = query ? query.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&').split(/\\s+/).filter(Boolean) : [];
            const highlight = (text) => {
                if (!text) return '';
                let hlText = this.escapeHtml(text);
                if (safeQuery.length === 0) return hlText;
                safeQuery.forEach(q => {
                    const regex = new RegExp(`(${q})`, 'gi');
                    hlText = hlText.replace(regex, '<mark class="bg-warning bg-opacity-50 p-0 rounded text-dark">$1</mark>');
                });
                return hlText;
            };

            let stockBadge = '';
            let onlineBadge = '';
            if (qty === 0) {
                stockBadge = '<span class="badge bg-danger-subtle text-danger border border-danger"><i class="fa-solid fa-circle-xmark"></i> Out</span>';
            } else if (phys <= thresh) {
                stockBadge = `<span class="badge bg-warning-subtle text-dark border border-warning" title="Physical: ${phys} | Online: ${online}"><i class="fa-solid fa-triangle-exclamation"></i> Low: ${qty}</span>`;
            } else {
                stockBadge = `<span class="badge bg-success-subtle text-success border border-success" title="Physical: ${phys} | Online: ${online}">${qty} ${this.escapeHtml(row.unit_type || '')}</span>`;
            }

            if (online > 0) {
                onlineBadge = `<span class="badge bg-info-subtle text-info border border-info ms-1" title="Online Allocation"><i class="fa-solid fa-globe"></i> ${online}</span>`;
            }

            const statusHtml = row.status === 'active'
                ? '<span class="text-success small"><i class="fa-solid fa-circle fa-xs"></i> Active</span>'
                : '<span class="text-secondary small"><i class="fa-solid fa-ban fa-xs"></i> Inactive</span>';

            const imgHtml = row.image_path
                ? `<img src="${baseUrl}${row.image_path}" style="width:100%; height:100%; object-fit:cover;">`
                : '<i class="fa-solid fa-cube text-secondary fa-lg"></i>';

            const escapedName = this.escapeHtml(row.product_name || '').replace(/'/g, "\\'");

            return `
                 <tr>
                     <td class="ps-4">
                         <div class="d-flex align-items-center">
                             <div class="rounded d-flex align-items-center justify-content-center me-3 overflow-hidden border"
                                 style="width: 40px; height: 40px; background: var(--bg-surface); border-color: var(--border-color) !important;">
                                 ${imgHtml}
                             </div>
                             <div>
                                 <div class="fw-bold mb-0" style="color: var(--text-primary);">
                                     ${highlight(row.product_name || '')}</div>
                                 <small class="text-muted">
                                     ${highlight(row.brand_name || '')} |
                                     <span class="text-primary">${highlight(row.variation_name || '')}</span>
                                 </small>
                                 ${row.sku ? `<div class="badge border ms-1" style="background: var(--bg-surface); color: var(--text-secondary); border-color: var(--border-color) !important;">${highlight(row.sku)}</div>` : ''}
                             </div>
                         </div>
                     </td>
                     ${!<?= json_encode(isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ?> ? '' : `<td class="capital-col ${isHidden ? 'd-none' : ''}">
                         <span class="text-muted small">₱</span> ${parseFloat(row.price_capital || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                     </td>`}
                     <td>
                         <span class="text-success fw-bold small">₱</span> <span class="fw-bold" style="color: var(--text-primary);">${parseFloat(row.price_retail || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                     </td>
                     <td>${stockBadge}${onlineBadge}</td>
                     <td>${statusHtml}</td>
                     <td class="text-end pe-4">
                         <div class="d-flex justify-content-end gap-2">
                             <a href="edit.php?id=${row.variation_id}" class="btn btn-sm btn-outline-primary" title="Update">
                                 <i class="fa-solid fa-pen-to-square"></i>
                             </a>
                             <a href="history.php?id=${row.variation_id}" class="btn btn-sm btn-outline-info" title="History">
                                 <i class="fa-solid fa-clock-rotate-left"></i>
                             </a>
                             <button onclick="openAllocateModal(${row.variation_id}, '${escapedName}', ${phys}, ${online})" class="btn btn-sm btn-outline-success" title="Allocate">
                                 <i class="fa-solid fa-right-left"></i>
                             </button>
                             <button onclick="confirmDelete(${row.variation_id})" class="btn btn-sm btn-outline-danger" title="Delete">
                                 <i class="fa-solid fa-trash"></i>
                             </button>
                         </div>
                     </td>
                 </tr>`;
        },

        renderCard(row, baseUrl) {
            const qty = parseInt(row.current_stock) || 0;
            const phys = parseInt(row.physical_stock) || qty;
            const online = parseInt(row.online_stock) || 0;
            const thresh = parseInt(row.low_stock_threshold) || 0;

            const query = this.searchInput ? this.searchInput.value.trim() : '';
            const safeQuery = query ? query.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&').split(/\\s+/).filter(Boolean) : [];
            const highlight = (text) => {
                if (!text) return '';
                let hlText = this.escapeHtml(text);
                if (safeQuery.length === 0) return hlText;
                safeQuery.forEach(q => {
                    const regex = new RegExp(`(${q})`, 'gi');
                    hlText = hlText.replace(regex, '<mark class="bg-warning bg-opacity-50 p-0 rounded text-dark">$1</mark>');
                });
                return hlText;
            };

            let statusClass = 'status-active';
            if (row.status !== 'active') statusClass = 'status-inactive';
            else if (qty === 0) statusClass = 'stock-out';
            else if (qty <= thresh) statusClass = 'stock-low';

            const imgHtml = row.image_path
                ? `<img src="${baseUrl}${row.image_path}" style="width:100%; height:100%; object-fit:cover;">`
                : '<i class="fa-solid fa-cube text-secondary fa-lg"></i>';

            let stockBadge = '';
            if (qty === 0) {
                stockBadge = '<span class="badge bg-danger-subtle text-danger"><i class="fa-solid fa-circle-xmark me-1"></i>Out of Stock</span>';
            } else if (phys <= thresh) {
                stockBadge = `<span class="badge bg-warning-subtle text-dark"><i class="fa-solid fa-triangle-exclamation me-1"></i>Low: ${qty}</span>`;
            } else {
                stockBadge = `<span class="badge bg-success-subtle text-success">${qty} ${this.escapeHtml(row.unit_type || '')}</span>`;
            }

            const escapedName = this.escapeHtml(row.product_name || '').replace(/'/g, "\\'");

            return `
                <div class="card inventory-card ${statusClass} border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex mb-3">
                            <div class="rounded d-flex align-items-center justify-content-center me-3 overflow-hidden border flex-shrink-0"
                                style="width: 60px; height: 60px; background: var(--bg-surface);">
                                ${imgHtml}
                            </div>
                            <div class="flex-grow-1 min-width-0">
                                <div class="fw-bold text-dark text-line-clamp-2">${highlight(row.product_name || '')}</div>
                                <div class="small text-muted text-line-clamp-2">
                                    ${highlight(row.brand_name || '')} | <span class="text-primary">${highlight(row.variation_name || '')}</span>
                                </div>
                                <div class="d-flex align-items-center flex-wrap gap-2 mt-1">
                                    <span class="text-success fw-bold small">SRP: ₱${parseFloat(row.price_retail || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                    ${row.sku ? `<span class="badge border text-secondary bg-light">${highlight(row.sku)}</span>` : ''}
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top mobile-card-footer">
                            <div class="flex-shrink-0">${stockBadge}</div>
                            <div class="d-flex gap-2 flex-wrap justify-content-end">
                                <a href="edit.php?id=${row.variation_id}" class="btn btn-sm btn-outline-primary" title="Update">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <a href="history.php?id=${row.variation_id}" class="btn btn-sm btn-outline-info" title="History">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </a>
                                <button onclick="openAllocateModal(${row.variation_id}, '${escapedName}', ${phys}, ${online})" class="btn btn-sm btn-outline-success" title="Allocate">
                                    <i class="fa-solid fa-right-left"></i>
                                </button>
                                <button onclick="confirmDelete(${row.variation_id})" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>`;

        },

        updatePagination(pagination) {
            const totalItems = parseInt(pagination.total_items) || 0;
            const totalPages = parseInt(pagination.total_pages) || 1;
            const currentPage = parseInt(pagination.current_page) || 1;
            const limit = parseInt(pagination.limit) || 100;

            // 1. Update Showing Text
            if (this.footerShowingText) {
                const start = totalItems > 0 ? (currentPage - 1) * limit + 1 : 0;
                const end = Math.min(currentPage * limit, totalItems);
                this.footerShowingText.textContent = `Showing ${start} to ${end} of ${totalItems} items`;
            }

            // 2. Update Dropdown Options (if total pages changed)
            if (this.pageSelect && this.totalPagesSpan) {
                this.totalPagesSpan.textContent = totalPages;

                // Only rebuild options if the count changed significantly or if needed
                // Ideally, we just check if the number of options matches totalPages.
                if (this.pageSelect.options.length !== totalPages) {
                    this.pageSelect.innerHTML = '';
                    for (let i = 1; i <= totalPages; i++) {
                        const option = document.createElement('option');
                        option.value = i;
                        option.text = i;
                        this.pageSelect.appendChild(option);
                    }
                }

                // Set current value
                this.pageSelect.value = currentPage;
            }
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        // Show appropriate view based on screen size
        function updateView() {
            const isMobile = window.innerWidth < 992;
            document.querySelectorAll('.mobile-cards').forEach(el => {
                el.style.display = isMobile ? 'block' : 'none';
            });
            document.querySelectorAll('.desktop-table').forEach(el => {
                el.style.display = isMobile ? 'none' : 'block';
            });
        }

        updateView();
        window.addEventListener('resize', updateView);

        InventorySearch.init();
    });
</script>

<!-- Allocation Modal -->
<div class="modal fade" id="allocateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-right-left text-success me-2"></i> Allocate Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="allocate_variation_id">
                <div class="mb-3">
                    <label class="form-label fw-bold">Product</label>
                    <div id="allocate_product_name" class="form-control-plaintext fw-bold text-primary"></div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <div class="text-muted small">Physical Store</div>
                                <div id="allocate_physical_stock" class="h3 mb-0 text-primary fw-bold">0</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <div class="text-muted small">Online Shop</div>
                                <div id="allocate_online_stock" class="h3 mb-0 text-info fw-bold">0</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="allocate_quantity" class="form-label">Quantity to Transfer</label>
                    <input type="number" id="allocate_quantity" class="form-control form-control-lg text-center" min="1"
                        value="1">
                </div>
                <div class="mb-3">
                    <label class="form-label">Direction</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="allocate_direction" id="dir_to_online"
                            value="to_online" checked>
                        <label class="btn btn-outline-info" for="dir_to_online">
                            <i class="fa-solid fa-store me-1"></i> → <i class="fa-solid fa-globe ms-1"></i> To Online
                        </label>
                        <input type="radio" class="btn-check" name="allocate_direction" id="dir_to_physical"
                            value="to_physical">
                        <label class="btn btn-outline-primary" for="dir_to_physical">
                            <i class="fa-solid fa-globe me-1"></i> → <i class="fa-solid fa-store ms-1"></i> To Physical
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitAllocation()">
                    <i class="fa-solid fa-check me-1"></i> Confirm Allocation
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Allocation Modal Functions
    function openAllocateModal(variationId, productName, physicalStock, onlineStock) {
        document.getElementById('allocate_variation_id').value = variationId;
        document.getElementById('allocate_product_name').textContent = productName;
        document.getElementById('allocate_physical_stock').textContent = physicalStock;
        document.getElementById('allocate_online_stock').textContent = onlineStock;
        document.getElementById('allocate_quantity').value = 1;
        document.getElementById('allocate_quantity').max = Math.max(physicalStock, onlineStock);
        document.getElementById('dir_to_online').checked = true;

        new bootstrap.Modal(document.getElementById('allocateModal')).show();
    }

    function submitAllocation() {
        const variationId = document.getElementById('allocate_variation_id').value;
        const quantity = parseInt(document.getElementById('allocate_quantity').value);
        const direction = document.querySelector('input[name="allocate_direction"]:checked').value;

        const physStock = parseInt(document.getElementById('allocate_physical_stock').textContent);
        const onlineStock = parseInt(document.getElementById('allocate_online_stock').textContent);

        // Validate quantity
        const maxQty = (direction === 'to_online') ? physStock : onlineStock;
        if (quantity <= 0 || quantity > maxQty) {
            EllaToast.warning(`Invalid quantity. Maximum available: ${maxQty}`);
            return;
        }

        // Show loading
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Processing...';
        btn.disabled = true;

        fetch('<?= BASE_URL ?>api/inventory/allocate_stock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                variation_id: variationId,
                quantity: quantity,
                direction: direction
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Update displayed values in modal
                    document.getElementById('allocate_physical_stock').textContent = data.physical_stock;
                    document.getElementById('allocate_online_stock').textContent = data.online_stock;

                    // Close modal and refresh page
                    bootstrap.Modal.getInstance(document.getElementById('allocateModal')).hide();

                    // Reload page to reflect changes
                    window.location.reload();
                } else {
                    EllaToast.error('Allocation failed: ' + data.message);
                }
            })
            .catch(err => {
                EllaToast.error('Error: ' + err.message);
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
    }
</script>

<?php require_once '../../includes/footer.php'; ?>