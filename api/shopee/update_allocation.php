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
$onlineStock = isset($input['online_stock']) ? (int)$input['online_stock'] : null;

if ($id === null || $onlineStock === null) {
    echo json_encode(['success' => false, 'error' => 'Missing ID or stock value']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Get current mapping, Shopee config, and POS stocks
    $stmt = $conn->prepare("SELECT m.*, c.partner_id, c.partner_key, c.shop_id, c.access_token, c.environment,
        COALESCE(i1.quantity, 0) as pos_physical_qty,
        COALESCE(i2.quantity, 0) as pos_online_qty
        FROM shopee_product_mappings m
        JOIN shopee_config c ON c.is_active = 1
        LEFT JOIN inventory i1 ON m.pos_product_id = i1.variation_id AND i1.store_id = 1
        LEFT JOIN inventory i2 ON m.pos_product_id = i2.variation_id AND i2.store_id = 2
        WHERE m.id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Product mapping or active config not found']);
        exit;
    }

    $posPhysicalQty = (int)$item['pos_physical_qty'];
    $posOnlineQty = (int)$item['pos_online_qty'];
    $totalQty = $posPhysicalQty + $posOnlineQty;

    if ($onlineStock < 0) {
        $onlineStock = 0;
    }

    if ($onlineStock > $totalQty) {
        echo json_encode(['success' => false, 'error' => "Allocated stock ({$onlineStock}) cannot exceed total POS available stock ({$totalQty})"]);
        exit;
    }

    $newPhysicalStock = $totalQty - $onlineStock;
    $newOnlineStock = $onlineStock;

    // Internally save the corresponding ratio percentage for dynamic syncing later
    $allocationRatio = $totalQty > 0 ? (int)round(($newOnlineStock / $totalQty) * 100) : 100;

    $isTest = $item['environment'] === 'test';
    $shopee = new ShopeeAPI($item['partner_id'], $item['partner_key'], $isTest);

    $conn->beginTransaction();

    // 2. Perform local inventory deduction/allocation shifts
    if (!empty($item['pos_product_id'])) {
        // Update physical store (store_id = 1)
        $updStore1 = $conn->prepare("UPDATE inventory SET quantity = ? WHERE variation_id = ? AND store_id = 1");
        $updStore1->execute([$newPhysicalStock, $item['pos_product_id']]);

        // Update or insert online shop (store_id = 2)
        $updStore2 = $conn->prepare("
            INSERT INTO inventory (variation_id, store_id, quantity) 
            VALUES (?, 2, ?)
            ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)
        ");
        $updStore2->execute([$item['pos_product_id'], $newOnlineStock]);

        // Log stock movements for audit trailing
        $stmtCap = $conn->prepare("SELECT price_capital FROM product_variations WHERE variation_id = ?");
        $stmtCap->execute([$item['pos_product_id']]);
        $capital_cost = (float)($stmtCap->fetchColumn() ?? 0);

        $movementStmt = $conn->prepare("
            INSERT INTO stock_movements 
            (variation_id, store_id, type, quantity, previous_stock, new_stock, reference, remarks, created_by, capital_cost)
            VALUES (?, ?, 'allocation_adjustment', ?, ?, ?, ?, 'Shopee Stock Allocation Sync', ?, ?)
        ");

        $ref = 'SHP-ALLOC-' . date('YmdHis');
        $userId = $_SESSION['user_id'] ?? null;

        // Log Physical Store changes
        $physicalDiff = $newPhysicalStock - $posPhysicalQty;
        if ($physicalDiff != 0) {
            $movementStmt->execute([
                $item['pos_product_id'],
                1,
                $physicalDiff,
                $posPhysicalQty,
                $newPhysicalStock,
                $ref,
                $userId,
                $capital_cost
            ]);
        }

        // Log Online Shop changes
        $onlineDiff = $newOnlineStock - $posOnlineQty;
        if ($onlineDiff != 0) {
            $movementStmt->execute([
                $item['pos_product_id'],
                2,
                $onlineDiff,
                $posOnlineQty,
                $newOnlineStock,
                $ref,
                $userId,
                $capital_cost
            ]);
        }
    }
    
    // 3. Prepare Shopee API call
    // Shopee API v2: /api/v2/product/update_stock
    $stockItem = [
        'seller_stock' => [
            [
                'stock' => (int)$newOnlineStock
            ]
        ]
    ];
    
    if ($item['shopee_model_id']) {
        $stockItem['model_id'] = (int)$item['shopee_model_id'];
    }

    $body = [
        'item_id' => (int)$item['shopee_item_id'],
        'stock_list' => [$stockItem]
    ];

    $response = $shopee->post('/api/v2/product/update_stock', $body, $item['access_token'], $item['shop_id']);

    if (isset($response['error']) && !empty($response['error'])) {
        $errorMsg = $response['message'] ?? json_encode($response['error']);
        throw new Exception("Shopee API Error: " . $errorMsg);
    }

    // 4. Update local DB mapping cache with ratio and computed stock
    $updateStmt = $conn->prepare("UPDATE shopee_product_mappings SET stock_allocation_ratio = ?, shopee_stock = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$allocationRatio, $newOnlineStock, $id]);

    // 5. Log the success
    $logStmt = $conn->prepare("INSERT INTO shopee_sync_logs (event_type, shopee_item_id, product_name, sku, old_value, new_value, source, status, created_by, created_at) 
        VALUES ('stock_update', ?, ?, ?, ?, ?, 'Manual Allocation (Ratio)', 'success', ?, NOW())");
    $logStmt->execute([
        $item['shopee_item_id'],
        $item['shopee_product_name'],
        $item['matched_pos_sku'],
        $item['shopee_stock'],
        $newOnlineStock . " ({$allocationRatio}%)",
        $_SESSION['user_id'] ?? null
    ]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Ratio updated and stock synced to Shopee']);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    // Log the failure
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
    } catch (Exception $ex) {}

    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
