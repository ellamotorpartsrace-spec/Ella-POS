<?php
// views/suppliers/edit.php
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

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
$stmt->execute([$id]);
$s = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$s) {
    echo "<div class='p-4'><h4>Supplier not found.</h4></div>";
    exit;
}
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold">✏️ Edit Supplier</h4>
            <div class="text-muted"><?= htmlspecialchars($s['supplier_name']) ?></div>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left"></i> Cancel
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0">
                <form action="../../api/suppliers/save_supplier.php" method="POST" class="card-body p-4">
                    <input type="hidden" name="supplier_id" value="<?= $s['supplier_id'] ?>">

                    <div class="mb-4">
                        <label class="form-label fw-bold">Company / Supplier Name</label>
                        <input type="text" name="supplier_name" class="form-control form-control-lg"
                            value="<?= htmlspecialchars($s['supplier_name']) ?>" required>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control"
                                value="<?= htmlspecialchars($s['contact_person']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Phone Number</label>
                            <input type="text" name="phone" class="form-control"
                                value="<?= htmlspecialchars($s['phone']) ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Email Address</label>
                        <input type="email" name="email" class="form-control"
                            value="<?= htmlspecialchars($s['email']) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Office Address</label>
                        <textarea name="address" class="form-control"
                            rows="3"><?= htmlspecialchars($s['address']) ?></textarea>
                    </div>

                    <hr class="text-muted opacity-25">

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm fw-bold">
                            <i class="fa-solid fa-save me-1"></i> Update Supplier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>