<?php
// api/users/delete_user.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requirePermission('manage_users');

if (isset($_GET['id'])) {
    $db = new Database();
    $conn = $db->getConnection();

    try {
        // Start database transaction
        $conn->beginTransaction();

        // Instead of hard delete, we set status to inactive
        // This prevents foreign key errors and preserves sales history
        $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$_GET['id']]);

        // Clean up active sessions so they are logged out immediately
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$_GET['id']]);

        $conn->commit();
        header("Location: " . BASE_URL . "views/users/index.php?success=archived");
        exit;
    } catch (PDOException $e) {
        $conn->rollBack();
        $error_msg = urlencode("Error archiving user: " . $e->getMessage());
        header("Location: " . BASE_URL . "views/users/index.php?error=" . $error_msg);
        exit;
    }
} else {
    header("Location: " . BASE_URL . "views/users/index.php?error=missing_id");
    exit;
}
?>