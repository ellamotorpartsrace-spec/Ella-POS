<?php
// api/users/activate_user.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requirePermission('manage_users');

if (isset($_GET['id'])) {
    $db = new Database();
    $conn = $db->getConnection();

    try {
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
        $stmt->execute([$_GET['id']]);

        header("Location: " . BASE_URL . "views/users/index.php?success=restored");
        exit;
    } catch (PDOException $e) {
        $error_msg = urlencode("Error restoring user: " . $e->getMessage());
        header("Location: " . BASE_URL . "views/users/index.php?error=" . $error_msg);
        exit;
    }
} else {
    header("Location: " . BASE_URL . "views/users/index.php?error=missing_id");
    exit;
}
?>
