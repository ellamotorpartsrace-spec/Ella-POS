<?php
// views/suppliers/mass_link.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('manage_settings') && !in_array($_SESSION['role'], ['manager', 'stockman', 'cashier'])) {
    denyAccess("You do not have permission to manage suppliers.");
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

// 1. Get ID & Validate
$supplier_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$db = new Database();
$conn = $db->getConnection();

// 2. Fetch Supplier Info with Safety Check
$stmtS = $conn->prepare("SELECT supplier_name FROM suppliers WHERE supplier_id = ?");
$stmtS->execute([$supplier_id]);
$supplier = $stmtS->fetch(PDO::FETCH_ASSOC); // Ensure FETCH_ASSOC is used

// 3. If no supplier found, stop and show error
if (!$supplier) {
    echo "<div class='container-fluid p-4'>
            <div class='alert alert-danger'>
                <h4 class='fw-bold'><i class='fa-solid fa-circle-exclamation'></i> Supplier Not Found</h4>
                <p>The supplier ID #$supplier_id does not exist in the database.</p>
                <a href='index.php' class='btn btn-danger'>Return to Suppliers</a>
            </div>
          </div>";
    require_once '../../includes/footer.php';
    exit;
}

// 4. Proceed to fetch products only if supplier exists
$products = $conn->query("
    SELECT p.product_id, p.product_name, p.brand_name, c.category_name, p.supplier_id 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.category_id 
    ORDER BY p.product_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold"><i class="fa-solid fa-link-slash text-danger me-2"></i>Link / Unlink Products</h4>
            <p class="text-muted">Managing items for: <strong
                    class="text-dark"><?= htmlspecialchars($supplier['supplier_name']) ?></strong></p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm px-3">Back to Suppliers</a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-0">
            <div class="input-group input-group-lg shadow-sm">
                <span class="input-group-text bg-white border-end-0"><i
                        class="fa-solid fa-magnifying-glass text-muted"></i></span>
                <input type="text" id="productSearch" class="form-control border-start-0 ps-0"
                    placeholder="Type to filter products...">
            </div>
        </div>

        <div class="table-responsive" style="max-height: 550px;">
            <table class="table table-hover align-middle mb-0" id="productTable">
                <thead class="bg-light sticky-top">
                    <tr>
                        <th class="ps-4" width="60">Status</th>
                        <th>Product & Brand</th>
                        <th>Category</th>
                        <th class="text-end pe-4">Current Supplier</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p):
                        $is_linked = ($p['supplier_id'] == $supplier_id);
                        ?>
                        <tr class="product-row <?= $is_linked ? 'table-primary-subtle' : '' ?>">
                            <td class="ps-4">
                                <input type="checkbox" class="form-check-input prod-check"
                                    data-product-id="<?= $p['product_id'] ?>"
                                    data-original-state="<?= $is_linked ? '1' : '0' ?>" <?= $is_linked ? 'checked' : '' ?>>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($p['product_name']) ?></div>
                                <div class="small text-muted font-monospace">
                                    <?= htmlspecialchars($p['brand_name'] ?: 'GENERIC') ?>
                                </div>
                            </td>
                            <td><span
                                    class="badge bg-white text-dark border"><?= htmlspecialchars($p['category_name'] ?: 'General') ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <?php if ($p['supplier_id'] && !$is_linked): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning">Linked
                                        Elsewhere</span>
                                <?php elseif ($is_linked): ?>
                                    <span class="badge bg-success text-white">Currently Linked</span>
                                <?php else: ?>
                                    <span class="text-muted small">Unassigned</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center border-0 shadow-lg">
            <div class="d-flex gap-3">
                <div class="small fw-bold text-success"><i class="fa-solid fa-plus-circle"></i> To Link: <span
                        id="addCount">0</span></div>
                <div class="small fw-bold text-danger"><i class="fa-solid fa-minus-circle"></i> To Remove: <span
                        id="removeCount">0</span></div>
            </div>
            <button class="btn btn-primary btn-lg px-5 fw-bold shadow" onclick="saveChanges()">
                Save Link Changes
            </button>
        </div>
    </div>
</div>

<script>
    // Search filter
    document.getElementById('productSearch').addEventListener('input', function () {
        let q = this.value.toLowerCase();
        document.querySelectorAll('.product-row').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    });

    // Calculate changes
    function calculateChanges() {
        let toLink = 0;
        let toRemove = 0;

        document.querySelectorAll('.prod-check').forEach(cb => {
            let current = cb.checked ? 1 : 0;
            let original = parseInt(cb.dataset.originalState);

            if (current === 1 && original === 0) toLink++;
            if (current === 0 && original === 1) toRemove++;

            // Highlight changed rows
            if (current !== original) cb.closest('tr').classList.add('bg-warning-subtle');
            else cb.closest('tr').classList.remove('bg-warning-subtle');
        });

        document.getElementById('addCount').innerText = toLink;
        document.getElementById('removeCount').innerText = toRemove;
    }

    document.querySelectorAll('.prod-check').forEach(cb => cb.addEventListener('change', calculateChanges));

    function saveChanges() {
        const to_link = [];
        const to_remove = [];

        document.querySelectorAll('.prod-check').forEach(cb => {
            let current = cb.checked ? 1 : 0;
            let original = parseInt(cb.dataset.originalState);
            let id = cb.dataset.productId;

            if (current === 1 && original === 0) to_link.push(id);
            if (current === 0 && original === 1) to_remove.push(id);
        });

        if (to_link.length === 0 && to_remove.length === 0) {
            EllaToast.warning("No changes detected.");
            return;
        }

        if (!confirm(`Apply changes?\nLink: ${to_link.length} items\nRemove: ${to_remove.length} items`)) return;

        fetch('../../api/suppliers/mass_link_products.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                supplier_id: <?= $supplier_id ?>,
                to_link: to_link,
                to_remove: to_remove
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    EllaToast.success("Supplier links updated!");
                    window.location.reload();
                } else {
                    EllaToast.error("Error: " + data.message);
                }
            });
    }
</script>