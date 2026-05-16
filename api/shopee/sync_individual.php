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
    $stmt = $conn->prepare("SELECT m.*, p.qty as pos_qty, c.partner_id, c.partner_key, c.shop_id, c.access_token, c.environment 
        FROM shopee_product_mappings m
        LEFT JOIN products p ON m.pos_product_id = p.id
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

    // 3. If mapped, push stock (use current Shopee stock as allocation)
    // In a real scenario, we might have a formula, but for now we just push what's in shopee_stock
    $stockList = [['stock' => (int)$item['shopee_stock']]];
    if ($item['shopee_model_id']) {
        $stockList[0]['model_id'] = (int)$item['shopee_model_id'];
    }

    $syncResult = $shopee->post('/api/v2/product/update_stock', [
        'item_id' => (int)$item['shopee_item_id'],
        'stock_list' => $stockList
    ], $accessToken, $shopId);

    if (isset($syncResult['error']) && !empty($syncResult['error'])) {
        throw new Exception("Shopee Push Error: " . ($syncResult['message'] ?? json_encode($syncResult['error'])));
    }

    // 4. Log
    $conn->prepare("INSERT INTO shopee_sync_logs (event_type, product_name, sku, status, created_at) VALUES ('stock_update', ?, ?, 'success', NOW())")
        ->execute([$item['shopee_product_name'], $item['matched_pos_sku']]);

    echo json_encode(['success' => true, 'message' => 'Product synced successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
