<?php
// views/inventory/history.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    denyAccess("You do not have permission to view inventory history.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

// 1. Get ID & Validate
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}
$variation_id = $_GET['id'];

$db = new Database();
$conn = $db->getConnection();

// 2. Fetch Product Details (Header Info)
$sqlProd = "
    SELECT 
        p.product_name, p.brand_name, 
        v.variation_name, v.sku, v.barcode,
        COALESCE(i.quantity, 0) as current_stock
    FROM product_variations v
    JOIN products p ON v.product_id = p.product_id
    LEFT JOIN inventory i ON v.variation_id = i.variation_id
    WHERE v.variation_id = :id
";
$stmt = $conn->prepare($sqlProd);
$stmt->execute([':id' => $variation_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "<div class='p-4'><h3>❌ Product not found</h3><a href='index.php'>Back</a></div>";
    require_once '../../includes/footer.php';
    exit;
}

// 3. Fetch History (Stock Movements)
// We JOIN with 'users' to see WHO did the action
$sqlHist = "
    SELECT m.*, u.username, u.full_name
    FROM stock_movements m
    LEFT JOIN users u ON m.created_by = u.id
    WHERE m.variation_id = :id
    ORDER BY m.created_at DESC
    LIMIT 100
";
$stmtHist = $conn->prepare($sqlHist);
$stmtHist->execute([':id' => $variation_id]);
$history = $stmtHist->fetchAll();
?>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold"><i class="fa-solid fa-clock-rotate-left text-primary"></i> Stock History</h4>
            <div class="text-muted">
                <?= htmlspecialchars($product['product_name']) ?>
                <span class="badge bg-secondary ms-1"><?= htmlspecialchars($product['variation_name']) ?></span>
            </div>
        </div>
        <a href="<?= htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'index.php') ?>" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left"></i> Cancel / Back
        </a>
    </div>

    <div class="row g-4">

        <div class="col-md-3">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <small class="text-uppercase text-muted fw-bold">Current Stock</small>
                    <div class="h2 fw-bold text-primary mb-0"><?= $product['current_stock'] ?></div>
                    <small class="text-muted">units available</small>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="fw-bold border-bottom pb-2">Item Details</h6>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>SKU</span>
                            <span class="font-monospace"><?= htmlspecialchars($product['sku'] ?? '-') ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>Barcode</span>
                            <span class="font-monospace"><?= htmlspecialchars($product['barcode'] ?? '-') ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>Brand</span>
                            <span class="fw-bold"><?= htmlspecialchars($product['brand_name']) ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold">Recent Movements (Last 100)</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Date & Time</th>
                                <th>Type</th>
                                <th class="text-center">Change</th>
                                <th class="text-center">Balance</th>
                                <th>Reference / Remarks</th>
                                <th class="text-end pe-4">User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($history) > 0): ?>
                                <?php foreach ($history as $row): ?>
                                    <tr>
                                        <td class="ps-4 small text-secondary">
                                            <?= date('M d, Y h:i A', strtotime($row['created_at'])) ?>
                                        </td>

                                        <td>
                                            <?php
                                            $type = $row['type'];
                                            $badgeClass = 'bg-secondary';
                                            $icon = 'fa-circle';

                                            switch ($type) {
                                                case 'stock_in':
                                                    $badgeClass = 'bg-success';
                                                    $icon = 'fa-arrow-down';
                                                    break;
                                                case 'stock_out':
                                                    $badgeClass = 'bg-warning text-dark';
                                                    $icon = 'fa-arrow-up';
                                                    break;
                                                case 'sales':
                                                    $badgeClass = 'bg-primary';
                                                    $icon = 'fa-cart-shopping';
                                                    break;
                                                case 'adjustment':
                                                    $badgeClass = 'bg-info text-dark';
                                                    $icon = 'fa-wrench';
                                                    break;
                                                case 'return':
                                                    $badgeClass = 'bg-danger';
                                                    $icon = 'fa-rotate-left';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?= $badgeClass ?> rounded-pill">
                                                <i class="fa-solid <?= $icon ?> me-1"></i>
                                                <?= strtoupper(str_replace('_', ' ', $type)) ?>
                                            </span>
                                        </td>

                                        <td class="text-center fw-bold">
                                            <?php if (in_array($type, ['stock_out', 'sales'])): ?>
                                                <span class="text-danger">-<?= $row['quantity'] ?></span>
                                            <?php else: ?>
                                                <span class="text-success">+<?= $row['quantity'] ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-center text-muted">
                                            <?= $row['new_stock'] ?>
                                        </td>

                                        <td>
                                            <?php if ($row['reference']): ?>
                                                <div class="badge bg-light text-dark border border-secondary mb-1">
                                                    Ref: <?= htmlspecialchars($row['reference']) ?>
                                                </div><br>
                                            <?php endif; ?>
                                            <small class="text-muted"><?= htmlspecialchars($row['remarks']) ?></small>
                                        </td>

                                        <td class="text-end pe-4 small">
                                            <i class="fa-solid fa-user-circle text-secondary"></i>
                                            <?= htmlspecialchars($row['username']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        No history found for this item yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>