<?php
// views/inventory/edit.php
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

// 1. Get the ID
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}
$variation_id = $_GET['id'];

// 2. Fetch Existing Data
$db = new Database();
$conn = $db->getConnection();

$sql = "
    SELECT 
        p.product_id, p.product_name, p.brand_name, p.category_id, p.description, p.image_path, p.created_at,
        v.variation_id, v.variation_name, v.sku, v.barcode, v.unit_type,
        v.price_capital, v.price_retail, v.price_wholesale, v.price_dealer, v.low_stock_threshold, v.status,
        COALESCE(i.quantity, 0) as current_stock
    FROM product_variations v
    JOIN products p ON v.product_id = p.product_id
    LEFT JOIN inventory i ON v.variation_id = i.variation_id
    WHERE v.variation_id = :id
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->execute([':id' => $variation_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "<div class='container p-4'><h3>❌ Product not found.</h3><a href='index.php'>Go Back</a></div>";
    require_once '../../includes/footer.php';
    exit;
}

// 3. Fetch Categories
$cats = $conn->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

// 4. NEW: Fetch Recent Price History (Last 5 changes)
$sqlHist = "
    SELECT h.*, u.username 
    FROM product_price_history h
    LEFT JOIN users u ON h.user_id = u.id
    WHERE h.variation_id = :id
    ORDER BY h.changed_at DESC
    LIMIT 5
";
$stmtHist = $conn->prepare($sqlHist);
$stmtHist->execute([':id' => $variation_id]);
$historyLogs = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

// 5. Fetch Original Creator
$sqlCreator = "
    SELECT u.username, l.created_at
    FROM activity_logs l
    JOIN users u ON l.user_id = u.id
    WHERE l.item_id = :id AND l.action_type = 'CREATE_PRODUCT'
    ORDER BY l.created_at ASC
    LIMIT 1
";
$stmtCreator = $conn->prepare($sqlCreator);
$stmtCreator->execute([':id' => $variation_id]);
$creatorInfo = $stmtCreator->fetch(PDO::FETCH_ASSOC);

if (!$creatorInfo) {
    // Fallback: Check stock_movements for Initial Stock (handles manual and batch uploads)
    $sqlFallback = "
        SELECT u.username, m.created_at
        FROM stock_movements m
        JOIN users u ON m.created_by = u.id
        WHERE m.variation_id = :id AND (m.remarks LIKE 'Initial Stock%' OR m.remarks LIKE 'Batch Upload%')
        ORDER BY m.created_at ASC
        LIMIT 1
    ";
    $stmtFallback = $conn->prepare($sqlFallback);
    $stmtFallback->execute([':id' => $variation_id]);
    $creatorInfo = $stmtFallback->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 text-dark fw-bold">✏️ Edit Product</h4>
            <small class="text-muted">Updating: <?= htmlspecialchars($product['product_name']) ?>
                (<?= htmlspecialchars($product['variation_name']) ?>)</small>
        </div>
        <div>
            <a href="history.php?id=<?= $variation_id ?>" class="btn btn-info text-white me-2">
                <i class="fa-solid fa-clock-rotate-left"></i> View Full History
            </a>
            <a href="<?= htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'index.php') ?>"
                class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left"></i> Cancel
            </a>
        </div>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation"></i> Error: <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <form action="../../api/inventory/update_product.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'index.php') ?>">
        <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
        <input type="hidden" name="variation_id" value="<?= $product['variation_id'] ?>">

        <div class="row g-4">

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white fw-bold">1. General Information</div>
                    <div class="card-body">

                        <div class="text-center mb-3">
                            <?php if (!empty($product['image_path'])): ?>
                                <img src="<?= BASE_URL . $product['image_path'] ?>" class="img-thumbnail"
                                    style="max-height: 150px;">
                                <div class="small text-muted mt-1">Current Image</div>
                            <?php else: ?>
                                <div class="py-4 bg-light rounded text-muted border border-dashed">
                                    <i class="fa-solid fa-image fa-2x mb-2"></i><br>No Image Uploaded
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Product Name</label>
                            <input type="text" name="product_name" class="form-control"
                                value="<?= htmlspecialchars($product['product_name']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select">
                                <?php foreach ($cats as $c): ?>
                                    <option value="<?= $c['category_id'] ?>" <?= $c['category_id'] == $product['category_id'] ? 'selected' : '' ?>>
                                        <?= $c['category_name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand_name" class="form-control"
                                value="<?= htmlspecialchars($product['brand_name'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control"
                                rows="2"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Active
                                    (Visible)</option>
                                <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>
                                    Inactive (Hidden)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-primary fw-bold"><i class="fa-solid fa-camera"></i> Update
                                Image</label>
                            <input type="file" name="product_image" class="form-control" accept="image/*">
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fa-solid fa-user-plus text-primary"></i>
                                    </div>
                                </div>
                                <div class="ms-3">
                                    <div class="small text-muted mb-0" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;">Original Creator</div>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($creatorInfo['username'] ?? 'System / Imported') ?></div>
                                    <?php 
                                        $displayDate = $creatorInfo['created_at'] ?? $product['created_at'] ?? null;
                                        if ($displayDate): 
                                    ?>
                                        <div class="text-muted" style="font-size: 0.75rem;">
                                            <i class="fa-regular fa-calendar-alt me-1"></i>
                                            <?= date('M d, Y h:i A', strtotime($displayDate)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="col-lg-8">

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white fw-bold">2. Pricing & Variations</div>
                    <div class="card-body">

                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Variation Name</label>
                                <input type="text" name="variation_name" class="form-control"
                                    value="<?= htmlspecialchars($product['variation_name']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Unit Type</label>
                                <select name="unit_type" class="form-select">
                                    <?php
                                    $units = ['pc', 'set', 'box', 'pair', 'bottle', 'kit', 'roll'];
                                    foreach ($units as $u):
                                        ?>
                                        <option value="<?= $u ?>" <?= ($product['unit_type'] ?? 'pc') == $u ? 'selected' : '' ?>>
                                            <?= ucfirst($u) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label"><i class="fa-solid fa-barcode"></i> Barcode</label>
                                <input type="text" name="barcode" class="form-control font-monospace"
                                    value="<?= htmlspecialchars($product['barcode'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SKU / Part Number</label>
                                <input type="text" name="sku" class="form-control"
                                    value="<?= htmlspecialchars($product['sku'] ?? '') ?>">
                            </div>
                        </div>

                        <hr class="text-muted opacity-25">

                        <div class="row mb-4">
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <div class="col-md-3">
                                    <label class="form-label text-muted small">Capital (Cost)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">₱</span>
                                        <input type="number" step="0.01" name="price_capital" class="form-control"
                                            value="<?= $product['price_capital'] ?>" placeholder="Leave blank for auto"
                                            <?= !hasPermission('adjust_prices') ? 'readonly' : '' ?>>
                                    </div>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="price_capital" value="<?= $product['price_capital'] ?>">
                            <?php endif; ?>

                            <div class="col-md-3">
                                <label class="form-label fw-bold text-primary small">Retail (SRP)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">₱</span>
                                    <input type="number" step="0.01" name="price_retail" class="form-control fw-bold"
                                        value="<?= $product['price_retail'] ?>" required
                                        <?= !hasPermission('adjust_prices') ? 'readonly' : '' ?>>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label text-muted small">Wholesale</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">₱</span>
                                    <input type="number" step="0.01" name="price_wholesale" class="form-control"
                                        value="<?= $product['price_wholesale'] ?>" <?= !hasPermission('adjust_prices') ? 'readonly' : '' ?>>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label text-muted small">Dealer Price</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">₱</span>
                                    <input type="number" step="0.01" name="price_dealer" class="form-control"
                                        value="<?= $product['price_dealer'] ?>" <?= !hasPermission('adjust_prices') ? 'readonly' : '' ?>>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card border-warning h-100 mb-0 shadow-sm overflow-hidden" style="border-left: 5px solid #ffc107 !important;">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="flex-shrink-0 bg-warning bg-opacity-10 rounded p-2 me-3">
                                                <i class="fa-solid fa-boxes-stacked fa-xl text-warning"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <label class="form-label fw-bold mb-0 small text-uppercase text-muted">Current Inventory</label>
                                                <div class="h4 mb-0 fw-bold text-dark"><?= $product['current_stock'] ?> <small class="text-muted fw-normal"><?= $product['unit_type'] ?></small></div>
                                            </div>
                                        </div>
                                        
                                        <div class="bg-light p-2 rounded border">
                                            <label class="small fw-bold text-dark mb-2 d-block"><i class="fa-solid fa-bolt text-warning"></i> Quick Stock Adjustment</label>
                                            <div class="row g-2">
                                                <div class="col-4">
                                                    <select name="stock_adj_type" class="form-select form-select-sm shadow-none">
                                                        <option value="">None</option>
                                                        <option value="add" class="text-success fw-bold">➕ Add</option>
                                                        <option value="subtract" class="text-danger fw-bold">➖ Reduce</option>
                                                    </select>
                                                </div>
                                                <div class="col-3">
                                                    <input type="number" step="any" name="stock_adj_qty" class="form-control form-select-sm shadow-none text-center" placeholder="Qty">
                                                </div>
                                                <div class="col-5">
                                                    <input type="text" name="stock_adj_remarks" class="form-control form-select-sm shadow-none" placeholder="Remarks...">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="alert alert-light border d-flex align-items-center h-100 mb-0" role="alert">
                                    <i class="fa-solid fa-bell fa-2x text-warning me-3"></i>
                                    <div class="flex-grow-1">
                                        <label class="form-label fw-bold mb-0">Alert Level</label>
                                        <div class="small text-muted">Notify when &lt; X</div>
                                    </div>
                                    <div style="width: 100px;">
                                        <input type="number" name="low_stock_threshold"
                                            class="form-control fw-bold text-center"
                                            value="<?= $product['low_stock_threshold'] ?? 5 ?>" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="card-footer bg-white border-top-0 d-flex justify-content-end py-3">
                        <button type="submit" class="btn btn-primary btn-lg px-4 shadow-sm">
                            <i class="fa-solid fa-save"></i> Update Product
                        </button>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-light fw-bold text-muted">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> Recent Price Adjustments
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0 small">
                                <thead>
                                    <tr>
                                        <th class="ps-3">Date</th>
                                        <th>Capital Change</th>
                                        <th>Retail Change</th>
                                        <th>Wholesale Change</th>
                                        <th>Dealer Change</th>
                                        <th>Updated By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($historyLogs) > 0): ?>
                                        <?php foreach ($historyLogs as $log): ?>
                                            <tr>
                                                <td class="ps-3"><?= date('M d, Y', strtotime($log['changed_at'])) ?></td>
                                                <td>
                                                    <?php if ($log['old_capital'] != $log['new_capital']): ?>
                                                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                                            <span
                                                                class="text-decoration-line-through text-muted">₱<?= $log['old_capital'] ?></span>
                                                            <i class="fa-solid fa-arrow-right text-muted mx-1"
                                                                style="font-size: 0.8em;"></i>
                                                            <span class="fw-bold">₱<?= $log['new_capital'] ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">***</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($log['old_retail'] != $log['new_retail']): ?>
                                                        <span
                                                            class="text-decoration-line-through text-muted">₱<?= $log['old_retail'] ?></span>
                                                        <i class="fa-solid fa-arrow-right text-muted mx-1"
                                                            style="font-size: 0.8em;"></i>
                                                        <span class="fw-bold text-success">₱<?= $log['new_retail'] ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (($log['old_wholesale'] ?? 0) != ($log['new_wholesale'] ?? 0)): ?>
                                                        <span
                                                            class="text-decoration-line-through text-muted">₱<?= $log['old_wholesale'] ?></span>
                                                        <i class="fa-solid fa-arrow-right text-muted mx-1"
                                                            style="font-size: 0.8em;"></i>
                                                        <span class="fw-bold text-success">₱<?= $log['new_wholesale'] ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (($log['old_dealer'] ?? 0) != ($log['new_dealer'] ?? 0)): ?>
                                                        <span
                                                            class="text-decoration-line-through text-muted">₱<?= $log['old_dealer'] ?></span>
                                                        <i class="fa-solid fa-arrow-right text-muted mx-1"
                                                            style="font-size: 0.8em;"></i>
                                                        <span class="fw-bold text-success">₱<?= $log['new_dealer'] ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-secondary"><?= htmlspecialchars($log['username']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-3 text-muted">No recent price changes
                                                recorded.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>