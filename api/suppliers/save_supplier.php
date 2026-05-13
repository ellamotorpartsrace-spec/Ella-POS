<?php
// api/suppliers/save_supplier.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../views/suppliers/index.php");
    exit;
}

$id      = $_POST['supplier_id'] ?? null;
$name    = trim($_POST['supplier_name']);
$contact = trim($_POST['contact_person'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$email   = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($id) {
        // ACTION: UPDATE EXISTING
        $sql = "UPDATE suppliers SET supplier_name=?, contact_person=?, phone=?, email=?, address=? WHERE supplier_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $contact, $phone, $email, $address, $id]);
        $message = "updated";
    } else {
        // ACTION: INSERT NEW
        $sql = "INSERT INTO suppliers (supplier_name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$name, $contact, $phone, $email, $address]);
        $message = "added";
    }

    header("Location: ../../views/suppliers/index.php?success=" . $message);

} catch (Exception $e) {
    header("Location: ../../views/suppliers/index.php?error=" . urlencode($e->getMessage()));
}