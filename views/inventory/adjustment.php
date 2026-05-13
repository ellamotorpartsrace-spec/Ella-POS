<?php
// views/inventory/adjustment.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    denyAccess("You do not have permission to adjust inventory.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Handle Search
$selected_product = null;
$search_results = [];
$search = $_GET['search'] ?? '';

if (!empty($search)) {
    $sqlSearch = "
        SELECT v.variation_id, v.variation_name, v.sku, v.barcode, 
               p.product_name, p.brand_name, p.image_path,
               COALESCE(i.quantity, 0) as current_stock
        FROM product_variations v
        JOIN products p ON v.product_id = p.product_id
        LEFT JOIN inventory i ON v.variation_id = i.variation_id
        WHERE v.status = 'active' 
        AND (p.product_name LIKE ? OR v.sku LIKE ? OR v.barcode LIKE ? OR p.brand_name LIKE ?)
        LIMIT 10
    ";
    $stmt = $conn->prepare($sqlSearch);
    $term = "%$search%";
    $stmt->execute([$term, $term, $term, $term]);
    $search_results = $stmt->fetchAll();
}

if (isset($_GET['id'])) {
    $sqlSelect = "
        SELECT v.*, p.product_name, p.brand_name, p.image_path,
               COALESCE(i.quantity, 0) as current_stock
        FROM product_variations v
        JOIN products p ON v.product_id = p.product_id
        LEFT JOIN inventory i ON v.variation_id = i.variation_id
        WHERE v.variation_id = ?
    ";
    $stmt = $conn->prepare($sqlSelect);
    $stmt->execute([$_GET['id']]);
    $selected_product = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="container-fluid p-3 p-lg-4">

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h4 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-sliders text-warning me-2"></i>Stock Adjustment
            </h4>
            <p class="text-muted mb-0 small">Correct inventory levels manually</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Back to Inventory
        </a>
    </div>

    <div class="row g-4">
        <!-- Search Column -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold">
                    <i class="fa-solid fa-magnifying-glass text-primary"></i> Find Product
                </div>
                <div class="card-body">
                    <!-- Progressive Search Input -->
                    <div class="position-relative mb-3">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-barcode"></i></span>
                            <input type="text" id="adjustment-search" class="form-control"
                                placeholder="Scan Barcode, Name or SKU..." value="<?= htmlspecialchars($search) ?>"
                                autofocus autocomplete="off">
                            <span class="input-group-text d-none" id="adjustment-search-spinner">
                                <i class="fa-solid fa-spinner fa-spin text-primary"></i>
                            </span>
                        </div>
                        <div id="adjustment-search-results" class="position-absolute z-3 w-100 list-group shadow"
                            style="max-height: 300px; overflow-y: auto; display: none;"></div>
                    </div>

                    <?php if (isset($_GET['id']) && !$selected_product): ?>
                        <div class="alert alert-warning small">Product not found</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Instructions/Notes -->
            <div class="alert alert-info border-0 shadow-sm">
                <h6 class="fw-bold"><i class="fa-solid fa-circle-info me-2"></i>Note</h6>
                <p class="small mb-0">Use this page to correct stock discrepancies. All adjustments are logged and
                    cannot be undone. For regular restocking, use the <a href="restock.php" class="fw-bold">Restock
                        Page</a>.</p>
            </div>
        </div>

        <!-- Adjustment Form Column -->
        <div class="col-lg-7">
            <?php if ($selected_product): ?>
                <div class="card shadow border-0 h-100">
                    <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between">
                        <span><i class="fa-solid fa-pen-to-square"></i> Adjustment Entry</span>
                        <span><?= htmlspecialchars($selected_product['sku'] ?? 'NO SKU') ?></span>
                    </div>
                    <div class="card-body p-4">

                        <!-- Product Summary -->
                        <div class="d-flex align-items-start mb-4 bg-light p-3 rounded">
                            <?php if (!empty($selected_product['image_path'])): ?>
                                <img src="<?= BASE_URL . $selected_product['image_path'] ?>" class="rounded me-3"
                                    style="width: 60px; height: 60px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-white rounded d-flex align-items-center justify-content-center me-3 text-secondary border"
                                    style="width: 60px; height: 60px;">
                                    <i class="fa-solid fa-image fa-lg"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h5 class="fw-bold mb-1"><?= htmlspecialchars($selected_product['product_name']) ?></h5>
                                <div class="text-muted small">
                                    <?= htmlspecialchars($selected_product['brand_name']) ?> -
                                    <?= htmlspecialchars($selected_product['variation_name']) ?>
                                </div>
                                <div class="badge bg-primary mt-1">Current Stock: <?= $selected_product['current_stock'] ?>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
                        <?php endif; ?>
                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success">Adjustment recorded successfully!</div>
                        <?php endif; ?>

                        <form action="../../api/inventory/process_adjustment.php" method="POST" id="adjustmentForm"
                            onsubmit="return validateForm()">
                            <input type="hidden" name="variation_id" value="<?= $selected_product['variation_id'] ?>">
                            <input type="hidden" name="current_stock" value="<?= $selected_product['current_stock'] ?>">

                            <!-- Hidden input for final signed quantity -->
                            <input type="hidden" name="quantity_adjustment" id="final_adjustment">

                            <div class="row g-3">

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Action</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="action_type" id="act_add" value="add"
                                            checked onchange="updateUI()">
                                        <label class="btn btn-outline-success" for="act_add">
                                            <i class="fa-solid fa-plus me-1"></i> Add Stock
                                        </label>

                                        <input type="radio" class="btn-check" name="action_type" id="act_sub" value="sub"
                                            onchange="updateUI()">
                                        <label class="btn btn-outline-danger" for="act_sub">
                                            <i class="fa-solid fa-minus me-1"></i> Remove Stock
                                        </label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Quantity</label>
                                    <input type="number" id="input_qty" class="form-control form-control-lg fw-bold"
                                        placeholder="0" min="1" required>
                                </div>

                                <div class="col-12">
                                    <div
                                        class="p-3 rounded bg-light border d-flex justify-content-between align-items-center">
                                        <span class="text-muted">Resulting Stock:</span>
                                        <span class="h4 fw-bold mb-0"
                                            id="preview_stock"><?= $selected_product['current_stock'] ?></span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Reason</label>
                                    <select name="reason" class="form-select" required>
                                        <option value="">-- Select Reason --</option>
                                        <option value="Damaged">Damaged / Broken</option>
                                        <option value="Lost">Lost / Stolen</option>
                                        <option value="Expired">Expired</option>
                                        <option value="Correction">Inventory Count Correction</option>
                                        <option value="Return">Customer Return (Restock)</option>
                                        <option value="Found">Found Item</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Remarks (Optional)</label>
                                    <input type="text" name="remarks" class="form-control"
                                        placeholder="Additional details...">
                                </div>

                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-warning w-100 shadow-sm fw-bold">
                                        <i class="fa-solid fa-save me-1"></i> Save Adjustment
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="h-100 d-flex align-items-center justify-content-center border rounded bg-light text-center p-5">
                    <div class="text-muted">
                        <i class="fa-solid fa-arrow-left fa-3x mb-3 opacity-25"></i>
                        <h5>No Product Selected</h5>
                        <p>Search and select a product from the left to adjust stock.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function updateUI() {
        const action = document.querySelector('input[name="action_type"]:checked').value;
        const qtyInput = document.getElementById('input_qty');
        const currentStock = <?= $selected_product['current_stock'] ?? 0 ?>;
        const qty = parseInt(qtyInput.value) || 0;

        let newStock = currentStock;
        let finalAdj = 0;

        if (action === 'add') {
            newStock = currentStock + qty;
            finalAdj = qty;
            qtyInput.classList.remove('text-danger', 'border-danger');
            qtyInput.classList.add('text-success', 'border-success');
        } else {
            newStock = currentStock - qty;
            finalAdj = -qty;
            qtyInput.classList.remove('text-success', 'border-success');
            qtyInput.classList.add('text-danger', 'border-danger');
        }

        const previewEl = document.getElementById('preview_stock');
        previewEl.innerText = newStock;

        // Validate negative stock visually
        if (newStock < 0) {
            previewEl.classList.add('text-danger');
        } else {
            previewEl.classList.remove('text-danger');
        }

        document.getElementById('final_adjustment').value = finalAdj;
    }

    function validateForm() {
        const action = document.querySelector('input[name="action_type"]:checked').value;
        const qty = parseInt(document.getElementById('input_qty').value) || 0;
        const currentStock = <?= $selected_product['current_stock'] ?? 0 ?>;

        if (qty <= 0) {
            EllaToast.warning("Please enter a valid quantity greater than 0.");
            return false;
        }

        if (action === 'sub' && (currentStock - qty) < 0) {
            if (!confirm("Warning: This adjustment will result in NEGATIVE stock. Continue?")) {
                return false;
            }
        }

        // Ensure updateUI is called one last time to set the hidden field
        updateUI();
        return true;
    }

    // Listen for input changes
    document.getElementById('input_qty')?.addEventListener('input', updateUI);

    // Initial call
    if (document.getElementById('input_qty')) updateUI();

    // =====================================================
    // PROGRESSIVE SEARCH MODULE FOR ADJUSTMENT
    // =====================================================
    const AdjustmentSearch = {
        searchTimeout: null,

        init() {
            const searchInput = document.getElementById('adjustment-search');
            if (!searchInput) return;

            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchTimeout);
                const query = e.target.value.trim();
                if (query.length < 1) {
                    this.hideResults();
                    return;
                }
                this.searchTimeout = setTimeout(() => this.searchProducts(query), 300);
            });

            // Handle Enter key for immediate search
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(this.searchTimeout);
                    const query = searchInput.value.trim();
                    if (query.length >= 1) {
                        this.searchProducts(query);
                    }
                }
            });

            // Close dropdown on outside click
            document.addEventListener('click', (e) => {
                if (!e.target.closest('#adjustment-search') && !e.target.closest('#adjustment-search-results')) {
                    this.hideResults();
                }
            });
        },

        async searchProducts(query) {
            const spinner = document.getElementById('adjustment-search-spinner');
            spinner?.classList.remove('d-none');

            try {
                const res = await fetch(`../../api/inventory/search_products.php?q=${encodeURIComponent(query)}`);
                const data = await res.json();
                // Handle both old format (array) and new format ({products: []})
                const products = data.products || data;
                this.renderSearchResults(products);
            } catch (err) {
                console.error('Search error:', err);
            } finally {
                spinner?.classList.add('d-none');
            }
        },

        renderSearchResults(products) {
            const container = document.getElementById('adjustment-search-results');
            const query = document.getElementById('adjustment-search').value.trim();
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

            if (!products || products.length === 0) {
                container.innerHTML = '<div class="list-group-item text-muted small">No products found</div>';
                container.style.display = 'block';
                return;
            }

            container.innerHTML = products.slice(0, 15).map(p => `
            <a href="adjustment.php?id=${p.variation_id}"
               class="list-group-item list-group-item-action d-flex align-items-center">
                <div class="me-3 text-secondary">
                    <i class="fa-solid fa-box fa-lg"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-bold text-dark">${highlight(p.product_name)}</div>
                    <div class="small text-muted">
                        ${p.brand_name ? highlight(p.brand_name) + ' | ' : ''}${highlight(p.variation_name || 'Default')}
                    </div>
                </div>
                <span class="badge bg-light text-dark border">Stock: ${p.current_stock}</span>
            </a>
        `).join('');
            container.style.display = 'block';
        },

        hideResults() {
            const container = document.getElementById('adjustment-search-results');
            if (container) container.style.display = 'none';
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
    };

    // Initialize search on page load
    document.addEventListener('DOMContentLoaded', () => {
        AdjustmentSearch.init();
    });
</script>

<?php require_once '../../includes/footer.php'; ?>