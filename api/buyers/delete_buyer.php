<?php
// api/buyers/delete_buyer.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

if (isset($_GET['id'])) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("DELETE FROM buyers WHERE buyer_id = ?");
    $stmt->execute([$_GET['id']]);
}

header("Location: " . BASE_URL . "views/buyers/index.php?success=deleted");
?>