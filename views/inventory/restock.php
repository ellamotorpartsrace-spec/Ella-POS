<?php
// views/inventory/restock.php - Enhanced with Single & Batch Modes
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    denyAccess("You do not have permission to access restocking.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Get suppliers for dropdown
$suppliers = $conn->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name")->fetchAll();

// Handle Search for single mode
$selected_product = null;
$search_results = [];
$search = $_GET['search'] ?? '';
$mode = $_GET['mode'] ?? 'single';

if (!empty($search) && $mode === 'single') {
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

<style>
    .mode-btn {
        transition: all 0.2s;
    }

    .mode-btn.active {
        transform: scale(1.02);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .batch-item {
        transition: all 0.2s;
        border-left: 4px solid var(--bs-success);
    }

    .batch-item:hover {
        background-color: #f8f9fa;
    }

    .search-dropdown {
        position: absolute;
        z-index: 1050;
        width: 100%;
        max-height: 420px;
        overflow-y: auto;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
        border-radius: 0 0 12px 12px;
        background: #fff;
        border: 1px solid #e9ecef;
    }

    .search-dropdown::-webkit-scrollbar {
        width: 6px;
    }

    .search-dropdown::-webkit-scrollbar-thumb {
        background: #ced4da;
        border-radius: 3px;
    }

    /* Product Suggestion Card */
    .product-suggestion {
        display: flex;
        align-items: center;
        padding: 12px 14px;
        gap: 12px;
        border-bottom: 1px solid #f1f3f5;
        transition: all 0.2s ease;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
    }

    .product-suggestion:last-child {
        border-bottom: none;
    }

    .product-suggestion:hover {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        transform: translateX(4px);
    }

    .product-suggestion:active {
        transform: translateX(2px);
        background: #e9ecef;
    }

    .product-suggestion-img {
        width: 48px;
        height: 48px;
        border-radius: 8px;
        object-fit: cover;
        flex-shrink: 0;
        border: 1px solid #e9ecef;
        background: #f8f9fa;
    }

    .product-suggestion-placeholder {
        width: 48px;
        height: 48px;
        border-radius: 8px;
        background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: #adb5bd;
    }

    .product-suggestion-content {
        flex: 1;
        min-width: 0;
    }

    .product-suggestion-name {
        font-weight: 600;
        color: #212529;
        margin-bottom: 2px;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .product-suggestion-details {
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 4px;
    }

    .product-suggestion-meta {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .product-suggestion-badge {
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 500;
    }

    .product-suggestion-right {
        text-align: right;
        flex-shrink: 0;
    }

    .stock-badge-in {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
    }

    .stock-badge-low {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        color: #856404;
    }

    .stock-badge-out {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
    }

    .sku-badge {
        background: #e9ecef;
        color: #495057;
    }

    .barcode-badge {
        background: #f8f9fa;
        color: #6c757d;
        border: 1px dashed #ced4da;
    }

    .price-capital {
        font-size: 0.85rem;
        color: #28a745;
        font-weight: 600;
    }

    .search-dropdown-header {
        padding: 10px 14px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        font-size: 0.75rem;
        color: #6c757d;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .search-dropdown-empty {
        padding: 24px;
        text-align: center;
        color: #6c757d;
    }

    .search-dropdown-empty i {
        font-size: 2rem;
        opacity: 0.3;
        margin-bottom: 8px;
    }

    .summary-card {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    }

    /* Single Mode Total Summary */
    .single-total-card {
        background: linear-gradient(135deg, #f0f9f1 0%, #e8f5e9 100%);
        border: 2px solid #c8e6c9;
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .single-total-card.has-value {
        border-color: #28a745;
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
    }

    .single-total-amount {
        font-size: 1.75rem;
        font-weight: 800;
        color: #1b5e20;
        letter-spacing: -0.5px;
        transition: all 0.3s ease;
    }

    .single-total-card.has-value .single-total-amount {
        color: #155724;
    }

    .single-summary-footer {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border-radius: 0 0 12px 12px;
        margin: 0 -1.5rem -1.5rem -1.5rem;
        padding: 16px 24px;
        margin-top: 24px;
    }

    .single-summary-footer .summary-item {
        text-align: center;
    }

    .single-summary-footer .summary-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: #fff;
    }

    .single-summary-footer .summary-label {
        font-size: 0.7rem;
        color: rgba(255, 255, 255, 0.8);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .total-calc-breakdown {
        font-size: 0.82rem;
        color: #6c757d;
        font-weight: 500;
    }

    .total-calc-breakdown .calc-highlight {
        color: #28a745;
        font-weight: 700;
    }
</style>

<div class="container-fluid p-3 p-lg-4">

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h4 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-truck-ramp-box text-success me-2"></i>Restock Inventory
            </h4>
            <p class="text-muted mb-0 small">Add stock for single or multiple products</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Back to Inventory
        </a>
    </div>

    <!-- Mode Selector -->
    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="card mode-btn h-100 border-0 shadow-sm <?= $mode === 'single' ? 'active border-primary' : '' ?>"
                onclick="switchMode('single')" style="cursor: pointer;">
                <div class="card-body p-3 text-center">
                    <i class="fa-solid fa-cube fa-2x text-primary mb-2"></i>
                    <h6 class="fw-bold mb-1">Single Product</h6>
                    <small class="text-muted">Restock one item at a time</small>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card mode-btn h-100 border-0 shadow-sm <?= $mode === 'batch' ? 'active border-success' : '' ?>"
                onclick="switchMode('batch')" style="cursor: pointer;">
                <div class="card-body p-3 text-center">
                    <i class="fa-solid fa-boxes-stacked fa-2x text-success mb-2"></i>
                    <h6 class="fw-bold mb-1">Batch Restock</h6>
                    <small class="text-muted">Multiple products at once</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Single Mode -->
    <div id="single-mode" class="<?= $mode !== 'single' ? 'd-none' : '' ?>">
        <div class="row g-4">
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
                                <input type="text" id="single-search" class="form-control"
                                    placeholder="Scan Barcode, Product Name or Brand..."
                                    value="<?= htmlspecialchars($search) ?>" autofocus autocomplete="off">
                                <span class="input-group-text d-none" id="single-search-spinner">
                                    <i class="fa-solid fa-spinner fa-spin text-primary"></i>
                                </span>
                            </div>
                            <div id="single-search-results" class="search-dropdown list-group d-none"></div>
                        </div>

                        <?php if (isset($_GET['id']) && !$selected_product): ?>
                            <div class="alert alert-warning small">Product not found</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">
                        <i class="fa-solid fa-clock-rotate-left text-secondary"></i> Recent Stock-Ins
                    </div>
                    <div class="card-body p-0">
                        <?php
                        $sqlLog = "SELECT m.*, p.product_name, p.brand_name, v.variation_name 
                                       FROM stock_movements m 
                                       JOIN product_variations v ON m.variation_id = v.variation_id
                                       JOIN products p ON v.product_id = p.product_id
                                       WHERE m.type = 'stock_in' 
                                       ORDER BY m.created_at DESC LIMIT 5";
                        $logs = $conn->query($sqlLog)->fetchAll();
                        ?>
                        <table class="table table-sm mb-0 small">
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold"><?= htmlspecialchars($log['product_name']) ?></div>
                                        <div class="text-muted small">
                                            <?= htmlspecialchars($log['brand_name']) ?> -
                                            <?= htmlspecialchars($log['variation_name']) ?>
                                        </div>
                                    </td>
                                    <td class="text-success fw-bold align-middle">+<?= $log['quantity'] ?></td>
                                    <td class="text-muted pe-3 text-end align-middle">
                                        <?= date('M d', strtotime($log['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <?php if ($selected_product): ?>
                    <div class="card shadow border-0 h-100">
                        <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between">
                            <span><i class="fa-solid fa-truck-ramp-box"></i> Restock Entry</span>
                            <span><?= htmlspecialchars($selected_product['sku'] ?? 'NO SKU') ?></span>
                        </div>
                        <div class="card-body p-4">
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
                                    <div class="badge bg-success mt-1">Current Stock:
                                        <?= $selected_product['current_stock'] ?>     <?= $selected_product['unit_type'] ?>
                                    </div>
                                </div>
                            </div>

                            <form action="../../api/inventory/process_restock.php" method="POST" id="single-restock-form"
                                enctype="multipart/form-data">
                                <input type="hidden" name="variation_id" value="<?= $selected_product['variation_id'] ?>">
                                <input type="hidden" name="current_stock" value="<?= $selected_product['current_stock'] ?>">

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-success">Quantity to Add <span
                                                class="text-danger">*</span></label>
                                        <input type="number" name="quantity_added" id="single-qty"
                                            class="form-control form-control-lg border-success fw-bold" placeholder="0"
                                            min="1" required autofocus>
                                    </div>
                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Capital Price (Per Unit)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="number" step="0.01" name="new_capital" id="single-capital"
                                                    class="form-control form-control-lg"
                                                    value="<?= $selected_product['price_capital'] ?>">
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <input type="hidden" name="new_capital" id="single-capital"
                                            value="<?= $selected_product['price_capital'] ?>">
                                    <?php endif; ?>
                                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                        <!-- Total Cost Summary -->
                                        <div class="col-12">
                                            <div class="single-total-card p-3" id="single-total-card">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <div class="text-muted small fw-bold text-uppercase mb-1">
                                                            <i class="fa-solid fa-calculator me-1"></i> Total Cost
                                                        </div>
                                                        <div class="total-calc-breakdown" id="single-calc-breakdown">
                                                            <span class="calc-highlight">0</span> pcs × ₱<span
                                                                class="calc-highlight">0.00</span>
                                                        </div>
                                                    </div>
                                                    <div class="single-total-amount" id="single-total-display">₱0.00</div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="col-md-6">
                                        <label class="form-label">Supplier</label>
                                        <select name="supplier" class="form-select">
                                            <option value="">-- Select Supplier --</option>
                                            <?php foreach ($suppliers as $s): ?>
                                                <option value="<?= htmlspecialchars($s['supplier_name']) ?>">
                                                    <?= htmlspecialchars($s['supplier_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Reference / Invoice #</label>
                                        <input type="text" name="reference" class="form-control"
                                            placeholder="e.g. PO-2023-001">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-muted">Reference Image</label>
                                                                                 <input type="file" name="reference_images[]" id="single-image" class="form-control" accept="image/*" multiple onchange="RestockUI.previewImages(this, 'single-image-preview')">
                                         <div id="single-image-preview" class="mt-2 d-flex flex-wrap gap-2"></div>

                                    </div>

                                    <!-- Payment Terms Section -->
                                    <div class="col-12 mt-3">
                                        <hr class="text-muted">
                                        <label class="form-label fw-bold text-primary">
                                            <i class="fa-solid fa-money-bill-transfer me-1"></i> Payment Terms
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Payment Status</label>
                                        <select name="payment_status" id="single-payment-status" class="form-select"
                                            onchange="toggleDueDate(this)">
                                            <option value="paid">Paid (Cash)</option>
                                            <option value="unpaid">Unpaid (Credit)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6" id="single-due-date-container" style="display: none;">
                                        <label class="form-label">Due Date <span class="text-danger">*</span></label>
                                        <input type="date" name="due_date" id="single-due-date" class="form-control"
                                            min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                                    </div>

                                    <div class="col-12 mt-4">
                                        <button type="submit" class="btn btn-success btn-lg w-100 shadow-sm">
                                            <i class="fa-solid fa-check-circle"></i> Confirm Restock
                                        </button>
                                    </div>
                                </div>

                                <!-- Sticky Summary Footer -->
                                <div class="single-summary-footer">
                                    <div class="d-flex justify-content-around">
                                        <div class="summary-item">
                                            <div class="summary-value" id="sf-current">
                                                <?= $selected_product['current_stock'] ?>
                                            </div>
                                            <div class="summary-label">Current Stock</div>
                                        </div>
                                        <div class="summary-item">
                                            <div class="summary-value"><i class="fa-solid fa-plus fa-xs"></i> <span
                                                    id="sf-adding">0</span></div>
                                            <div class="summary-label">Adding</div>
                                        </div>
                                        <div class="summary-item">
                                            <div class="summary-value" id="sf-new"><?= $selected_product['current_stock'] ?>
                                            </div>
                                            <div class="summary-label">New Stock</div>
                                        </div>
                                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                            <div class="summary-item">
                                                <div class="summary-value" id="sf-total-cost">₱0.00</div>
                                                <div class="summary-label">Total Cost</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div
                        class="h-100 d-flex align-items-center justify-content-center border rounded bg-light text-center p-5">
                        <div class="text-muted">
                            <i class="fa-solid fa-arrow-left fa-3x mb-3 opacity-25"></i>
                            <h5>No Product Selected</h5>
                            <p>Search and select a product from the left to begin restocking.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Batch Mode -->
    <div id="batch-mode" class="<?= $mode !== 'batch' ? 'd-none' : '' ?>">
        <div class="row g-4">
            <!-- Left: Search & Add Products -->
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body p-2 d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm w-50" onclick="RestockDraftUI.saveDraft()">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Draft
                        </button>
                        <button class="btn btn-outline-primary btn-sm w-50" onclick="RestockDraftUI.loadDraft()">
                            <i class="fa-solid fa-folder-open me-1"></i> Load Draft
                        </button>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-success text-white fw-bold">
                        <i class="fa-solid fa-search-plus"></i> Add Products to Batch
                    </div>
                    <div class="card-body">
                        <!-- Supplier Selection -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Supplier <span class="text-danger">*</span></label>
                            <select id="batch-supplier" class="form-select" required>
                                <option value="">-- Select Supplier --</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= $s['supplier_id'] ?>"
                                        data-name="<?= htmlspecialchars($s['supplier_name']) ?>">
                                        <?= htmlspecialchars($s['supplier_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Reference / PO Number</label>
                            <input type="text" id="batch-reference" class="form-control" placeholder="e.g. PO-2024-001">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted">Reference Images (Multiple)</label>
                                                         <input type="file" id="batch-image" class="form-control" accept="image/*" multiple onchange="RestockUI.previewImages(this, 'batch-image-preview')">
                             <div id="batch-image-preview" class="mt-2 d-flex flex-wrap gap-2"></div>

                        </div>

                        <!-- Payment Terms Section -->
                        <div class="border rounded p-3 bg-light mb-3">
                            <label class="form-label fw-bold text-primary mb-2">
                                <i class="fa-solid fa-money-bill-transfer me-1"></i> Payment Terms
                            </label>
                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label small">Payment Status</label>
                                    <select id="batch-payment-status" class="form-select"
                                        onchange="BatchRestock.togglePaymentTerms()">
                                        <option value="paid">Paid (Cash)</option>
                                        <option value="unpaid">Unpaid (Credit)</option>
                                    </select>
                                </div>
                                <div class="col-12 mt-2" id="batch-credit-terms-container" style="display: none;">
                                    <div
                                        class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                        <label class="form-label small fw-bold mb-0">Credit Schedule</label>
                                        <div class="d-flex gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-success py-0"
                                                onclick="BatchRestock.splitExact()"
                                                title="Split batch total equally across all terms">
                                                <i class="fa-solid fa-equals me-1"></i>EXACT
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary py-0"
                                                onclick="BatchRestock.addCreditTerm()">
                                                <i class="fa-solid fa-plus"></i> Add Term
                                            </button>
                                        </div>
                                    </div>
                                    <div id="batch-credit-terms-list">
                                        <!-- Terms will be rendered here -->
                                    </div>
                                    <div class="d-flex justify-content-between mt-2 pt-2 border-top small">
                                        <span class="text-muted">Total: <span id="terms-batch-total"
                                                class="fw-bold text-dark">₱0.00</span></span>
                                        <span class="text-muted">Scheduled: <span id="terms-scheduled-total"
                                                class="fw-bold text-primary">₱0.00</span></span>
                                        <span id="terms-remaining-wrap" class="d-none">Remaining: <span
                                                id="terms-remaining" class="fw-bold text-danger">₱0.00</span></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Product Search -->
                        <div class="position-relative mb-3">
                            <label class="form-label fw-bold">Search Product</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-barcode"></i></span>
                                <input type="text" id="batch-search" class="form-control"
                                    placeholder="Scan barcode or type product name..." autocomplete="off">
                            </div>
                            <div id="batch-search-results" class="search-dropdown list-group d-none"></div>
                        </div>

                        <!-- Quick Add Form (after selecting product) -->
                        <div id="quick-add-form" class="d-none">
                            <div class="card bg-light border-0 mb-3">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <div class="fw-bold" id="qa-product-name">Product Name</div>
                                            <small class="text-muted" id="qa-product-details">Brand | Variation</small>
                                        </div>
                                        <button type="button" class="btn-close"
                                            onclick="BatchRestock.clearQuickAdd()"></button>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-4">
                                            <label class="form-label small">Qty</label>
                                            <input type="number" id="qa-qty" class="form-control" min="1" value="1">
                                        </div>
                                        <div class="col-5">
                                            <label class="form-label small">Cost/Unit</label>
                                            <input type="number" id="qa-cost" class="form-control" step="0.01"
                                                placeholder="0.00">
                                        </div>
                                        <div class="col-3 d-flex flex-column justify-content-end pb-1">
                                            <div class="form-check form-switch mb-1">
                                                <input class="form-check-input" type="checkbox" id="qa-is-free"
                                                    onchange="BatchRestock.toggleFree(this)">
                                                <label class="form-check-label small fw-bold text-success"
                                                    for="qa-is-free">FREE</label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Free Item Reason (shown when FREE is toggled) -->
                                    <div id="qa-free-reason-row" class="d-none mt-2">
                                        <div
                                            class="alert alert-success border-success py-2 px-3 mb-0 d-flex align-items-center gap-2">
                                            <i class="fa-solid fa-gift text-success"></i>
                                            <div class="flex-grow-1">
                                                <div class="small fw-bold text-success mb-1">Free Item — Select Reason
                                                </div>
                                                <select id="qa-free-reason" class="form-select form-select-sm">
                                                    <option value="Supplier Promo">🎁 Supplier Promo / Bonus</option>
                                                    <option value="Warranty Replacement">🔄 Warranty Replacement
                                                    </option>
                                                    <option value="Damaged Return">📦 Damaged Return</option>
                                                    <option value="Sample">🧪 Sample / Demo Item</option>
                                                    <option value="Other">📝 Other</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <input type="hidden" id="qa-variation-id">
                                    <input type="hidden" id="qa-current-stock">
                                    <input type="hidden" id="qa-sku">
                                    <input type="hidden" id="qa-barcode">
                                    <button type="button" class="btn btn-success w-100 mt-3"
                                        onclick="BatchRestock.addToBatch()">
                                        <i class="fa-solid fa-plus me-1"></i>Add to Batch
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Batch List -->
            <div class="col-lg-7">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-list-check text-success me-2"></i>Batch Items</span>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                onclick="BatchRestock.clearBatch()" id="btn-clear-batch"
                                title="Clear all items from batch">
                                <i class="fa-solid fa-trash-can me-1"></i>Clear Items
                            </button>
                            <span class="badge bg-secondary" id="batch-count">0 items</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="batch-list" style="max-height: 400px; overflow-y: auto;">
                            <div class="text-center py-5 text-muted" id="batch-empty">
                                <i class="fa-solid fa-box-open fa-3x opacity-25 mb-3"></i>
                                <h6>No items added yet</h6>
                                <p class="small">Search and add products to your batch</p>
                            </div>
                            <div id="batch-items" class="d-none"></div>
                        </div>
                    </div>

                    <!-- Summary Footer -->
                    <div class="card-footer summary-card text-white p-3">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="h4 fw-bold mb-0" id="summary-items">0</div>
                                <small class="opacity-75">Items</small>
                            </div>
                            <div class="col-4">
                                <div class="h4 fw-bold mb-0" id="summary-qty">0</div>
                                <small class="opacity-75">Total Qty</small>
                            </div>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <div class="col-4">
                                    <div class="h5 fw-bold mb-0" id="summary-cost">₱0.00</div>
                                    <small class="opacity-75">Total Cost</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-footer bg-white p-3">
                        <button type="button" class="btn btn-success btn-lg w-100 shadow" id="btn-process-batch"
                            onclick="BatchRestock.processBatch()" disabled>
                            <i class="fa-solid fa-check-circle me-1"></i>Process Batch Restock
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ================= DRAFTS MODAL ================= -->
<div class="modal fade" id="draftsModal" tabindex="-1" aria-labelledby="draftsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title" id="draftsModalLabel">
                    <i class="fa-solid fa-folder-open me-2"></i>Saved Restock Drafts
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body p-3" style="min-height: 300px; max-height: 60vh;">
                <!-- Loading State -->
                <div id="drafts-loading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2">Loading drafts...</p>
                </div>

                <!-- Empty State -->
                <div id="drafts-empty" class="text-center py-5 d-none">
                    <i class="fa-solid fa-folder-open fa-4x text-muted opacity-25 mb-3"></i>
                    <h6 class="text-muted">No Saved Drafts</h6>
                    <p class="small text-muted">Your saved restock batches will appear here.</p>
                </div>

                <!-- Drafts Grid -->
                <div id="drafts-grid" class="row g-3 d-none"></div>
            </div>
            <div class="modal-footer bg-light py-2">
                <small class="text-muted me-auto">
                    <i class="fa-solid fa-info-circle me-1"></i>
                    Click a draft card to load it
                </small>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================= SAVE DRAFT MODAL ================= -->
<div class="modal fade" id="saveDraftModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Save Restock Draft
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">DRAFT LABEL (Optional)</label>
                    <input type="text" id="draft-label-input" class="form-control"
                        placeholder="e.g., Supplier ABC order, Missing items...">
                    <small class="text-muted">Give this draft a name to easily identify it later</small>
                </div>
                <div class="alert alert-info py-2 mb-0">
                    <i class="fa-solid fa-boxes-stacked me-1"></i>
                    <span id="save-draft-summary">0 items • ₱0.00</span>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-sm" id="btn-confirm-save-draft">
                    <i class="fa-solid fa-check me-1"></i>Save Draft
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle due date visibility for single mode
    function toggleDueDate(selectElem) {
        const container = document.getElementById('single-due-date-container');
        container.style.display = selectElem.value === 'unpaid' ? 'block' : 'none';
        if (selectElem.value === 'unpaid') {
            document.getElementById('single-due-date').required = true;
        } else {
            document.getElementById('single-due-date').required = false;
        }
    }

    function switchMode(mode, element = null) {
        document.getElementById('single-mode').classList.toggle('d-none', mode !== 'single');
        document.getElementById('batch-mode').classList.toggle('d-none', mode !== 'batch');

        document.querySelectorAll('.mode-btn').forEach(btn => btn.classList.remove('active', 'border-primary', 'border-success'));

        const targetBtn = element || (mode === 'single' ? document.querySelector('.mode-btn[onclick*="single"]') : document.querySelector('.mode-btn[onclick*="batch"]'));
        if (targetBtn) {
            targetBtn.classList.add('active', mode === 'single' ? 'border-primary' : 'border-success');
        }

        // Update URL without reload
        const url = new URL(window.location);
        url.searchParams.set('mode', mode);
        window.history.replaceState({}, '', url);
    }

    // =====================================================
    // SINGLE MODE PROGRESSIVE SEARCH
    // =====================================================
    const SingleModeSearch = {
        searchTimeout: null,

        init() {
            const searchInput = document.getElementById('single-search');
            if (!searchInput) return;

            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchTimeout);
                const query = e.target.value.trim();
                if (query.length < 1) {
                    document.getElementById('single-search-results').classList.add('d-none');
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
                if (!e.target.closest('#single-search') && !e.target.closest('#single-search-results')) {
                    document.getElementById('single-search-results').classList.add('d-none');
                }
            });
        },

        async searchProducts(query) {
            const spinner = document.getElementById('single-search-spinner');
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
            const container = document.getElementById('single-search-results');
            const query = document.getElementById('single-search').value.trim();
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
                container.innerHTML = `
                    <div class="search-dropdown-empty">
                        <i class="fa-solid fa-box-open d-block"></i>
                        <div class="fw-medium">No products found</div>
                        <small>Try a different search term</small>
                    </div>`;
                container.classList.remove('d-none');
                return;
            }

            const baseUrl = '<?= BASE_URL ?>';
            let html = `<div class="search-dropdown-header"><i class="fa-solid fa-search me-1"></i>${products.length} result${products.length !== 1 ? 's' : ''} found</div>`;

            html += products.slice(0, 15).map(p => {
                // Determine stock status
                const stock = parseInt(p.current_stock) || 0;
                const threshold = parseInt(p.low_stock_threshold) || 5;
                let stockBadgeClass = 'stock-badge-in';
                let stockIcon = 'fa-check-circle';
                let stockLabel = 'In Stock';

                if (stock === 0) {
                    stockBadgeClass = 'stock-badge-out';
                    stockIcon = 'fa-times-circle';
                    stockLabel = 'Out of Stock';
                } else if (stock <= threshold) {
                    stockBadgeClass = 'stock-badge-low';
                    stockIcon = 'fa-exclamation-triangle';
                    stockLabel = 'Low Stock';
                }

                // Build image HTML
                const imageHtml = p.image_path
                    ? `<img src="${baseUrl}${this.escapeHtml(p.image_path)}" class="product-suggestion-img" alt="">`
                    : `<div class="product-suggestion-placeholder"><i class="fa-solid fa-box"></i></div>`;

                return `
                <a href="restock.php?mode=single&id=${p.variation_id}" class="product-suggestion">
                    ${imageHtml}
                    <div class="product-suggestion-content">
                        <div class="product-suggestion-name">${highlight(p.product_name)}</div>
                        <div class="product-suggestion-details">
                            <strong>${p.brand_name ? highlight(p.brand_name) : 'No Brand'}</strong> • ${p.variation_name ? highlight(p.variation_name) : 'Default'}
                        </div>
                        <div class="product-suggestion-meta">
                            ${p.sku ? `<span class="product-suggestion-badge sku-badge"><i class="fa-solid fa-tag me-1"></i>${highlight(p.sku)}</span>` : ''}
                            ${p.barcode ? `<span class="product-suggestion-badge barcode-badge"><i class="fa-solid fa-barcode me-1"></i>${highlight(p.barcode)}</span>` : ''}
                        </div>
                    </div>
                    <div class="product-suggestion-right">
                        <span class="product-suggestion-badge ${stockBadgeClass}">
                            <i class="fa-solid ${stockIcon} me-1"></i>${stock} ${p.unit_type || 'pcs'}
                        </span>
                        <div class="price-capital mt-1">₱${parseFloat(p.price_capital || 0).toFixed(2)}</div>
                    </div>
                </a>`;
            }).join('');

            container.innerHTML = html;
            container.classList.remove('d-none');
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
    };

    const BatchRestock = {
        items: [],
        searchTimeout: null,
        creditTerms: [],

        init() {
            const searchInput = document.getElementById('batch-search');
            if (!searchInput) return;

            // Initialize at least one credit term
            this.creditTerms = [{ date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], amount: '' }];
            this.renderCreditTerms();

            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchTimeout);
                const query = e.target.value.trim();
                if (query.length < 2) {
                    document.getElementById('batch-search-results').classList.add('d-none');
                    return;
                }
                this.searchTimeout = setTimeout(() => this.searchProducts(query), 300);
            });

            // Close dropdown on outside click
            document.addEventListener('click', (e) => {
                if (!e.target.closest('#batch-search') && !e.target.closest('#batch-search-results')) {
                    document.getElementById('batch-search-results').classList.add('d-none');
                }
            });

            this.loadState();

            // Auto-save form fields
            ['batch-supplier', 'batch-reference', 'batch-payment-status'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('change', () => this.saveState());
                    el.addEventListener('input', () => this.saveState());
                }
            });
        },

        saveState() {
            localStorage.setItem('restock_batch_items', JSON.stringify(this.items));
            localStorage.setItem('restock_batch_supplier', document.getElementById('batch-supplier').value);
            localStorage.setItem('restock_batch_reference', document.getElementById('batch-reference').value);
            localStorage.setItem('restock_batch_payment_status', document.getElementById('batch-payment-status').value);
            localStorage.setItem('restock_batch_credit_terms', JSON.stringify(this.creditTerms));
        },

        loadState() {
            try {
                const savedItems = localStorage.getItem('restock_batch_items');
                if (savedItems) {
                    this.items = JSON.parse(savedItems);
                    this.renderBatchList();
                }

                const savedSupplier = localStorage.getItem('restock_batch_supplier');
                if (savedSupplier) document.getElementById('batch-supplier').value = savedSupplier;

                const savedRef = localStorage.getItem('restock_batch_reference');
                if (savedRef) document.getElementById('batch-reference').value = savedRef;

                const savedStatus = localStorage.getItem('restock_batch_payment_status');
                if (savedStatus) {
                    document.getElementById('batch-payment-status').value = savedStatus;
                }

                const savedTerms = localStorage.getItem('restock_batch_credit_terms');
                if (savedTerms) {
                    this.creditTerms = JSON.parse(savedTerms);
                    if (this.creditTerms.length === 0) {
                        this.addCreditTerm();
                    }
                }

                this.togglePaymentTerms();
                this.renderCreditTerms(); // Refresh UI
            } catch (e) {
                console.error('Error loading batch state:', e);
            }
        },

        clearState() {
            localStorage.removeItem('restock_batch_items');
            localStorage.removeItem('restock_batch_supplier');
            localStorage.removeItem('restock_batch_reference');
            localStorage.removeItem('restock_batch_payment_status');
            localStorage.removeItem('restock_batch_credit_terms');
            this.creditTerms = [{ date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], amount: '' }];
            this.renderCreditTerms();
            document.getElementById('batch-payment-status').value = 'paid';
            this.togglePaymentTerms();
        },

        togglePaymentTerms() {
            const status = document.getElementById('batch-payment-status').value;
            document.getElementById('batch-credit-terms-container').style.display = status === 'unpaid' ? 'block' : 'none';
            this.updateTermsTotal();
            this.saveState();
        },

        addCreditTerm() {
            const today = new Date();
            today.setDate(today.getDate() + 30);
            this.creditTerms.push({ date: today.toISOString().split('T')[0], amount: '' });
            this.renderCreditTerms();
            this.saveState();
        },

        removeCreditTerm(index) {
            this.creditTerms.splice(index, 1);
            if (this.creditTerms.length === 0) {
                this.addCreditTerm(); // ensure at least one
            }
            this.renderCreditTerms();
            this.saveState();
        },

        updateTermDate(index, date) {
            this.creditTerms[index].date = date;
            this.saveState();
        },

        updateTermAmount(index, amount) {
            this.creditTerms[index].amount = parseFloat(amount) || 0;
            // Auto-fill the LAST term with the remaining balance
            // only when the user edits a non-last term and there are exactly 2 terms
            const last = this.creditTerms.length - 1;
            if (index !== last && this.creditTerms.length >= 2) {
                const batchTotal = this.items.reduce((sum, i) => sum + i.subtotal, 0);
                const otherSum = this.creditTerms.reduce((sum, t, i) => i === last ? sum : sum + (parseFloat(t.amount) || 0), 0);
                const remaining = Math.max(0, parseFloat((batchTotal - otherSum).toFixed(2)));
                this.creditTerms[last].amount = remaining;
            }
            this.renderCreditTerms();
            this.saveState();
        },

        // Split batch total equally across all credit terms
        splitExact() {
            const batchTotal = this.items.reduce((sum, i) => sum + i.subtotal, 0);
            if (batchTotal <= 0) {
                EllaToast.warning('Add items to the batch first.');
                return;
            }
            const count = this.creditTerms.length;
            const share = parseFloat((batchTotal / count).toFixed(2));
            let distributed = 0;
            this.creditTerms.forEach((term, i) => {
                if (i < count - 1) {
                    term.amount = share;
                    distributed += share;
                } else {
                    // Last term absorbs rounding remainder
                    term.amount = parseFloat((batchTotal - distributed).toFixed(2));
                }
            });
            this.renderCreditTerms();
            this.saveState();
            EllaToast.success(`Split ₱${batchTotal.toLocaleString(undefined, { minimumFractionDigits: 2 })} across ${count} term${count > 1 ? 's' : ''}`);
        },

        renderCreditTerms() {
            const container = document.getElementById('batch-credit-terms-list');
            if (!container) return;
            const batchTotal = this.items.reduce((sum, i) => sum + i.subtotal, 0);
            const last = this.creditTerms.length - 1;
            container.innerHTML = this.creditTerms.map((term, index) => {
                const isLast = index === last;
                const otherSum = this.creditTerms.reduce((sum, t, i) => i === index ? sum : sum + (parseFloat(t.amount) || 0), 0);
                const remaining = parseFloat((batchTotal - otherSum).toFixed(2));
                const showHint = isLast && this.creditTerms.length > 1 && batchTotal > 0;
                const hintHtml = showHint
                    ? `<small class="text-muted d-block" style="font-size:0.68rem">Balance: <span class="fw-bold ${remaining < 0 ? 'text-danger' : 'text-success'}">₱${remaining.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span></small>`
                    : '';
                return `
                <div class="row g-2 mb-2 credit-term-row align-items-center">
                    <div class="col-5">
                        <label class="form-label small text-muted mb-0" style="font-size: 0.7rem">Due Date</label>
                        <input type="date" class="form-control form-control-sm" value="${term.date}" min="${new Date().toISOString().split('T')[0]}" onchange="BatchRestock.updateTermDate(${index}, this.value)">
                    </div>
                    <div class="col-5">
                        <label class="form-label small text-muted mb-0" style="font-size: 0.7rem">Amount</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">₱</span>
                            <input type="number" class="form-control text-end" step="0.01" value="${term.amount}" onchange="BatchRestock.updateTermAmount(${index}, this.value)">
                        </div>
                        ${hintHtml}
                    </div>
                    <div class="col-2 text-end">
                        <label class="form-label small mb-0 d-block">&nbsp;</label>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="BatchRestock.removeCreditTerm(${index})">
                            <i class="fa-solid fa-times"></i>
                        </button>
                    </div>
                </div>`;
            }).join('');
            this.updateTermsTotal();
        },

        updateTermsTotal() {
            const batchTotal = this.items.reduce((sum, i) => sum + i.subtotal, 0);
            let scheduledTotal = 0;
            this.creditTerms.forEach(t => {
                scheduledTotal += parseFloat(t.amount) || 0;
            });
            const remaining = parseFloat((batchTotal - scheduledTotal).toFixed(2));

            const batchTotalEl = document.getElementById('terms-batch-total');
            const scheduledTotalEl = document.getElementById('terms-scheduled-total');
            const remainWrap = document.getElementById('terms-remaining-wrap');
            const remainEl = document.getElementById('terms-remaining');

            if (batchTotalEl) batchTotalEl.textContent = '₱' + batchTotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
            if (scheduledTotalEl) {
                scheduledTotalEl.textContent = '₱' + scheduledTotal.toLocaleString(undefined, { minimumFractionDigits: 2 });
                const mismatch = Math.abs(scheduledTotal - batchTotal) > 0.05 && batchTotal > 0;
                scheduledTotalEl.className = 'fw-bold ' + (mismatch ? 'text-danger' : (batchTotal > 0 ? 'text-success' : 'text-primary'));
            }
            if (remainWrap && remainEl) {
                const showRemaining = Math.abs(remaining) > 0.05 && batchTotal > 0;
                remainWrap.classList.toggle('d-none', !showRemaining);
                remainEl.textContent = '₱' + Math.abs(remaining).toLocaleString(undefined, { minimumFractionDigits: 2 });
                remainEl.className = 'fw-bold ' + (remaining < 0 ? 'text-danger' : 'text-warning');
                remainWrap.querySelector('.text-muted, span:first-child');
                remainWrap.firstChild.textContent = remaining < 0 ? 'Over by: ' : 'Remaining: ';
            }
        },

        async searchProducts(query) {
            try {
                const res = await fetch(`../../api/inventory/search_products.php?q=${encodeURIComponent(query)}`);
                const data = await res.json();
                const products = data.products || data;
                this.renderSearchResults(products);
            } catch (err) {
                console.error('Search error:', err);
            }
        },

        renderSearchResults(products) {
            const container = document.getElementById('batch-search-results');
            const query = document.getElementById('batch-search').value.trim();
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
                container.innerHTML = `
                    <div class="search-dropdown-empty">
                        <i class="fa-solid fa-box-open d-block"></i>
                        <div class="fw-medium">No products found</div>
                        <small>Try a different search term</small>
                    </div>`;
                container.classList.remove('d-none');
                return;
            }

            const baseUrl = '<?= BASE_URL ?>';
            let html = `<div class="search-dropdown-header"><i class="fa-solid fa-search me-1"></i>${products.length} result${products.length !== 1 ? 's' : ''} found</div>`;

            html += products.slice(0, 15).map(p => {
                // Determine stock status
                const stock = parseInt(p.current_stock) || 0;
                const threshold = parseInt(p.low_stock_threshold) || 5;
                let stockBadgeClass = 'stock-badge-in';
                let stockIcon = 'fa-check-circle';

                if (stock === 0) {
                    stockBadgeClass = 'stock-badge-out';
                    stockIcon = 'fa-times-circle';
                } else if (stock <= threshold) {
                    stockBadgeClass = 'stock-badge-low';
                    stockIcon = 'fa-exclamation-triangle';
                }

                // Build image HTML
                const imageHtml = p.image_path
                    ? `<img src="${baseUrl}${this.escapeHtml(p.image_path)}" class="product-suggestion-img" alt="">`
                    : `<div class="product-suggestion-placeholder"><i class="fa-solid fa-box"></i></div>`;

                // Safely encode product data for onclick
                const productData = encodeURIComponent(JSON.stringify(p));

                return `
                <div class="product-suggestion" onclick="BatchRestock.selectProduct(JSON.parse(decodeURIComponent('${productData}')))">
                    ${imageHtml}
                    <div class="product-suggestion-content">
                        <div class="product-suggestion-name">${highlight(p.product_name)}</div>
                        <div class="product-suggestion-details">
                            <strong>${p.brand_name ? highlight(p.brand_name) : 'No Brand'}</strong> • ${p.variation_name ? highlight(p.variation_name) : 'Default'}
                        </div>
                        <div class="product-suggestion-meta">
                            ${p.sku ? `<span class="product-suggestion-badge sku-badge"><i class="fa-solid fa-tag me-1"></i>${highlight(p.sku)}</span>` : ''}
                            ${p.barcode ? `<span class="product-suggestion-badge barcode-badge"><i class="fa-solid fa-barcode me-1"></i>${highlight(p.barcode)}</span>` : ''}
                        </div>
                    </div>
                    <div class="product-suggestion-right">
                        <span class="product-suggestion-badge ${stockBadgeClass}">
                            <i class="fa-solid ${stockIcon} me-1"></i>${stock} ${p.unit_type || 'pcs'}
                        </span>
                        <div class="price-capital mt-1">₱${parseFloat(p.price_capital || 0).toFixed(2)}</div>
                    </div>
                </div>`;
            }).join('');

            container.innerHTML = html;
            container.classList.remove('d-none');
        },

        selectProduct(product) {
            // We no longer block here, as the user might want to add the same item as FREE.
            // Duplicate checking is now handled in addToBatch.

            document.getElementById('qa-product-name').textContent = product.product_name;
            document.getElementById('qa-product-details').textContent =
                `${product.brand_name || ''} | ${product.variation_name || ''} | Stock: ${product.current_stock}`;
            document.getElementById('qa-variation-id').value = product.variation_id;
            document.getElementById('qa-current-stock').value = product.current_stock;
            document.getElementById('qa-sku').value = product.sku || '';
            document.getElementById('qa-barcode').value = product.barcode || '';
            document.getElementById('qa-qty').value = 1;
            document.getElementById('qa-cost').value = product.price_capital || '';
            document.getElementById('qa-cost').disabled = false;
            document.getElementById('qa-is-free').checked = false;
            document.getElementById('qa-free-reason-row').classList.add('d-none');
            document.getElementById('qa-free-reason').value = 'Supplier Promo';

            document.getElementById('quick-add-form').classList.remove('d-none');
            document.getElementById('batch-search-results').classList.add('d-none');
            document.getElementById('batch-search').value = '';
            document.getElementById('qa-qty').focus();
        },

        clearQuickAdd() {
            document.getElementById('quick-add-form').classList.add('d-none');
            document.getElementById('qa-is-free').checked = false;
            document.getElementById('qa-cost').disabled = false;
            document.getElementById('qa-free-reason-row').classList.add('d-none');
            document.getElementById('qa-free-reason').value = 'Supplier Promo';
        },

        toggleFree(checkbox) {
            const costInput = document.getElementById('qa-cost');
            const reasonRow = document.getElementById('qa-free-reason-row');
            if (checkbox.checked) {
                costInput.dataset.oldValue = costInput.value;
                costInput.value = 0;
                costInput.disabled = true;
                reasonRow.classList.remove('d-none');
            } else {
                costInput.value = costInput.dataset.oldValue || '';
                costInput.disabled = false;
                reasonRow.classList.add('d-none');
            }
        },

        addToBatch() {
            const variationId = parseInt(document.getElementById('qa-variation-id').value);
            const qty = parseInt(document.getElementById('qa-qty').value);
            const isFree = document.getElementById('qa-is-free').checked;
            const cost = isFree ? 0 : (parseFloat(document.getElementById('qa-cost').value) || 0);
            const currentStock = parseInt(document.getElementById('qa-current-stock').value);
            const sku = document.getElementById('qa-sku').value;
            const barcode = document.getElementById('qa-barcode').value;
            const freeReason = isFree ? document.getElementById('qa-free-reason').value : '';
            const productName = document.getElementById('qa-product-name').textContent;
            const details = document.getElementById('qa-product-details').textContent;

            if (!variationId || qty <= 0) {
                EllaToast.warning('Please enter a valid quantity');
                return;
            }

            // Check for duplicates with the SAME free status
            const existingIndex = this.items.findIndex(i => i.variation_id === variationId && !!i.is_free === !!isFree);
            if (existingIndex !== -1) {
                EllaToast.warning(`This product is already in the batch as ${isFree ? 'FREE' : 'PAID'}. Please update its quantity in the list instead.`);
                return;
            }

            this.items.push({
                variation_id: variationId,
                product_name: productName,
                details: details,
                sku: sku,
                barcode: barcode,
                quantity: qty,
                cost: cost,
                is_free: isFree,
                free_reason: freeReason,
                current_stock: currentStock,
                subtotal: qty * cost
            });

            this.clearQuickAdd();
            this.renderBatchList();
            this.saveState();
        },

        removeFromBatch(index) {
            this.items.splice(index, 1);
            this.renderBatchList();
            this.saveState();
        },

        updateItemQty(index, qty) {
            if (qty <= 0) return;
            this.items[index].quantity = qty;
            this.items[index].subtotal = qty * this.items[index].cost;
            this.renderBatchList();
            this.saveState();
        },

        clearBatch() {
            if (this.items.length === 0) return;
            if (!confirm('Are you sure you want to clear all items from the batch?')) return;
            this.items = [];
            this.renderBatchList();
            this.saveState();
            EllaToast.success('Batch items cleared');
        },

        renderBatchList() {
            const emptyState = document.getElementById('batch-empty');
            const itemsContainer = document.getElementById('batch-items');
            const processBtn = document.getElementById('btn-process-batch');

            if (this.items.length === 0) {
                emptyState.classList.remove('d-none');
                itemsContainer.classList.add('d-none');
                processBtn.disabled = true;
            } else {
                emptyState.classList.add('d-none');
                itemsContainer.classList.remove('d-none');
                processBtn.disabled = false;
            }

            itemsContainer.innerHTML = this.items.map((item, index) => `
            <div class="batch-item d-flex align-items-center p-3 border-bottom ${item.is_free ? 'border-start border-3 border-success bg-success-subtle' : ''}" data-index="${index}">
                <div class="me-3 text-muted drag-handle" style="cursor: grab; padding: 10px 5px;" 
                     onmousedown="this.closest('.batch-item').setAttribute('draggable', 'true')"
                     onmouseup="this.closest('.batch-item').setAttribute('draggable', 'false')"
                     onmouseleave="this.closest('.batch-item').setAttribute('draggable', 'false')">
                    <i class="fa-solid fa-grip-vertical"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <div class="fw-bold">${this.escapeHtml(item.product_name)}</div>
                        ${item.is_free ? `<span class="badge bg-success"><i class="fa-solid fa-gift me-1"></i>FREE</span>` : ''}
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <small class="badge bg-light text-dark border fw-normal">Stock #: ${this.escapeHtml(item.sku || item.barcode || 'N/A')}</small>
                        <small class="text-muted">${this.escapeHtml(item.details)}</small>
                        ${item.is_free && item.free_reason ? `<small class="badge bg-success-subtle text-success border border-success fw-normal">${this.escapeHtml(item.free_reason)}</small>` : ''}
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 100px;">
                        <input type="number" class="form-control text-center fw-bold" value="${item.quantity}" min="1"
                                onfocus="this.select()"
                                onchange="BatchRestock.updateItemQty(${index}, parseInt(this.value))">
                    </div>
                    <div class="text-end" style="min-width: 100px;">
                        ${item.is_free
                    ? `<div class="small text-success fw-bold">₱0.00 / ea</div>`
                    : `<div class="small text-muted">₱${item.cost.toFixed(2)}/ea</div>`
                }
                        <div class="fw-bold ${item.is_free ? 'text-success' : 'text-success'}">₱${item.subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                    </div>
                    <button class="btn btn-sm btn-outline-danger" onclick="BatchRestock.removeFromBatch(${index})">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');

            this._initDragDrop();

            // Update summary
            const totalItems = this.items.length;
            const totalQty = this.items.reduce((sum, i) => sum + i.quantity, 0);
            const totalCost = this.items.reduce((sum, i) => sum + i.subtotal, 0);

            document.getElementById('batch-count').textContent = `${totalItems} items`;
            document.getElementById('summary-items').textContent = totalItems;
            document.getElementById('summary-qty').textContent = totalQty;
            document.getElementById('summary-cost').textContent = '₱' + totalCost.toLocaleString(undefined, {
                minimumFractionDigits: 2
            });
            this.updateTermsTotal(); // Update scheduled amount warning
        },

        _initDragDrop() {
            const container = document.getElementById('batch-items');
            const items = container.querySelectorAll('.batch-item');
            let dragSrcIndex = null;

            items.forEach(item => {
                item.addEventListener('dragstart', (e) => {
                    dragSrcIndex = parseInt(item.dataset.index);
                    item.style.opacity = '0.4';
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', dragSrcIndex);
                });

                item.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    item.classList.add('bg-light');
                    item.style.borderTop = '2px solid #28a745';
                });

                item.addEventListener('dragleave', () => {
                    item.classList.remove('bg-light');
                    item.style.borderTop = '';
                });

                item.addEventListener('drop', (e) => {
                    e.preventDefault();
                    item.classList.remove('bg-light');
                    item.style.borderTop = '';
                    const fromIndex = parseInt(e.dataTransfer.getData('text/plain'));
                    const toIndex = parseInt(item.dataset.index);
                    if (fromIndex !== toIndex) {
                        this.moveItem(fromIndex, toIndex);
                    }
                });

                item.addEventListener('dragend', () => {
                    item.style.opacity = '1';
                    item.style.borderTop = '';
                });
            });
        },

        moveItem(from, to) {
            const item = this.items.splice(from, 1)[0];
            this.items.splice(to, 0, item);
            this.renderBatchList();
            this.saveState();
        },

        async processBatch() {
            const supplierId = document.getElementById('batch-supplier').value;
            const supplierName = document.getElementById('batch-supplier').selectedOptions[0]?.dataset.name || '';
            const reference = document.getElementById('batch-reference').value;

            if (!supplierId) {
                EllaToast.warning('Please select a supplier');
                document.getElementById('batch-supplier').focus();
                return;
            }

            if (this.items.length === 0) {
                EllaToast.warning('Please add at least one product to the batch');
                return;
            }

            if (!confirm(`Process batch restock of ${this.items.length} items?`)) return;

            const btn = document.getElementById('btn-process-batch');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Processing...';

            try {
                const formData = new FormData();
                formData.append('supplier_id', supplierId);
                formData.append('supplier_name', supplierName);
                formData.append('reference', reference);
                formData.append('items', JSON.stringify(this.items));

                // Add payment terms
                const paymentStatus = document.getElementById('batch-payment-status').value;
                formData.append('payment_status', paymentStatus);
                if (paymentStatus === 'unpaid') {
                    let validTerms = [];
                    let scheduledTotal = 0;
                    this.creditTerms.forEach(t => {
                        const amt = parseFloat(t.amount) || 0;
                        if (t.date && amt > 0) {
                            validTerms.push({ due_date: t.date, amount: amt });
                            scheduledTotal += amt;
                        }
                    });

                    if (validTerms.length === 0) {
                        EllaToast.warning('Please add at least one valid payment term.');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-check-circle me-1"></i>Process Batch Restock';
                        return;
                    }

                    const batchTotal = this.items.reduce((sum, i) => sum + i.subtotal, 0);
                    if (Math.abs(scheduledTotal - batchTotal) > 0.05) {
                        if (!confirm(`Warning: Your scheduled payments (₱${scheduledTotal.toFixed(2)}) do not match the total batch cost (₱${batchTotal.toFixed(2)}). Continue anyway?`)) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fa-solid fa-check-circle me-1"></i>Process Batch Restock';
                            return;
                        }
                    }

                    formData.append('credit_terms', JSON.stringify(validTerms));
                }

                const fileInput = document.getElementById('batch-image');
                if (fileInput.files.length > 0) {
                    for (let i = 0; i < fileInput.files.length; i++) {
                        formData.append('reference_images[]', fileInput.files[i]);
                    }
                }

                const res = await fetch('../../api/inventory/process_batch_restock.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();

                if (data.success) {
                    EllaToast.success('Batch restock completed successfully!');
                    this.items = [];
                    this.renderBatchList();
                    document.getElementById('batch-reference').value = '';
                    document.getElementById('batch-image').value = '';
                    document.getElementById('batch-supplier').value = '';
                    RestockUI.selectedFiles['batch-image'] = [];
                    RestockUI.renderPreviews('batch-image', 'batch-image-preview');
                    this.clearState();
                } else {
                    EllaToast.error('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                console.error('Batch restock error:', err);
                EllaToast.error('Network error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-check-circle me-1"></i>Process Batch Restock';
            }
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
    };

    const SingleModeForm = {
        currentStock: 0,

        init() {
            const form = document.getElementById('single-restock-form');
            if (!form) return;

            const variationIdElem = form.querySelector('[name="variation_id"]');
            if (!variationIdElem) return;

            const variationId = variationIdElem.value;
            const cacheKey = 'restock_single_form_' + variationId;

            // Load saved data
            try {
                const saved = localStorage.getItem(cacheKey);
                if (saved) {
                    const data = JSON.parse(saved);
                    Object.keys(data).forEach(key => {
                        const input = form.querySelector(`[name="${key}"]`);
                        if (input && key !== 'reference_image') {
                            input.value = data[key];
                            if (key === 'payment_status') {
                                toggleDueDate(input);
                            }
                        }
                    });
                }
            } catch (e) { }

            // Save on input changes
            form.addEventListener('input', (e) => this.saveData(form, cacheKey));
            form.addEventListener('change', (e) => this.saveData(form, cacheKey));

            // Clear on submit
            form.addEventListener('submit', () => localStorage.removeItem(cacheKey));

            // ---- Total Calculator ----
            const qtyInput = document.getElementById('single-qty');
            const capitalInput = document.getElementById('single-capital');
            if (qtyInput && capitalInput) {
                const currentStockEl = document.getElementById('sf-current');
                this.currentStock = currentStockEl ? parseInt(currentStockEl.textContent) || 0 : 0;

                qtyInput.addEventListener('input', () => this.updateTotal());
                capitalInput.addEventListener('input', () => this.updateTotal());
                this.updateTotal();
            }
        },

        saveData(form, cacheKey) {
            const data = {};
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                if (key !== 'reference_image' && typeof value === 'string') {
                    data[key] = value;
                }
            }
            localStorage.setItem(cacheKey, JSON.stringify(data));
        },

        updateTotal() {
            const qty = parseInt(document.getElementById('single-qty')?.value) || 0;
            const capital = parseFloat(document.getElementById('single-capital')?.value) || 0;
            const total = qty * capital;

            const totalCard = document.getElementById('single-total-card');
            const totalDisplay = document.getElementById('single-total-display');
            const breakdown = document.getElementById('single-calc-breakdown');

            if (totalDisplay) {
                totalDisplay.textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            if (breakdown) {
                breakdown.innerHTML = `<span class="calc-highlight">${qty.toLocaleString()}</span> pcs × ₱<span class="calc-highlight">${capital.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>`;
            }
            if (totalCard) {
                totalCard.classList.toggle('has-value', total > 0);
            }

            // Update summary footer
            const sfAdding = document.getElementById('sf-adding');
            const sfNew = document.getElementById('sf-new');
            const sfTotalCost = document.getElementById('sf-total-cost');

            if (sfAdding) sfAdding.textContent = qty.toLocaleString();
            if (sfNew) sfNew.textContent = (this.currentStock + qty).toLocaleString();
            if (sfTotalCost) sfTotalCost.textContent = '₱' + total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    };

    const RestockDraftUI = {
        modals: { drafts: null, save: null },

        init() {
            const draftsModalEl = document.getElementById('draftsModal');
            const saveModalEl = document.getElementById('saveDraftModal');

            if (draftsModalEl) this.modals.drafts = new bootstrap.Modal(draftsModalEl);
            if (saveModalEl) this.modals.save = new bootstrap.Modal(saveModalEl);

            document.getElementById('btn-confirm-save-draft')?.addEventListener('click', () => {
                this.confirmSaveDraft();
            });
        },

        saveDraft() {
            if (BatchRestock.items.length === 0) {
                EllaToast.warning('Batch is empty. Add items first before saving.');
                return;
            }

            const total = BatchRestock.items.reduce((sum, item) => sum + item.subtotal, 0);
            const summaryEl = document.getElementById('save-draft-summary');
            if (summaryEl) {
                summaryEl.textContent = `${BatchRestock.items.length} item(s) • ₱${total.toLocaleString(undefined, { minimumFractionDigits: 2 })}`;
            }

            document.getElementById('draft-label-input').value = '';
            this.modals.save?.show();
        },

        async confirmSaveDraft() {
            const label = document.getElementById('draft-label-input').value.trim();
            const totalAmount = BatchRestock.items.reduce((sum, item) => sum + item.subtotal, 0);

            const btn = document.getElementById('btn-confirm-save-draft');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Saving...';

            try {
                const supplierId = document.getElementById('batch-supplier').value;
                const supplierName = document.getElementById('batch-supplier').selectedOptions[0]?.dataset.name || '';

                const payload = {
                    supplier_id: supplierId,
                    supplier_name: supplierName,
                    reference: document.getElementById('batch-reference').value,
                    payment_status: document.getElementById('batch-payment-status').value,
                    items: BatchRestock.items,
                    credit_terms: BatchRestock.creditTerms,
                    draft_label: label,
                    total_amount: totalAmount
                };

                const res = await fetch('../../api/inventory/save_restock_draft.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();
                this.modals.save?.hide();

                if (data.success) {
                    EllaToast.success('Restock draft saved successfully!');
                } else {
                    EllaToast.error('Failed to save draft: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                console.error('Save draft error:', err);
                EllaToast.error('Network error saving draft');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        },

        async loadDraft() {
            this.modals.drafts?.show();
            this.setModalState('loading');

            try {
                const res = await fetch('../../api/inventory/list_restock_drafts.php');
                const data = await res.json();

                if (!data.success || !data.drafts || data.drafts.length === 0) {
                    this.setModalState('empty');
                    return;
                }

                this.renderDraftCards(data.drafts);
                this.setModalState('grid');
            } catch (err) {
                console.error('Load drafts error:', err);
                this.setModalState('empty');
                EllaToast.error('Failed to load drafts');
            }
        },

        setModalState(state) {
            const loading = document.getElementById('drafts-loading');
            const empty = document.getElementById('drafts-empty');
            const grid = document.getElementById('drafts-grid');

            loading?.classList.add('d-none');
            empty?.classList.add('d-none');
            grid?.classList.add('d-none');

            if (state === 'loading') loading?.classList.remove('d-none');
            if (state === 'empty') empty?.classList.remove('d-none');
            if (state === 'grid') grid?.classList.remove('d-none');
        },

        renderDraftCards(drafts) {
            const grid = document.getElementById('drafts-grid');
            if (!grid) return;

            grid.innerHTML = drafts.map(draft => {
                const date = new Date(draft.created_at).toLocaleString();
                const total = parseFloat(draft.total_amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
                const label = draft.draft_label || 'Unnamed Draft';
                const supplier = draft.supplier_name || 'No Supplier Selected';

                return `
                <div class="col-md-6">
                    <div class="card h-100 border-primary cursor-pointer hover-shadow transition-all" 
                         onclick="RestockDraftUI.fetchAndLoadDraft(${draft.draft_id})">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="fw-bold text-primary mb-0 text-truncate" style="max-width: 80%;">
                                    <i class="fa-regular fa-file-lines me-1"></i>${this.escapeHtml(label)}
                                </h6>
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" 
                                        onclick="event.stopPropagation(); RestockDraftUI.deleteDraft(${draft.draft_id}, this)">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                            
                            <div class="small text-muted mb-2">
                                <span class="d-block"><i class="fa-solid fa-truck-field me-1"></i>${this.escapeHtml(supplier)}</span>
                                <span class="d-block"><i class="fa-solid fa-clock me-1"></i>${date}</span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                <span class="badge bg-secondary">${draft.item_count} items</span>
                                <span class="fw-bold text-success">₱${total}</span>
                            </div>
                        </div>
                    </div>
                </div>`;
            }).join('');
        },

        async fetchAndLoadDraft(draftId) {
            if (BatchRestock.items.length > 0) {
                if (!confirm("Loading a draft will overwrite your current batch. Continue?")) return;
            }

            try {
                const res = await fetch(`../../api/inventory/load_restock_draft.php?id=${draftId}`);
                const data = await res.json();

                if (!data.success) throw new Error(data.error);

                const draft = data.draft;

                // Load details into UI
                document.getElementById('batch-supplier').value = draft.supplier_id || '';
                document.getElementById('batch-reference').value = draft.reference || '';
                document.getElementById('batch-payment-status').value = draft.payment_status || 'paid';

                BatchRestock.items = draft.items || [];
                BatchRestock.creditTerms = draft.credit_terms || [];
                if (BatchRestock.creditTerms.length === 0) {
                    BatchRestock.addCreditTerm(); // ensure at least one
                }

                // Update UI state
                BatchRestock.togglePaymentTerms();
                BatchRestock.renderCreditTerms();
                BatchRestock.renderBatchList();
                BatchRestock.saveState(); // Persist to local storage

                this.modals.drafts?.hide();
                EllaToast.success('Draft loaded successfully!');

            } catch (err) {
                console.error('Load draft details error:', err);
                EllaToast.error('Failed to load draft data');
            }
        },

        async deleteDraft(draftId, btnElement) {
            if (!confirm("Are you sure you want to delete this draft?")) return;

            const originalHtml = btnElement.innerHTML;
            btnElement.disabled = true;
            btnElement.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

            try {
                const res = await fetch('../../api/inventory/delete_restock_draft.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ draft_id: draftId })
                });

                const data = await res.json();
                if (data.success) {
                    const card = btnElement.closest('.col-md-6');
                    if (card) {
                        card.remove();
                        const grid = document.getElementById('drafts-grid');
                        if (!grid.children.length) this.setModalState('empty');
                    }
                    EllaToast.success('Draft deleted');
                } else {
                    EllaToast.error('Failed to delete: ' + data.error);
                }
            } catch (err) {
                console.error('Delete draft error:', err);
                EllaToast.error('Network error deleting draft');
            } finally {
                btnElement.disabled = false;
                btnElement.innerHTML = originalHtml;
            }
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
    };

    const RestockUI = {
        selectedFiles: {
            'single-image': [],
            'batch-image': []
        },

        previewImages(input, containerId) {
            const inputId = input.id;
            const container = document.getElementById(containerId);
            const newFiles = Array.from(input.files);
            
            // Accumulate files
            newFiles.forEach(file => {
                if (this.selectedFiles[inputId].length < 8) {
                    // Check for duplicates (optional but good)
                    const isDuplicate = this.selectedFiles[inputId].some(f => f.name === file.name && f.size === file.size);
                    if (!isDuplicate) {
                        this.selectedFiles[inputId].push(file);
                    }
                }
            });

            if (this.selectedFiles[inputId].length > 8) {
                if(typeof EllaToast !== 'undefined') EllaToast.warning('Maximum 8 images allowed. Excess files were ignored.');
                else alert('Maximum 8 images allowed.');
                this.selectedFiles[inputId] = this.selectedFiles[inputId].slice(0, 8);
            }

            this.renderPreviews(inputId, containerId);
            this.syncInput(inputId);
        },

        renderPreviews(inputId, containerId) {
            const container = document.getElementById(containerId);
            const files = this.selectedFiles[inputId];
            container.innerHTML = '';

            if (files.length > 0) {
                const header = document.createElement('div');
                header.className = 'w-100 mb-1';
                header.innerHTML = `<small class="text-primary fw-bold"><i class="fa-solid fa-images me-1"></i>${files.length} images selected</small>`;
                container.appendChild(header);

                files.forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'position-relative';
                        wrapper.style.width = '65px';
                        wrapper.style.height = '65px';
                        
                        wrapper.innerHTML = `
                            <img src="${e.target.result}" class="rounded border shadow-sm" style="width: 100%; height: 100%; object-fit: cover;">
                            <button type="button" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light p-1" 
                                    style="cursor: pointer; line-height: 1;" onclick="RestockUI.removeFile('${inputId}', '${containerId}', ${index})">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        `;
                        container.appendChild(wrapper);
                    }
                    reader.readAsDataURL(file);
                });
            }
        },

        removeFile(inputId, containerId, index) {
            this.selectedFiles[inputId].splice(index, 1);
            this.renderPreviews(inputId, containerId);
            this.syncInput(inputId);
        },

        syncInput(inputId) {
            const input = document.getElementById(inputId);
            const dataTransfer = new DataTransfer();
            this.selectedFiles[inputId].forEach(file => dataTransfer.items.add(file));
            input.files = dataTransfer.files;
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        SingleModeSearch.init();
        BatchRestock.init();
        SingleModeForm.init();
        RestockDraftUI.init();

        // Check for draft_id from Admin Draft Management
        const urlParams = new URLSearchParams(window.location.search);
        const draftId = urlParams.get('draft_id');
        if (draftId) {
            // Clear param from URL without reload
            const url = new URL(window.location);
            url.searchParams.delete('draft_id');
            window.history.replaceState({}, '', url);

            // Switch to batch mode first
            switchMode('batch');
            
            // Show loading toast immediately
            if (typeof EllaToast !== 'undefined') {
                EllaToast.info('Loading draft...');
            }

            // Load the draft
            RestockDraftUI.fetchAndLoadDraft(draftId);
        }
    });

</script>

<?php require_once '../../includes/footer.php'; ?>