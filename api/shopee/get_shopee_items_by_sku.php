<?php
/**
 * api/shopee/get_shopee_items_by_sku.php — Fetch Shopee items sharing a specific SKU
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

$sku = $_GET['sku'] ?? '';
if (empty($sku)) {
    echo json_encode(['success' => false, 'error' => 'Missing SKU']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT id, shopee_item_id, shopee_model_id, shopee_product_name, shopee_variation_name, 
               shopee_parent_sku, shopee_variation_sku, has_variation, shopee_stock, shopee_price
        FROM shopee_product_mappings
        WHERE (has_variation = 0 AND shopee_parent_sku = ?)
           OR (has_variation = 1 AND shopee_variation_sku = ?)
    ");
    $stmt->execute([$sku, $sku]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'items' => $items]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
