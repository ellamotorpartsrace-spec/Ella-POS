<?php
// views/inventory/reference.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    denyAccess("You do not have permission to view stock movements.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

$ref = $_GET['ref'] ?? null;
$from = $_GET['from'] ?? 'movements';

// Determine back URL based on origin page
$back_urls = [
    'stockin_records' => ['url' => 'stockin_records.php', 'label' => 'Back to Stock-In Records'],
    'movements' => ['url' => 'movements.php', 'label' => 'Back to Stock Movements'],
];
$back_info = $back_urls[$from] ?? $back_urls['movements'];

if (!$ref) {
    echo "<div class='container p-4'><h3>❌ No reference provided</h3><a href='" . $back_info['url'] . "'>Back</a></div>";
    require_once '../../includes/footer.php';
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// 1. Fetch Reference Image
$stmtImg = $conn->prepare("SELECT * FROM reference_attachments WHERE reference_number = ?");
$stmtImg->execute([$ref]);
$attachments = $stmtImg->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch Items (Stock Movements)
$sqlItems = "
    SELECT sm.*, 
           p.product_name, p.brand_name, 
           v.variation_name, v.sku, v.unit_type, COALESCE(sm.capital_cost, v.price_capital) as price_capital,
           u.full_name as created_by_name
    FROM stock_movements sm
    JOIN product_variations v ON sm.variation_id = v.variation_id
    JOIN products p ON v.product_id = p.product_id
    LEFT JOIN users u ON sm.created_by = u.id
    WHERE sm.reference = ?
    ORDER BY sm.created_at DESC
";
$stmtItems = $conn->prepare($sqlItems);
$stmtItems->execute([$ref]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-file-invoice text-primary me-2"></i>Reference Details</h4>
            <div class="text-muted">
                Reference Code: <span class="fw-bold text-dark selectable"><?= htmlspecialchars($ref) ?></span>
            </div>
        </div>
        <a href="<?= htmlspecialchars($back_info['url']) ?>" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> <?= htmlspecialchars($back_info['label']) ?>
        </a>
    </div>

    <?php
    $totalItemsCount = 0;
    $totalItemsQty = 0;
    $totalTransactionCost = 0;
    foreach ($items as $row) {
        $totalItemsCount++;
        $totalItemsQty += abs($row['quantity']);
        if (in_array($row['type'], ['stock_in', 'return'])) {
            $totalTransactionCost += abs($row['quantity']) * (float) $row['price_capital'];
        }
    }
    ?>

    <?php if (count($items) > 0 && $totalTransactionCost > 0): ?>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 bg-primary bg-opacity-10">
                    <div class="card-body p-3">
                        <div class="text-primary small fw-bold text-uppercase mb-1">Total Items</div>
                        <div class="h4 fw-bold mb-0 text-primary"><?= number_format($totalItemsCount) ?> <small
                                class="fw-normal fs-6">unique products</small></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 bg-success bg-opacity-10">
                    <div class="card-body p-3">
                        <div class="text-success small fw-bold text-uppercase mb-1">Total Quantity Count</div>
                        <div class="h4 fw-bold mb-0 text-success">+<?= number_format($totalItemsQty) ?> <small
                                class="fw-normal fs-6">qty added</small></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 bg-warning bg-opacity-10">
                    <div class="card-body p-3">
                        <div class="text-warning small fw-bold text-uppercase mb-1">Reference Total Cost</div>
                        <div class="h4 fw-bold mb-0 text-warning">₱<?= number_format($totalTransactionCost, 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left Column: Items List -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="fw-bold mb-0">Items in this Reference</h6>
                        <small class="text-muted"><?= count($items) ?> item(s) found</small>
                    </div>
                    <?php if (count($items) > 0): ?>
                        <div class="text-end">
                            <small class="text-muted d-block">Created By</small>
                            <span
                                class="fw-bold small"><?= htmlspecialchars($items[0]['created_by_name'] ?? 'System') ?></span>
                            <div class="small text-muted"><?= date('M d, Y h:i A', strtotime($items[0]['created_at'])) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Product Detail</th>
                                    <th class="text-end">Capital</th>
                                    <th class="text-center">Qty Change</th>
                                    <th class="text-end">Total</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($items) > 0): ?>
                                    <?php foreach ($items as $row):
                                        $qty_sign = in_array($row['type'], ['stock_in', 'return']) ? '+' : '-';
                                        $qty_color = in_array($row['type'], ['stock_in', 'return']) ? 'success' : 'danger';
                                        ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['product_name']) ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($row['brand_name']) ?> |
                                                    <?= htmlspecialchars($row['variation_name']) ?>
                                                    <?php if ($row['sku']): ?> (<?= htmlspecialchars($row['sku']) ?>)
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td class="text-end">
                                                <div class="fw-medium text-secondary">
                                                    ₱<?= number_format((float) $row['price_capital'], 2) ?></div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $qty_color ?>-subtle text-<?= $qty_color ?> fs-6">
                                                    <?= $qty_sign ?>         <?= abs($row['quantity']) ?>
                                                </span>
                                                <div class="small text-muted mt-1">
                                                    Stock: <?= $row['previous_stock'] ?> <i class="fa-solid fa-arrow-right mx-1"
                                                        style="font-size: 0.7em;"></i> <?= $row['new_stock'] ?>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <div class="fw-bold text-dark">
                                                    ₱<?= number_format(abs($row['quantity']) * (float) $row['price_capital'], 2) ?>
                                                </div>
                                            </td>
                                            <td class="text-muted small">
                                                <?= htmlspecialchars($row['remarks']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">No items found linked to this
                                            reference.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <?php if (count($items) > 0 && $totalTransactionCost > 0): ?>
                                <tfoot class="bg-light bg-opacity-50 border-top-0">
                                    <tr>
                                        <td colspan="2" class="text-end fw-bold text-dark py-3">Totals:</td>
                                        <td class="text-center py-3">
                                            <div class="badge bg-success text-white fs-6 shadow-sm">+
                                                <?= number_format($totalItemsQty) ?>
                                            </div>
                                        </td>
                                        <td class="text-end py-3">
                                            <div class="fw-bold text-dark fs-5">₱
                                                <?= number_format($totalTransactionCost, 2) ?>
                                            </div>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Image/Attachment -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-paperclip me-2"></i>Attachment</h6>
                    <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                        <button type="button" class="btn btn-sm btn-success"
                            onclick="document.getElementById('retroactive-upload').click()">
                            <i class="fa-solid fa-plus me-1"></i> Add Photo
                        </button>
                        <input type="file" id="retroactive-upload" class="d-none" accept="image/*" multiple
                            onchange="uploadAttachment(this, '<?= htmlspecialchars($ref) ?>')">
                    <?php endif; ?>
                </div>
                <div class="card-body bg-light d-flex align-items-center justify-content-center p-0"
                    style="min-height: 400px; position: relative;">
                    <?php if (count($attachments) > 0): ?>
                        <div id="attachmentCarousel" class="carousel slide w-100 h-100" data-bs-ride="carousel">
                            <?php if (count($attachments) > 1): ?>
                                <div class="carousel-indicators">
                                    <?php foreach ($attachments as $index => $att): ?>
                                        <button type="button" data-bs-target="#attachmentCarousel" data-bs-slide-to="<?= $index ?>"
                                            class="<?= $index === 0 ? 'active' : '' ?>"
                                            aria-current="<?= $index === 0 ? 'true' : 'false' ?>"
                                            aria-label="Slide <?= $index + 1 ?>"></button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="carousel-inner h-100">
                                <?php foreach ($attachments as $index => $att): ?>
                                    <div class="carousel-item h-100 <?= $index === 0 ? 'active' : '' ?>">
                                        <div class="d-flex flex-column align-items-center justify-content-center h-100 p-2">
                                            <img src="<?= BASE_URL . $att['image_path'] ?>"
                                                class="d-block img-fluid rounded shadow-sm"
                                                style="max-height: 550px; width: auto;" alt="Attachment <?= $index + 1 ?>">
                                            <div class="mt-3 d-flex gap-2" style="position: relative; z-index: 10;">
                                                <a href="<?= BASE_URL . $att['image_path'] ?>" target="_blank"
                                                    class="btn btn-sm btn-primary">
                                                    <i class="fa-solid fa-expand me-1"></i> View Full Size
                                                </a>
                                                <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                        onclick="deleteAttachment(<?= $att['id'] ?>, '<?= $att['image_path'] ?>', this)">
                                                        <i class="fa-solid fa-trash me-1"></i> Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if (count($attachments) > 1): ?>
                                <button class="carousel-control-prev" type="button" data-bs-target="#attachmentCarousel"
                                    data-bs-slide="prev" style="width: 10%;">
                                    <span class="carousel-control-prev-icon bg-dark rounded-circle p-2"
                                        aria-hidden="true"></span>
                                    <span class="visually-hidden">Previous</span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#attachmentCarousel"
                                    data-bs-slide="next" style="width: 10%;">
                                    <span class="carousel-control-next-icon bg-dark rounded-circle p-2"
                                        aria-hidden="true"></span>
                                    <span class="visually-hidden">Next</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted opacity-50">
                            <i class="fa-regular fa-image fa-4x mb-3"></i>
                            <p>No image attached to this reference.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function deleteAttachment(id, path, btn) {
        if (!confirm('Are you sure you want to delete this receipt photo? This action cannot be undone.')) return;

        // Disable button to prevent double clicks
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Deleting...';
        }

        const formData = new FormData();
        if (id) formData.append('id', id);
        formData.append('image_path', path);

        fetch('../../api/inventory/delete_attachment.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (typeof EllaToast !== 'undefined') EllaToast.success(data.message);
                    else alert(data.message);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    if (typeof EllaToast !== 'undefined') EllaToast.error('Error: ' + data.error);
                    else alert('Error: ' + data.error);
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-trash me-1"></i> Delete';
                    }
                }
            })
            .catch(err => {
                console.error(err);
                if (typeof EllaToast !== 'undefined') EllaToast.error('An error occurred during deletion.');
                else alert('An error occurred during deletion.');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-trash me-1"></i> Delete';
                }
            });
    }

    function uploadAttachment(input, ref) {
        if (!input.files || input.files.length === 0) return;

        const formData = new FormData();
        formData.append('reference_number', ref);
        for (let i = 0; i < input.files.length; i++) {
            formData.append('reference_images[]', input.files[i]);
        }

        // Show loading state
        if (typeof EllaToast !== 'undefined') EllaToast.info('Uploading attachment(s)...');

        fetch('../../api/inventory/upload_retroactive_attachment.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (typeof EllaToast !== 'undefined') EllaToast.success(data.message || 'Attachments uploaded successfully!');
                    else alert('Attachments uploaded successfully!');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    if (typeof EllaToast !== 'undefined') EllaToast.error('Error: ' + data.error);
                    else alert('Error: ' + data.error);
                    input.value = ''; // Reset input so it can be triggered again
                }
            })
            .catch(err => {
                console.error(err);
                if (typeof EllaToast !== 'undefined') EllaToast.error('An error occurred during upload.');
                else alert('An error occurred during upload.');
                input.value = '';
            });
    }
</script>

<?php require_once '../../includes/footer.php'; ?>