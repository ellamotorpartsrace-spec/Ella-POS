<?php
// views/inventory/batch_upload.php
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

// Fetch Categories for template
$db = new Database();
$conn = $db->getConnection();
$cats = $conn->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();
$suppliers = $conn->query("SELECT * FROM suppliers ORDER BY supplier_name ASC")->fetchAll();
?>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-dark fw-bold">📦 Batch Product Upload</h4>
        <div>
            <a href="../../api/inventory/download_template.php" class="btn btn-outline-primary me-2">
                <i class="fa-solid fa-download"></i> Download CSV Template
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
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

    <div class="row g-4">

        <!-- Step 1: Upload Form -->
        <div class="col-lg-12" id="uploadStep">
            <div class="card shadow-sm border-0 mb-4">
                <div
                    class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-upload"></i> Step 1: Upload File</span>
                    <span class="badge bg-light text-primary">Initial Step</span>
                </div>
                <div class="card-body">
                    <form id="initialUploadForm">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Select File <span class="text-danger">*</span></label>
                                <input type="file" name="csv_file" class="form-control" accept=".csv,.xlsx,.xls"
                                    required id="csvFile">
                                <div class="form-text">
                                    <i class="fa-solid fa-info-circle"></i> Supported: CSV, Excel (.xlsx, .xls)
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Default Supplier (Optional)</label>
                                <select name="supplier_id" id="supplier_id" class="form-select">
                                    <option value="">-- No Specific Supplier --</option>
                                    <?php foreach ($suppliers as $s): ?>
                                        <option value="<?= $s['supplier_id'] ?>">
                                            <?= htmlspecialchars($s['supplier_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="skipDuplicates"
                                        name="skip_duplicates" checked>
                                    <label class="form-check-label fw-bold" for="skipDuplicates">Skip duplicate
                                        products</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="updateExisting"
                                        name="update_existing">
                                    <label class="form-check-label fw-bold" for="updateExisting">Update existing if
                                        matched</label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-between align-items-center">
                            <div class="text-muted small">
                                <i class="fa-solid fa-lightbulb text-warning"></i> Need a format?
                                <a href="../../api/inventory/download_template.php"
                                    class="text-decoration-none">Download CSV Template</a>
                            </div>
                            <button type="submit" class="btn btn-primary px-4 py-2" id="previewBtn">
                                <i class="fa-solid fa-magnifying-glass"></i> Preview & Clean Data
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Step 2: Preview & Clean Table (Hidden initially) -->
        <div class="col-lg-12 d-none" id="previewStep">
            <div class="card shadow-sm border-0 mb-4">
                <div
                    class="card-header bg-success text-white fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-table-list"></i> Step 2: Preview & Clean Data</span>
                    <button type="button" class="btn btn-sm btn-light" id="backToUpload">
                        <i class="fa-solid fa-arrow-left"></i> Change File
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                        <div class="small">
                            <span class="badge bg-info text-white me-2" id="rowCountBadge">0 Rows Found</span>
                            <i class="fa-solid fa-info-circle text-muted"></i>
                            <span class="text-muted">Click any cell below to edit. Correct highlight rows before
                                processing.</span>
                        </div>
                        <button type="button" class="btn btn-success" id="confirmProcessBtn">
                            <i class="fa-solid fa-check-double"></i> Confirm & Start Process
                        </button>
                    </div>

                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-hover table-bordered mb-0 small" id="previewTable">
                            <thead class="table-dark sticky-top">
                                <!-- Headers injected via JS -->
                            </thead>
                            <tbody>
                                <!-- Rows injected via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Instructions (Hidden on Preview) -->
        <div class="col-lg-12" id="instructionCol">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">
                    <i class="fa-solid fa-book"></i> Instructions & Quick Reference
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h6 class="fw-bold text-primary"><i class="fa-solid fa-star"></i> Required</h6>
                            <ul class="mb-0 small">
                                <li><code>product_name</code></li>
                                <li><code>category_name</code> (ID or Name)</li>
                                <li><code>price_capital</code> or <code>price_retail</code></li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6 class="fw-bold text-primary"><i class="fa-solid fa-plus-circle"></i> Optional</h6>
                            <ul class="mb-0 small">
                                <li><code>brand_name</code>, <code>unit_type</code>, <code>variation_name</code></li>
                                <li><code>sku</code>, <code>barcode</code></li>
                                <li><code>initial_stock</code>, <code>low_stock_threshold</code></li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <h6 class="fw-bold text-primary"><i class="fa-solid fa-tags"></i> Categories</h6>
                            <div style="max-height: 100px; overflow-y: auto;" class="small border p-2 rounded">
                                <?php foreach ($cats as $c): ?>
                                    <code><?= htmlspecialchars($c['category_name']) ?></code>,
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const uploadStep = document.getElementById('uploadStep');
        const previewStep = document.getElementById('previewStep');
        const instructionCol = document.getElementById('instructionCol');
        const initialForm = document.getElementById('initialUploadForm');
        const previewTable = document.getElementById('previewTable');
        const rowCountBadge = document.getElementById('rowCountBadge');

        let parsedData = [];
        let currentHeaders = [];

        // 1. Initial Upload -> Preview
        initialForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const fileInput = document.getElementById('csvFile');
            if (!fileInput.files.length) return;

            const btn = document.getElementById('previewBtn');
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Parsing...';

            const formData = new FormData();
            formData.append('csv_file', fileInput.files[0]);

            fetch('../../api/inventory/parse_batch_upload.php', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderPreview(data);
                        uploadStep.classList.add('d-none');
                        instructionCol.classList.add('d-none');
                        previewStep.classList.remove('d-none');
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to parse file.');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                });
        });

        // 2. Render Preview Table
        function renderPreview(data) {
            parsedData = data.rows;
            currentHeaders = data.headers;
            rowCountBadge.innerText = `${data.rowCount} Rows Found`;

            const thead = previewTable.querySelector('thead');
            const tbody = previewTable.querySelector('tbody');

            thead.innerHTML = '';
            tbody.innerHTML = '';

            // Build Header
            const hRow = document.createElement('tr');
            currentHeaders.forEach(h => {
                const th = document.createElement('th');
                th.innerText = h.replace(/_/g, ' ').toUpperCase();
                hRow.appendChild(th);
            });
            thead.appendChild(hRow);

            // Build Rows
            parsedData.forEach((row, rowIndex) => {
                const tr = document.createElement('tr');
                if (row._warnings && row._warnings.length > 0) {
                    tr.classList.add('table-warning');
                    tr.title = row._warnings.join(', ');
                }

                currentHeaders.forEach(h => {
                    const td = document.createElement('td');
                    td.contentEditable = true;
                    td.innerText = row[h] || '';

                    // Track changes
                    td.addEventListener('blur', () => {
                        parsedData[rowIndex][h] = td.innerText.trim();
                    });

                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
        }

        // 3. Back to Upload
        document.getElementById('backToUpload').addEventListener('click', () => {
            previewStep.classList.add('d-none');
            uploadStep.classList.remove('d-none');
            instructionCol.classList.remove('d-none');
        });

        // 4. Final Confirmation
        document.getElementById('confirmProcessBtn').addEventListener('click', function () {
            const btn = this;
            const originalHtml = btn.innerHTML;

            if (!confirm('Start processing ' + parsedData.length + ' rows?')) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

            const finalForm = new FormData();
            finalForm.append('json_data', JSON.stringify(parsedData));
            finalForm.append('supplier_id', document.getElementById('supplier_id').value);
            if (document.getElementById('skipDuplicates').checked) finalForm.append('skip_duplicates', '1');
            if (document.getElementById('updateExisting').checked) finalForm.append('update_existing', '1');

            fetch('../../api/inventory/process_batch_upload.php', {
                method: 'POST',
                body: finalForm
            })
                .then(res => {
                    // Since process_batch_upload.php redirects, we need to handle that or let browser handle
                    if (res.redirected) {
                        window.location.href = res.url;
                    } else {
                        return res.text().then(text => {
                            // Fallback redirect or error show
                            window.location.href = '../../views/inventory/batch_upload.php?success=' + encodeURIComponent('Process triggered');
                        });
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Processing failed.');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                });
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>