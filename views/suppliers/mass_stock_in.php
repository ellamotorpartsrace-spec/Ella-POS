<?php
// views/suppliers/mass_stock_in.php
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

$supplier_id = $_GET['supplier_id'];
$db = new Database();
$conn = $db->getConnection();

// Get Supplier Info
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
$stmt->execute([$supplier_id]);
$vendor = $stmt->fetch();
?>

<div class="container-fluid p-4">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white p-3">
            <h5 class="mb-0 fw-bold"><i class="fa-solid fa-cart-plus me-2"></i> Mass Stock-In:
                <?= $vendor['supplier_name'] ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Find Product to Add</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" id="prod-search" class="form-control" placeholder="Search by name or SKU...">
                    </div>
                    <div id="search-results" class="list-group position-absolute shadow-sm"
                        style="z-index: 1000; width: 45%;"></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">PO Reference #</label>
                    <input type="text" id="po_ref" class="form-control" value="PO-<?= time() ?>">
                </div>
            </div>

            <form id="mass-stock-form">
                <input type="hidden" name="supplier_id" value="<?= $supplier_id ?>">
                <table class="table table-bordered align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th>Product Detail</th>
                            <th width="150">Cost Price</th>
                            <th width="120">Quantity</th>
                            <th width="150">Subtotal</th>
                            <th width="50"></th>
                        </tr>
                    </thead>
                    <tbody id="stock-items-body">
                    </tbody>
                </table>

                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-success btn-lg px-5 fw-bold" onclick="submitStockIn()">
                        <i class="fa-solid fa-file-import me-2"></i> Process Mass Stock-In
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Search and add logic (Simplified for brainstorming)
    document.getElementById('prod-search').addEventListener('input', function (e) {
        let q = e.target.value;
        if (q.length < 2) return;
        // AJAX call to search_variations.php then append to #stock-items-body
    });

    function addItem(variation) {
        const html = `
        <tr>
            <td>
                <strong>${variation.product_name}</strong><br>
                <small class="text-muted">${variation.variation_name}</small>
                <input type="hidden" name="items[${variation.id}][variation_id]" value="${variation.id}">
            </td>
            <td><input type="number" name="items[${variation.id}][cost]" class="form-control" value="${variation.price_capital}"></td>
            <td><input type="number" name="items[${variation.id}][qty]" class="form-control" value="1"></td>
            <td class="fw-bold">₱ 0.00</td>
            <td><button class="btn btn-sm btn-link text-danger"><i class="fa-solid fa-trash"></i></button></td>
        </tr>
    `;
        document.getElementById('stock-items-body').insertAdjacentHTML('beforeend', html);
    }

    function submitStockIn() {
        const items = [];
        document.querySelectorAll('.stock-item-row').forEach(row => {
            items.push({
                variation_id: row.dataset.id,
                qty: row.querySelector('.qty-input').value,
                cost: row.querySelector('.cost-input').value
            });
        });

        const payload = {
            supplier_id: document.getElementById('supplier_id').value,
            po_ref: document.getElementById('po_ref').value,
            items: items
        };

        fetch('../../api/pos/process_mass_stock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    EllaToast.success("Stock-in Complete!");
                    window.location.href = "index.php";
                }
            });
    }
</script>