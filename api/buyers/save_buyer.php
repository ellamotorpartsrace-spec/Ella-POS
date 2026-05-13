<?php
// api/buyers/save_buyer.php
declare(strict_types=1);
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;
requireLogin();

$id      = $_POST['buyer_id'] ?? '';
$name    = trim($_POST['buyer_name']);
$shop    = trim($_POST['shop_name'] ?? '');
$contact = trim($_POST['contact_number'] ?? '');
$email   = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$tier    = $_POST['price_tier'];
$walkin  = isset($_POST['is_walkin']) ? 1 : 0;
$limit   = isset($_POST['credit_limit']) && $_POST['credit_limit'] !== '' ? floatval($_POST['credit_limit']) : null;
$notes   = trim($_POST['credit_notes'] ?? '');

try {
    $db = new Database();
    $conn = $db->getConnection();

    if (empty($id)) {
        // INSERT
        $sql = "INSERT INTO buyers (buyer_name, shop_name, contact_number, email, address, price_tier, is_walkin, credit_limit, credit_notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $shop, $contact, $email, $address, $tier, $walkin, $limit, $notes]);
    } else {
        // UPDATE
        $sql = "UPDATE buyers SET buyer_name=?, shop_name=?, contact_number=?, email=?, address=?, price_tier=?, is_walkin=?, credit_limit=?, credit_notes=? 
                WHERE buyer_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $shop, $contact, $email, $address, $tier, $walkin, $limit, $notes, $id]);
    }

    header("Location: " . BASE_URL . "views/buyers/index.php?success=1");

} catch (Exception $e) {
    header("Location: " . BASE_URL . "views/buyers/index.php?error=" . urlencode($e->getMessage()));
}
?>