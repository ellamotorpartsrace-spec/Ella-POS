<?php
// views/inventory/archived.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    denyAccess("You do not have permission to manage inventory.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// --- PAGINATION & SEARCH LOGIC ---
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 100;
$offset = ($page - 1) * $limit;

// Base Query - Filter by INACTIVE
$baseSql = "
    FROM product_variations v
    JOIN products p ON v.product_id = p.product_id
    LEFT JOIN inventory i_phys ON v.variation_id = i_phys.variation_id AND i_phys.store_id = 1
    LEFT JOIN inventory i_online ON v.variation_id = i_online.variation_id AND i_online.store_id = 2
    WHERE v.status = 'inactive'
";

// Filters
$params = [];
if (!empty($search)) {
    $baseSql .= " AND (p.product_name LIKE ? OR p.brand_name LIKE ? OR v.sku LIKE ? OR v.barcode LIKE ?)";
    $term = "%$search%";
    $params = [$term, $term, $term, $term];
}

// 1. Fetch Stats (Aggregates) for Info Cards
$sqlStats = "
    SELECT 
        COUNT(*) as total_items
    " . $baseSql;

$stmtStats = $conn->prepare($sqlStats);
$stmtStats->execute($params);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

$total_items = $stats['total_items'] ?? 0;
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

    /* Archived Color Schema */
    .inventory-card {
        border-left-color: var(--bs-secondary);
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
    }
