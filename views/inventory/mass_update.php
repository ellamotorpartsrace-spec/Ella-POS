<?php
// views/inventory/mass_update.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin') {
    denyAccess("You do not have permission to access mass updates.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

// Get unique brands for dropdown
$db = new Database();
$conn = $db->getConnection();
$brands = $conn->query("SELECT DISTINCT brand_name FROM products WHERE brand_name IS NOT NULL AND brand_name != '' ORDER BY brand_name")->fetchAll(PDO::FETCH_COLUMN);

// Date filters for batches
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Get recent restock batches
$batch_sql = "
    SELECT reference, remarks, DATE(created_at) as date, COUNT(*) as item_count
    FROM stock_movements 
    WHERE type = 'stock_in' 
";
$batch_params = [];

if (!empty($date_from)) {
    $batch_sql .= " AND DATE(created_at) >= ?";
    $batch_params[] = $date_from;
}
if (!empty($date_to)) {
    $batch_sql .= " AND DATE(created_at) <= ?";
    $batch_params[] = $date_to;
}

$batch_sql .= " GROUP BY reference ORDER BY created_at DESC LIMIT 50";
$stmtBatches = $conn->prepare($batch_sql);
$stmtBatches->execute($batch_params);
$batches = $stmtBatches->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-dark fw-bold">🔄 Mass Update Products</h4>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Inventory
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-check-circle"></i>
            <strong>Success!</strong> <?= htmlspecialchars($_GET['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <strong>Error:</strong> <?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['warnings'])):
        $warnings = json_decode(urldecode($_GET['warnings']), true);
        if ($warnings && count($warnings) > 0):
            ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-exclamation-triangle"></i>
                <strong>Warnings:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($warnings as $warn): ?>
                        <li><?= htmlspecialchars($warn) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; endif; ?>

    <?php if (isset($_GET['log'])):
        $log_filename = basename($_GET['log']); // sanitize
        $log_path = __DIR__ . '/../../logs/' . $log_filename;
        if (file_exists($log_path)):
            $log_contents = file_get_contents($log_path);
            ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center"
                    data-bs-toggle="collapse" data-bs-target="#logViewer" role="button">
                    <span><i class="fa-solid fa-file-lines"></i> Debug Log: <?= htmlspecialchars($log_filename) ?></span>
                    <span class="badge bg-light text-dark">Click to expand/collapse</span>
                </div>
                <div class="collapse show" id="logViewer">
                    <div class="card-body p-0">
                        <pre class="mb-0 p-3"
                            style="max-height: 400px; overflow-y: auto; background: #1e1e1e; color: #d4d4d4; font-size: 12px; white-space: pre-wrap; word-wrap: break-word;"><?= htmlspecialchars($log_contents) ?></pre>
                    </div>
                </div>
            </div>
        <?php endif; endif; ?>

    <div class="row g-4">

        <!-- Step 1: Download Products -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="fa-solid fa-download"></i> Step 1: Download Products
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Filter by batch, brand, or search products to download a template.</p>

                    <form id="downloadForm" action="../../api/inventory/download_update_template.php" method="GET">

                        <div class="row g-2 mb-3">
                            <div class="col-md-5">
                                <label class="form-label fw-bold">Date From</label>
                                <input type="date" name="date_from" class="form-control"
                                    value="<?= htmlspecialchars($date_from) ?>">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold">Date To</label>
                                <input type="date" name="date_to" class="form-control"
                                    value="<?= htmlspecialchars($date_to) ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-secondary w-100" id="filterBatchesBtn"
                                    title="Filter Batch List">
                                    <i class="fa-solid fa-filter"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Filter by Restock Batch</label>
                            <select name="batch" class="form-select" id="batchSelect">
                                <option value="">-- All Batches --</option>
                                <?php foreach ($batches as $batch): ?>
                                    <option value="<?= htmlspecialchars($batch['reference']) ?>" <?= ($_GET['batch'] ?? '') === $batch['reference'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($batch['date']) ?> -
                                        <?= htmlspecialchars($batch['reference']) ?>
                                        (<?= $batch['item_count'] ?> items)
                                        <?= !empty($batch['remarks']) ? '- ' . htmlspecialchars($batch['remarks']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Narrow down the list using the date filters above.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Filter by Brand <small
                                    class="text-muted fw-normal">(Select multiple)</small></label>
                            <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                <div class="form-check border-bottom pb-2 mb-2">
                                    <input class="form-check-input" type="checkbox" id="selectAllBrands" checked>
                                    <label class="form-check-label fw-bold" for="selectAllBrands">
                                        All Brands
                                    </label>
                                </div>
                                <?php foreach ($brands as $index => $brand): ?>
                                    <div class="form-check">
                                        <input class="form-check-input brand-checkbox" type="checkbox" name="brand[]"
                                            value="<?= htmlspecialchars($brand) ?>" id="brand_<?= $index ?>" checked>
                                        <label class="form-check-label" for="brand_<?= $index ?>">
                                            <?= htmlspecialchars($brand) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Or Search Products</label>
                            <input type="text" name="search" class="form-control"
                                placeholder="e.g. Oil, SKU123, 908123 (comma-separated)">
                            <div class="form-text">You can search for multiple products by separating them with commas.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fa-solid fa-file-csv"></i> Download CSV with Products
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Step 2: Upload Updates -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-success text-white fw-bold">
                    <i class="fa-solid fa-upload"></i> Step 2: Upload Updates
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Edit the downloaded CSV and upload to apply changes.</p>

                    <form action="../../api/inventory/process_mass_update.php" method="POST"
                        enctype="multipart/form-data" id="uploadForm">

                        <div class="mb-4">
                            <label class="form-label fw-bold">Select Edited File <span
                                    class="text-danger">*</span></label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv,.xlsx,.xls" required
                                id="csvFile">
                            <div class="form-text">
                                <i class="fa-solid fa-info-circle"></i>
                                Supported formats: CSV (.csv), Excel (.xlsx, .xls)
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100" id="submitBtn">
                            <i class="fa-solid fa-upload"></i> Upload & Apply Updates
                        </button>

                    </form>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">
                    <i class="fa-solid fa-book"></i> How It Works
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                                    style="width: 50px; height: 50px;">
                                    <i class="fa-solid fa-download fa-lg"></i>
                                </div>
                            </div>
                            <h6 class="fw-bold text-center">1. Download</h6>
                            <p class="text-muted small text-center">Select a brand or search, then download the CSV with
                                current product data as reference.</p>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <div class="bg-warning text-dark rounded-circle d-inline-flex align-items-center justify-content-center"
                                    style="width: 50px; height: 50px;">
                                    <i class="fa-solid fa-pen fa-lg"></i>
                                </div>
                            </div>
                            <h6 class="fw-bold text-center">2. Edit</h6>
                            <p class="text-muted small text-center">Open in Excel, modify the prices or other fields you
                                want to update. Keep <code>variation_id</code> intact.</p>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center"
                                    style="width: 50px; height: 50px;">
                                    <i class="fa-solid fa-upload fa-lg"></i>
                                </div>
                            </div>
                            <h6 class="fw-bold text-center">3. Upload</h6>
                            <p class="text-muted small text-center">Upload the edited CSV. Products are matched by
                                <code>variation_id</code> and updated automatically.
                            </p>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-primary"><i class="fa-solid fa-check text-success"></i> Updatable
                                Fields:</h6>
                            <ul class="small mb-0">
                                <li><code>product_name</code>, <code>brand_name</code>, <code>variation_name</code>,
                                    <code>unit_type</code>
                                </li>
                                <li><code>price_capital</code>, <code>price_retail</code>, <code>price_wholesale</code>,
                                    <code>price_dealer</code>
                                </li>
                                <li><code>current_stock</code> - Stock changes will be recorded as movements</li>
                                <li><code>low_stock_threshold</code>, <code>status</code></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-danger"><i class="fa-solid fa-xmark text-danger"></i> Read-Only
                                Fields:</h6>
                            <ul class="small mb-0">
                                <li><code>product_id</code>, <code>variation_id</code> - Used for matching, don't change
                                </li>
                            </ul>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<script>
    // Brand Checkboxes Logic
    const selectAllBrands = document.getElementById('selectAllBrands');
    const brandCheckboxes = document.querySelectorAll('.brand-checkbox');

    if (selectAllBrands && brandCheckboxes) {
        selectAllBrands.addEventListener('change', function () {
            brandCheckboxes.forEach(cb => cb.checked = this.checked);
        });

        brandCheckboxes.forEach(cb => {
            cb.addEventListener('change', function () {
                const allChecked = Array.from(brandCheckboxes).every(c => c.checked);
                const allUnchecked = Array.from(brandCheckboxes).every(c => !c.checked);

                selectAllBrands.checked = allChecked;
                selectAllBrands.indeterminate = !allChecked && !allUnchecked;
            });
        });
    }

    document.getElementById('downloadForm').addEventListener('submit', function (e) {
        const isAllBrands = selectAllBrands.checked;
        const hasCheckedBrands = document.querySelectorAll('.brand-checkbox:checked').length > 0;
        const searchVal = document.querySelector('input[name="search"]').value.trim();

        if (!isAllBrands && !hasCheckedBrands && searchVal === '') {
            e.preventDefault();
            alert('Please select at least one brand or enter a search term.');
            return;
        }

        // To prevent massive URL, if all brands are selected we don't send any brand parameter
        // The backend treats empty brand filter as "all"
        if (isAllBrands) {
            brandCheckboxes.forEach(cb => cb.disabled = true);
            setTimeout(() => {
                brandCheckboxes.forEach(cb => cb.disabled = false);
            }, 100);
        }
    });

    // Date filtering logic to refresh page
    document.getElementById('filterBatchesBtn').addEventListener('click', function () {
        const from = document.querySelector('input[name="date_from"]').value;
        const to = document.querySelector('input[name="date_to"]').value;
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('date_from', from);
        currentUrl.searchParams.set('date_to', to);
        window.location.href = currentUrl.toString();
    });

    // Show loading on submit for Upload
    document.getElementById('uploadForm').addEventListener('submit', function () {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
    });
</script>

<?php require_once '../../includes/footer.php'; ?>