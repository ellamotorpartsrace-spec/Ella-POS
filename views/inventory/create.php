<?php
// views/inventory/create.php
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

// Fetch Categories
$db = new Database();
$conn = $db->getConnection();
$cats = $conn->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();
?>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-dark fw-bold">✨ Add New Product</h4>
        <div>
            <a href="batch_upload.php" class="btn btn-outline-primary me-2">
                <i class="fa-solid fa-file-csv"></i> Batch Upload
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left"></i> Cancel
            </a>
        </div>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-triangle-exclamation"></i> Error: <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <form action="../../api/inventory/save_product.php" method="POST" enctype="multipart/form-data">
        <div class="row g-4">

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white fw-bold">1. General Information</div>
                    <div class="card-body">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="product_name" class="form-control"
                                placeholder="e.g. Michelin City Grip" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select">
                                <?php foreach ($cats as $c): ?>
                                    <option value="<?= $c['category_id'] ?>"><?= $c['category_name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand_name" class="form-control" placeholder="e.g. Yamaha">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Product Image</label>
                            <input type="file" name="product_image" class="form-control" accept="image/*">
                            <div class="form-text small">Formats: JPG, PNG, WEBP (Max 2MB)</div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white fw-bold">2. Pricing & Stock Details</div>
                    <div class="card-body">

                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label fw-bold">Variation / Size / Color</label>
                                <input type="text" name="variation_name" class="form-control"
                                    placeholder="e.g. Front 110/70 (Leave empty if none)">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Unit Type</label>
                                <select name="unit_type" class="form-select">
                                    <option value="pc">Piece (pc)</option>
                                    <option value="set">Set</option>
                                    <option value="box">Box</option>
                                    <option value="pair">Pair</option>
                                    <option value="bottle">Bottle</option>
                                    <option value="kit">Kit</option>
                                    <option value="roll">Roll</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label"><i class="fa-solid fa-barcode"></i> Barcode (Scan
                                    Here)</label>
                                <input type="text" name="barcode" class="form-control font-monospace"
                                    placeholder="Scan or type...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SKU / Part Number</label>
                                <input type="text" name="sku" class="form-control" placeholder="Unique ID">
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
                                            placeholder="Leave blank for auto">
                                    </div>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="price_capital" value="">
                            <?php endif; ?>

                            <div class="col-md-3">
                                <label class="form-label fw-bold text-primary small">Retail (SRP)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white">₱</span>
                                    <input type="number" step="0.01" name="price_retail" class="form-control fw-bold"
                                        placeholder="0.00" required>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label text-muted small">Wholesale</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">₱</span>
                                    <input type="number" step="0.01" name="price_wholesale" class="form-control"
                                        placeholder="0.00">
                                </div>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label text-muted small">Dealer Price</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">₱</span>
                                    <input type="number" step="0.01" name="price_dealer" class="form-control"
                                        placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <hr class="text-muted opacity-25">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="alert alert-light border d-flex align-items-center h-100 mb-0" role="alert">
                                    <i class="fa-solid fa-boxes-stacked fa-2x text-secondary me-3"></i>
                                    <div class="flex-grow-1">
                                        <label class="form-label fw-bold mb-0">Initial Stock</label>
                                        <div class="small text-muted">Current Qty</div>
                                    </div>
                                    <div style="width: 100px;">
                                        <input type="number" name="initial_stock"
                                            class="form-control fw-bold text-center" value="0" min="0">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="alert alert-light border d-flex align-items-center h-100 mb-0" role="alert">
                                    <i class="fa-solid fa-bell fa-2x text-warning me-3"></i>
                                    <div class="flex-grow-1">
                                        <label class="form-label fw-bold mb-0">Alert Level</label>
                                        <div class="small text-muted">Notify when < X</div>
                                        </div>
                                        <div style="width: 100px;">
                                            <input type="number" name="low_stock_threshold"
                                                class="form-control fw-bold text-center" value="5" min="0">
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="card-footer bg-white border-top-0 d-flex justify-content-end py-3">
                            <button type="submit" class="btn btn-success btn-lg px-4 shadow-sm">
                                <i class="fa-solid fa-save"></i> Save Product
                            </button>
                        </div>
                    </div>
                </div>

            </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>

<script>
    document.querySelector('form').addEventListener('submit', function (e) {
        const retailInput = document.querySelector('input[name="price_retail"]');
        const wholesaleInput = document.querySelector('input[name="price_wholesale"]');
        const dealerInput = document.querySelector('input[name="price_dealer"]');

        if (retailInput && wholesaleInput && dealerInput) {
            const rVal = retailInput.value;
            const wVal = wholesaleInput.value;
            const dVal = dealerInput.value;

            const rPrice = rVal !== '' ? parseFloat(rVal) : 0;

            if (wVal !== '' && parseFloat(wVal) > rPrice) {
                e.preventDefault();
                EllaToast.error("Error: Wholesale Price cannot be higher than Retail Price.");
                wholesaleInput.focus();
                return;
            }

            if (dVal !== '' && parseFloat(dVal) > rPrice) {
                e.preventDefault();
                EllaToast.error("Error: Dealer Price cannot be higher than Retail Price.");
                dealerInput.focus();
                return;
            }

            if (wVal !== '' && dVal !== '') {
                if (parseFloat(dVal) > parseFloat(wVal)) {
                    e.preventDefault();
                    EllaToast.error("Error: Dealer Price cannot be higher than Wholesale Price. They can be equal, but Dealer cannot be strictly higher.");
                    dealerInput.focus();
                }
            } else if (wVal === '' && dVal !== '') {
                e.preventDefault();
                EllaToast.error("Error: Please provide a Wholesale Price first if you want to set a Dealer Price.");
                wholesaleInput.focus();
            }
        }
    });
</script>