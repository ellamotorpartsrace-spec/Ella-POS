<?php
/**
 * api/shopee/update_allocation.php — Update stock allocation and push to Shopee
 */
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/ShopeeApi.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$newOnlineStock = $input['online_stock'] ?? null;

if ($id === null || $newOnlineStock === null) {
    echo json_encode(['success' => false, 'error' => 'Missing ID or stock value']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Get current mapping, Shopee config, and POS stock
    $stmt = $conn->prepare("SELECT m.*, c.partner_id, c.partner_key, c.shop_id, c.access_token, c.environment,
        COALESCE(i.quantity, 0) as pos_qty
        FROM shopee_product_mappings m
        JOIN shopee_config c ON c.is_active = 1
        LEFT JOIN inventory i ON m.pos_product_id = i.variation_id AND i.store_id = 1
        WHERE m.id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Product mapping or active config not found']);
        exit;
    }

    // Backend validation: Prevent over-allocation
    if ($newOnlineStock > $item['pos_qty']) {
        echo json_encode(['success' => false, 'error' => "Cannot allocate {$newOnlineStock} items. Only {$item['pos_qty']} available in POS inventory."]);
        exit;
    }

    $isTest = $item['environment'] === 'test';
    $shopee = new ShopeeAPI($item['partner_id'], $item['partner_key'], $isTest);
    
    // 2. Prepare Shopee API call
    // Shopee API v2: /api/v2/product/update_stock
    $stockList = [
        [
            'stock' => (int)$newOnlineStock
        ]
    ];
    
    if ($item['shopee_model_id']) {
        $stockList[0]['model_id'] = (int)$item['shopee_model_id'];
    }

    $body = [
        'item_id' => (int)$item['shopee_item_id'],
        'stock_list' => $stockList
    ];

    $response = $shopee->post('/api/v2/product/update_stock', $body, $item['access_token'], $item['shop_id']);

    if (isset($response['error']) && !empty($response['error'])) {
        $errorMsg = $response['message'] ?? json_encode($response['error']);
        throw new Exception("Shopee API Error: " . $errorMsg);
    }

    // 3. Update local DB
    $updateStmt = $conn->prepare("UPDATE shopee_product_mappings SET shopee_stock = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$newOnlineStock, $id]);

    // 4. Log the success
    $logStmt = $conn->prepare("INSERT INTO shopee_sync_logs (event_type, shopee_item_id, product_name, sku, old_value, new_value, source, status, created_at) 
        VALUES ('stock_update', ?, ?, ?, ?, ?, 'Manual Allocation', 'success', NOW())");
    $logStmt->execute([
        $item['shopee_item_id'],
        $item['shopee_product_name'],
        $item['matched_pos_sku'],
        $item['shopee_stock'],
        $newOnlineStock
    ]);

    echo json_encode(['success' => true, 'message' => 'Stock updated and synced to Shopee']);

} catch (Exception $e) {
    // Log the failure
    try {
        if (isset($conn)) {
            $logStmt = $conn->prepare("INSERT INTO shopee_sync_logs (event_type, status, error_message, created_at) VALUES ('stock_update', 'failed', ?, NOW())");
            $logStmt->execute([$e->getMessage()]);
        }
    } catch (Exception $ex) {}

    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
