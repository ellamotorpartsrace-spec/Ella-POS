<?php
// views/suppliers/view_supplier.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('manage_settings') && !in_array($_SESSION['role'], ['manager', 'stockman', 'cashier'])) {
    denyAccess("You do not have permission to view suppliers.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

$id = $_GET['id'];
$db = new Database();
$conn = $db->getConnection();

// Get Supplier
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
$stmt->execute([$id]);
$s = $stmt->fetch(PDO::FETCH_ASSOC);

// Get Linked Products
$stmtItems = $conn->prepare("
    SELECT p.product_name, v.variation_name, v.sku, v.price_capital, v.price_retail 
    FROM products p
    JOIN product_variations v ON p.product_id = v.product_id
    WHERE p.supplier_id = ?
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid p-4">
    <div class="mb-4">
        <a href="index.php" class="text-decoration-none small"><i class="fa-solid fa-arrow-left"></i> Back to
            Suppliers</a>
        <h3 class="fw-bold mt-2"><?= htmlspecialchars($s['supplier_name']) ?></h3>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold">Contact Information</div>
                <div class="card-body">
                    <p class="mb-1 text-muted small">Contact Person</p>
                    <p class="fw-bold"><?= $s['contact_person'] ?: 'N/A' ?></p>
                    <p class="mb-1 text-muted small">Phone</p>
                    <p class="fw-bold"><?= $s['phone'] ?: 'N/A' ?></p>
                    <p class="mb-1 text-muted small">Address</p>
                    <p class="fw-bold"><?= $s['address'] ?: 'N/A' ?></p>
                </div>
            </div>

            <a href="mass_stock_in.php?supplier_id=<?= $id ?>" class="btn btn-success btn-lg w-100 shadow-sm">
                <i class="fa-solid fa-boxes-packing me-2"></i> Mass Stock-In
            </a>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h6 class="mb-0 fw-bold">Supplied Products</h6>
                    <span class="badge bg-primary"><?= count($items) ?> Items</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light small">
                            <tr>
                                <th class="ps-3">Product / SKU</th>
                                <th>Variation</th>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                    <th>Cost</th>
                                <?php endif; ?>
                                <th class="text-end pe-3">SRP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($items) > 0):
                                foreach ($items as $i): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold"><?= $i['product_name'] ?></div>
                                            <small class="text-muted font-monospace"><?= $i['sku'] ?></small>
                                        </td>
                                        <td><?= $i['variation_name'] ?></td>
                                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                            <td class="text-primary fw-bold">₱<?= number_format($i['price_capital'], 2) ?></td>
                                        <?php endif; ?>
                                        <td class="text-end pe-3 fw-bold">₱<?= number_format($i['price_retail'], 2) ?></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">No products linked to this supplier
                                        yet.</td>
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