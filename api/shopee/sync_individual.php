<?php
/**
 * api/shopee/sync_individual.php — Sync a single Shopee item (pull info + push stock)
 */
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/ShopeeApi.php';

requireLogin();

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Missing mapping ID']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Get current mapping and Shopee config
    $stmt = $conn->prepare("SELECT m.*, COALESCE(i.quantity, 0) as pos_qty, c.partner_id, c.partner_key, c.shop_id, c.access_token, c.environment 
        FROM shopee_product_mappings m
        LEFT JOIN inventory i ON m.pos_product_id = i.variation_id AND i.store_id = 1
        JOIN shopee_config c ON c.is_active = 1
        WHERE m.id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Mapping not found']);
        exit;
    }

    $isTest = $item['environment'] === 'test';
    $shopee = new ShopeeAPI($item['partner_id'], $item['partner_key'], $isTest);
    $accessToken = $item['access_token'];
    $shopId = $item['shop_id'];

    // 2. Refresh info from Shopee
    $infoResult = $shopee->get('/api/v2/product/get_item_base_info', [
        'item_id_list' => $item['shopee_item_id']
    ], $accessToken, $shopId);

    $shopeeItem = $infoResult['response']['item_list'][0] ?? null;
    if ($shopeeItem) {
        // Update price and generic info
        $price = $shopeeItem['price_info'][0]['current_price'] ?? $item['shopee_price'];
        $conn->prepare("UPDATE shopee_product_mappings SET shopee_price = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$price, $id]);
    }

    // 3. If mapped, push stock (dynamically compute using stock_allocation_ratio)
    $ratio = isset($item['stock_allocation_ratio']) ? (int)$item['stock_allocation_ratio'] : 100;
    $computedStock = floor((int)$item['pos_qty'] * ($ratio / 100));
    if ($computedStock < 0) $computedStock = 0;

    $stockItem = [
        'seller_stock' => [
            [
                'stock' => (int)$computedStock
            ]
        ]
    ];
    if ($item['shopee_model_id']) {
        $stockItem['model_id'] = (int)$item['shopee_model_id'];
    }

    $syncResult = $shopee->post('/api/v2/product/update_stock', [
        'item_id' => (int)$item['shopee_item_id'],
        'stock_list' => [$stockItem]
    ], $accessToken, $shopId);

    if (isset($syncResult['error']) && !empty($syncResult['error'])) {
        throw new Exception("Shopee Push Error: " . ($syncResult['message'] ?? json_encode($syncResult['error'])));
    }

    // Update local cached stock value
    $conn->prepare("UPDATE shopee_product_mappings SET shopee_stock = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$computedStock, $id]);

    // 4. Log
    $conn->prepare("INSERT INTO shopee_sync_logs (event_type, product_name, sku, old_value, new_value, status, created_by, created_at) VALUES ('stock_update', ?, ?, ?, ?, 'success', ?, NOW())")
        ->execute([
            $item['shopee_product_name'], 
            $item['matched_pos_sku'], 
            $item['shopee_stock'],
            $computedStock . " ({$ratio}%)",
            $_SESSION['user_id'] ?? null
        ]);

    echo json_encode(['success' => true, 'message' => 'Product synced successfully']);

} catch (Exception $e) {
    try {
        if (isset($conn)) {
            $logStmt = $conn->prepare("INSERT INTO shopee_sync_logs (event_type, shopee_item_id, product_name, sku, status, error_message, created_by, created_at) VALUES ('stock_update', ?, ?, ?, 'failed', ?, ?, NOW())");
            $logStmt->execute([
                isset($item) ? $item['shopee_item_id'] : null,
                isset($item) ? $item['shopee_product_name'] : null,
                isset($item) ? $item['matched_pos_sku'] : null,
                $e->getMessage(),
                $_SESSION['user_id'] ?? null
            ]);
        }
    } catch (Exception $logEx) {}
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
