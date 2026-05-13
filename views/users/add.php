<?php
// views/users/add.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Security: Only Admins can add users
requireLogin();
requirePermission('manage_users');

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../config/database.php';

// Fetch dynamic roles
$db = new Database();
$conn = $db->getConnection();
$rolesStmt = $conn->query("SELECT role_slug, role_name FROM roles ORDER BY role_name ASC");
$dynamicRoles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold"><i class="fa-solid fa-user-plus text-primary"></i> Register New Staff</h4>
            <div class="text-muted small">Create a new account for your cashiers or managers.</div>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to List
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">

                    <form action="../../api/users/save_user.php" method="POST" id="addUserForm">
                        <input type="hidden" name="id" value="">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-id-card"></i></span>
                                <input type="text" name="full_name" class="form-control"
                                    placeholder="e.g. Juan Dela Cruz" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-at"></i></span>
                                <input type="text" name="username" class="form-control" placeholder="e.g. juan_01"
                                    required>
                            </div>
                            <div class="form-text small">This will be used for logging in.</div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Role / Position</label>
                                <select name="role" class="form-select" required>
                                    <option value="admin">Administrator (Full Access)</option>
                                    <?php foreach ($dynamicRoles as $r): ?>
                                        <option value="<?= htmlspecialchars($r['role_slug']) ?>">
                                            <?= htmlspecialchars($r['role_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Initial Status</label>
                                <select name="status" class="form-select">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive (Locked)</option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-4 text-muted opacity-25">

                        <div class="mb-4">
                            <label class="form-label fw-bold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-key"></i></span>
                                <input type="password" name="password" id="password" class="form-control"
                                    placeholder="Minimum 6 characters" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePass()">
                                    <i class="fa-solid fa-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold shadow-sm">
                                <i class="fa-solid fa-user-check"></i> Create Account
                            </button>
                            <button type="reset" class="btn btn-light text-muted">Clear Form</button>
                        </div>
                    </form>

                </div>
            </div>

            <div class="alert alert-info mt-4 border-0 shadow-sm small">
                <i class="fa-solid fa-circle-info me-2"></i>
                <strong>Note:</strong> New users can change their passwords once they log in through their profile
                settings.
            </div>
        </div>
    </div>
</div>

<script>
    function togglePass() {
        const passInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        if (passInput.type === 'password') {
            passInput.type = 'text';
            eyeIcon.className = 'fa-solid fa-eye-slash';
        } else {
            passInput.type = 'password';
            eyeIcon.className = 'fa-solid fa-eye';
        }
    }
</script>

<?php require_once '../../includes/footer.php'; ?>