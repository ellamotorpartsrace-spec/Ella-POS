<?php
// views/inventory/restore.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager'])) {
    denyAccess("You do not have permission to restore inventory.");
}

$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: archived.php?error=missing_id');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

try {
    $sql = "UPDATE product_variations SET status = 'active' WHERE variation_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);

    header('Location: archived.php?msg=restored');
} catch (Exception $e) {
    // In a real app, log the error
    header('Location: archived.php?error=db_error');
}
exit;
