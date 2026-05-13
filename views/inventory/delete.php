<?php
// views/inventory/delete.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    denyAccess("You do not have permission to delete inventory.");
}

$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: index.php?error=missing_id');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

try {
    $sql = "UPDATE product_variations SET status = 'inactive' WHERE variation_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    if (strpos($referer, '?') !== false) {
        header('Location: ' . $referer . '&msg=deleted');
    } else {
        header('Location: ' . $referer . '?msg=deleted');
    }
} catch (Exception $e) {
    // In a real app, log the error
    header('Location: index.php?error=db_error');
}
exit;
