<?php
/**
 * api/shopee/save_mappings.php — Save Shopee product mappings to DB
 */
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$mappings = $input['mappings'] ?? [];

if (empty($mappings)) {
    echo json_encode(['success' => false, 'error' => 'No mappings provided']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    $stmt = $conn->prepare("UPDATE shopee_product_mappings SET 
        matched_pos_sku = ?, 
        pos_product_id = ?, 
        mapping_status = ?, 
        updated_at = NOW() 
        WHERE id = ?");

    foreach ($mappings as $map) {
        $stmt->execute([
            $map['posSku'],
            $map['posId'],
            $map['status'],
            $map['id']
        ]);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => count($mappings) . ' mappings saved successfully']);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