</style>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-secondary"><i class="fa-solid fa-box-archive me-2"></i> Archived Products</h4>
            <div class="text-muted small">Manage inactive products</div>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Inventory
        </a>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'restored'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-check-circle me-2"></i> Product restored successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body d-lg-flex justify-content-between align-items-center">

            <div class="d-flex mb-3 mb-lg-0" style="max-width: 450px; width: 100%;">
                <div class="input-group">
                    <span class="input-group-text bg-light text-secondary">
                        <i class="fa-solid fa-search"></i>
                    </span>
                    <input type="text" id="inventory-search" class="form-control"
                        placeholder="Search archived products..." value="<?= htmlspecialchars($search ?? '') ?>"
                        autofocus>
                    <span class="input-group-text d-none bg-white" id="search-spinner">
                        <i class="fa-solid fa-spinner fa-spin text-secondary"></i>
                    </span>
                </div>
            </div>

            <div class="text-secondary fw-bold">
                Total Archived: <span class="text-dark"><?= number_format($total_items) ?></span>
            </div>

        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0 desktop-table">
            <div class="table-responsive">
                <table id="inventory-table" class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Product Detail</th>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <th>Cost (Capital)</th>
                            <?php endif; ?>
                            <th>SRP (Retail)</th>
                            <th>Stock Level</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $row): ?>
                                <tr class="text-muted">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="rounded d-flex align-items-center justify-content-center me-3 overflow-hidden border bg-light"
                                                style="width: 40px; height: 40px;">
                                                <?php if (!empty($row['image_path'])): ?>
                                                    <img src="<?= BASE_URL . $row['image_path'] ?>"
                                                        style="width:100%; height:100%; object-fit:cover; filter: grayscale(100%);">
                                                <?php else: ?>
                                                    <i class="fa-solid fa-cube text-secondary fa-lg"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold mb-0 text-dark">
                                                    <?= htmlspecialchars($row['product_name'] ?? '') ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($row['brand_name'] ?? '') ?> |
                                                    <span
                                                        class="text-secondary"><?= htmlspecialchars($row['variation_name'] ?? '') ?></span>
                                                </small>
                                                <?php if (!empty($row['sku'])): ?>
                                                    <div class="badge border ms-1 text-muted bg-light">
                                                        <?= htmlspecialchars($row['sku']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                        <td>
                                            <span class="text-muted small">₱</span> <?= number_format($row['price_capital'], 2) ?>
                                        </td>
                                    <?php endif; ?>

                                    <td>
                                        <span class="text-secondary fw-bold small">₱</span> <span
                                            class="fw-bold"><?= number_format($row['price_retail'], 2) ?></span>
                                    </td>

                                    <td>
                                        <?php
                                        $qty = $row['current_stock'];
                                        echo '<span class="badge bg-secondary text-white">' . $qty . ' ' . $row['unit_type'] . '</span>';
                                        ?>
                                    </td>

                                    <td>
                                        <span class="badge bg-secondary text-white"><i class="fa-solid fa-box-archive"></i>
                                            Archived</span>
                                    </td>

                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button onclick="confirmRestore(<?= $row['variation_id'] ?>)"
                                                class="btn btn-sm btn-success" title="Restore Product">
                                                <i class="fa-solid fa-trash-arrow-up"></i> Restore
                                            </button>
                                            <a href="edit.php?id=<?= $row['variation_id'] ?>"
                                                class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <h5 class="text-secondary">No archived products found</h5>
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
                    <?php foreach ($products as $row): ?>
                        <div class="card inventory-card border-0 shadow-sm bg-light">
                            <div class="card-body p-3">
                                <div class="d-flex mb-3">
                                    <!-- Image -->
                                    <div class="rounded d-flex align-items-center justify-content-center me-3 overflow-hidden border flex-shrink-0 bg-white"
                                        style="width: 60px; height: 60px;">
                                        <?php if (!empty($row['image_path'])): ?>
                                            <img src="<?= BASE_URL . $row['image_path'] ?>"
                                                style="width:100%; height:100%; object-fit:cover; filter: grayscale(100%);">
                                        <?php else: ?>
                                            <i class="fa-solid fa-cube text-secondary fa-lg"></i>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Info -->
                                    <div class="flex-grow-1 min-width-0">
                                        <div class="fw-bold text-dark text-truncate">
                                            <?= htmlspecialchars($row['product_name']) ?>
                                        </div>
                                        <div class="small text-muted text-truncate">
                                            <?= htmlspecialchars($row['brand_name']) ?> | <span
                                                class="text-secondary"><?= htmlspecialchars($row['variation_name']) ?></span>
                                        </div>
                                        <div class="d-flex align-items-center flex-wrap gap-2 mt-1">
                                            <span class="text-secondary fw-bold small">SRP:
                                                ₱<?= number_format($row['price_retail'], 2) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                    <div>
                                        <span class="badge bg-secondary text-white"><i class="fa-solid fa-box-archive"></i>
                                            Archived</span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button onclick="confirmRestore(<?= $row['variation_id'] ?>)"
                                            class="btn btn-sm btn-success" title="Restore">
                                            <i class="fa-solid fa-trash-arrow-up"></i> Restore
                                        </button>
                                        <a href="edit.php?id=<?= $row['variation_id'] ?>" class="btn btn-sm btn-outline-primary"
                                            title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <h5 class="text-secondary">No archived products found</h5>
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
    function confirmRestore(id) {
        if (confirm("Are you sure you want to restore this product? It will be visible in the main inventory again.")) {
            window.location.href = `restore.php?id=${id}`;
        }
    }

    // =====================================================
    // PROGRESSIVE SEARCH MODULE FOR ARCHIVED INVENTORY
    // =====================================================
    const InventorySearch = {
        debounceTimer: null,
        status: 'inactive', // Fixed status for this page
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
            url += `&status=${this.status}`; // IMPORTANT: Filter by inactive status

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
                            <h5 class="text-secondary">No archived products found</h5>
                        </td>
                    </tr>`;
                return;
            }

            const baseUrl = '<?= BASE_URL ?>';
            this.tbody.innerHTML = products.map(row => this.renderTableRow(row, baseUrl)).join('');

            // Also render Mobile Cards
            if (this.mobileContainer) {
                this.mobileContainer.innerHTML = products.map(row => this.renderCard(row, baseUrl)).join('');
            }
        },

        renderTableRow(row, baseUrl) {
            const qty = parseInt(row.current_stock) || 0;

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

            const imgHtml = row.image_path
                ? `<img src="${baseUrl}${row.image_path}" style="width:100%; height:100%; object-fit:cover; filter: grayscale(100%);">`
                : '<i class="fa-solid fa-cube text-secondary fa-lg"></i>';

            return `
                 <tr class="text-muted">
                     <td class="ps-4">
                         <div class="d-flex align-items-center">
                             <div class="rounded d-flex align-items-center justify-content-center me-3 overflow-hidden border bg-light"
                                 style="width: 40px; height: 40px;">
                                 ${imgHtml}
                             </div>
                             <div>
                                 <div class="fw-bold mb-0 text-dark">
                                     ${highlight(row.product_name || '')}</div>
                                 <small class="text-muted">
                                     ${highlight(row.brand_name || '')} |
                                     <span class="text-secondary">${highlight(row.variation_name || '')}</span>
                                 </small>
                                 ${row.sku ? `<div class="badge border ms-1 text-muted bg-light">${highlight(row.sku)}</div>` : ''}
                             </div>
                         </div>
                     </td>
                     ${!<?= json_encode(isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ?> ? '' : `<td>
                         <span class="text-muted small">₱</span> ${parseFloat(row.price_capital || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                     </td>`}
                     <td>
                         <span class="text-secondary fw-bold small">₱</span> <span class="fw-bold">${parseFloat(row.price_retail || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                     </td>
                     <td><span class="badge bg-secondary text-white">${qty} ${this.escapeHtml(row.unit_type || '')}</span></td>
                     <td><span class="badge bg-secondary text-white"><i class="fa-solid fa-box-archive"></i> Archived</span></td>
                     <td class="text-end pe-4">
                         <div class="d-flex justify-content-end gap-2">
                             <button onclick="confirmRestore(${row.variation_id})" class="btn btn-sm btn-success" title="Restore">
                                 <i class="fa-solid fa-trash-arrow-up"></i> Restore
                             </button>
                             <a href="edit.php?id=${row.variation_id}" class="btn btn-sm btn-outline-primary" title="Edit">
                                 <i class="fa-solid fa-pen"></i>
                             </a>
                         </div>
                     </td>
                 </tr>`;
        },

        renderCard(row, baseUrl) {
            const qty = parseInt(row.current_stock) || 0;

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

            const imgHtml = row.image_path
                ? `<img src="${baseUrl}${row.image_path}" style="width:100%; height:100%; object-fit:cover; filter: grayscale(100%);">`
                : '<i class="fa-solid fa-cube text-secondary fa-lg"></i>';

            return `
                <div class="card inventory-card border-0 shadow-sm bg-light">
                    <div class="card-body p-3">
                        <div class="d-flex mb-3">
                            <div class="rounded d-flex align-items-center justify-content-center me-3 overflow-hidden border flex-shrink-0 bg-white"
                                style="width: 60px; height: 60px;">
                                ${imgHtml}
                            </div>
                            <div class="flex-grow-1 min-width-0">
                                <div class="fw-bold text-dark text-truncate">${highlight(row.product_name || '')}</div>
                                <div class="small text-muted text-truncate">
                                    ${highlight(row.brand_name || '')} | <span class="text-secondary">${highlight(row.variation_name || '')}</span>
                                </div>
                                <div class="d-flex align-items-center flex-wrap gap-2 mt-1">
                                    <span class="text-secondary fw-bold small">SRP: ₱${parseFloat(row.price_retail || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <div><span class="badge bg-secondary text-white"><i class="fa-solid fa-box-archive"></i> Archived</span></div>
                            <div class="d-flex gap-2">
                                <button onclick="confirmRestore(${row.variation_id})" class="btn btn-sm btn-success" title="Restore">
                                    <i class="fa-solid fa-trash-arrow-up"></i> Restore
                                </button>
                                <a href="edit.php?id=${row.variation_id}" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
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

            // 2. Update Dropdown Options
            if (this.pageSelect && this.totalPagesSpan) {
                this.totalPagesSpan.textContent = totalPages;

                if (this.pageSelect.options.length !== totalPages) {
                    this.pageSelect.innerHTML = '';
                    for (let i = 1; i <= totalPages; i++) {
                        const option = document.createElement('option');
                        option.value = i;
                        option.text = i;
                        this.pageSelect.appendChild(option);
                    }
                }
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

<?php require_once '../../includes/footer.php'; ?>