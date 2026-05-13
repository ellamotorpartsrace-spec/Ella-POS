<?php
// api/suppliers/mass_link_products.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');
requireLogin();

$data = json_decode(file_get_contents("php://input"), true);
$supplier_id = $data['supplier_id'] ?? null;
$to_link     = $data['to_link'] ?? [];   // Array of IDs to assign
$to_remove   = $data['to_remove'] ?? []; // Array of IDs to set to NULL

if (!$supplier_id) {
    echo json_encode(['success' => false, 'message' => 'Missing supplier ID.']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    // 1. PROCESS LINKING
    if (!empty($to_link)) {
        $linkPlaceholders = implode(',', array_fill(0, count($to_link), '?'));
        $stmtLink = $conn->prepare("UPDATE products SET supplier_id = ? WHERE product_id IN ($linkPlaceholders)");
        $stmtLink->execute(array_merge([$supplier_id], $to_link));
    }

    // 2. PROCESS REMOVING (Unlink)
    if (!empty($to_remove)) {
        $removePlaceholders = implode(',', array_fill(0, count($to_remove), '?'));
        $stmtRemove = $conn->prepare("UPDATE products SET supplier_id = NULL WHERE product_id IN ($removePlaceholders)");
        $stmtRemove->execute($to_remove);
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if(isset($conn)) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}