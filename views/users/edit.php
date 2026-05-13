<?php
// views/users/edit.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Security: Only Admins can edit users
requireLogin();
requirePermission('manage_users');

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

// 2. Fetch User Data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// 3. Fetch Dynamic Roles
$rolesStmt = $conn->query("SELECT role_slug, role_name FROM roles ORDER BY role_name ASC");
$dynamicRoles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$user) {
    echo "<div class='alert alert-danger m-4'>User not found.</div>";
    require_once '../../includes/footer.php';
    exit;
}
?>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 fw-bold">
            <i class="fa-solid fa-user-pen text-primary"></i> Edit User: <?= htmlspecialchars($user['username']) ?>
        </h4>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to List
        </a>
    </div>

    <div class="card shadow-sm border-0" style="max-width: 800px;">
        <div class="card-body p-4">

            <form action="../../api/users/save_user.php" method="POST">
                <input type="hidden" name="id" value="<?= $user['id'] ?>">

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Full Name</label>
                        <input type="text" name="full_name" class="form-control"
                            value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Username</label>
                        <input type="text" name="username" class="form-control"
                            value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>

                    <div class="col-12">
                        <hr class="text-muted opacity-25">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Role / Position</label>
                        <select name="role" class="form-select">
                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrator (Full
                                Access)</option>
                            <?php foreach ($dynamicRoles as $r): ?>
                                <option value="<?= htmlspecialchars($r['role_slug']) ?>" <?= $user['role'] === $r['role_slug'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r['role_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">Admins can manage users and settings.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold text-danger">Reset Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="password" class="form-control"
                                placeholder="Leave blank to keep current">
                        </div>
                        <div class="form-text small">Only type here if you want to change the password.</div>
                    </div>

                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="index.php" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="fa-solid fa-save"></i> Save Changes
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>