<?php
/**
 * api/shopee/fetch_products.php — Fetch ALL products from Shopee & save to DB
 * 
 * This pulls every product + variation from the authorized Shopee shop,
 * applies the correct SKU matching logic, and saves to shopee_product_mappings.
 * 
 * Matching Rule:
 *   - Product WITH variations  → use variation_sku (model_sku) to match POS SKU
 *   - Product WITHOUT variations → use parent_sku (item_sku) to match POS SKU
 */
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/ShopeeApi.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    echo json_encode(['success' => false, 'error' => 'Admin or manager access required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Load active Shopee config
    $stmt = $conn->prepare("SELECT * FROM shopee_config WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config || empty($config['access_token'])) {
        echo json_encode(['success' => false, 'error' => 'Shopee not authorized. Please connect your shop first.']);
        exit;
    }

    $isTest = $config['environment'] === 'test';
    $shopee = new ShopeeAPI($config['partner_id'], $config['partner_key'], $isTest);
    $accessToken = $config['access_token'];
    $shopId      = $config['shop_id'];

    // Check if token needs refresh (less than 10 mins left OR already expired)
    if (strtotime($config['token_expires_at']) - time() < 600) {
        $refreshResult = $shopee->refreshToken($config['refresh_token'], $shopId);
        if (isset($refreshResult['access_token'])) {
            $accessToken = $refreshResult['access_token'];
            $expiresAt = date('Y-m-d H:i:s', time() + ($refreshResult['expire_in'] ?? 14400));
            $conn->prepare("UPDATE shopee_config SET access_token=?, refresh_token=?, token_expires_at=?, updated_at=NOW() WHERE is_active=1")
                ->execute([$accessToken, $refreshResult['refresh_token'], $expiresAt]);
        } else {
            $errorMsg = $refreshResult['message'] ?? (isset($refreshResult['error']) ? json_encode($refreshResult['error']) : 'Unknown error during refresh');
            throw new Exception("Shopee token expired and auto-refresh failed: " . $errorMsg . ". Please go to Settings and re-authorize.");
        }
    }

    // ═══════════════════════════════════════
    // STEP 1: Fetch items from Shopee (Paginated)
    // ═══════════════════════════════════════
    $allProducts = [];
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $pageSize = 50;

    $listResult = $shopee->get('/api/v2/product/get_item_list', [
        'offset'      => $offset,
        'page_size'   => $pageSize,
        'item_status' => 'NORMAL',
    ], $accessToken, $shopId);

    if (isset($listResult['error']) && $listResult['error'] !== '' && $listResult['error'] !== 0) {
        echo json_encode(['success' => false, 'error' => 'Shopee API error: ' . ($listResult['message'] ?? json_encode($listResult['error']))]);
        exit;
    }

    $items = $listResult['response']['item'] ?? [];
    $hasNextPage = $listResult['response']['has_next_page'] ?? false;

    if (!empty($items)) {
        $itemIds = array_column($items, 'item_id');

        // ═══════════════════════════════════════
        // STEP 2: Get base info for these items
        // ═══════════════════════════════════════
        $itemIdList = implode(',', $itemIds);
        $infoResult = $shopee->get('/api/v2/product/get_item_base_info', [
            'item_id_list' => $itemIdList,
        ], $accessToken, $shopId);

        $itemInfoList = $infoResult['response']['item_list'] ?? [];

        foreach ($itemInfoList as $item) {
            $itemId   = $item['item_id'];
            $itemName = $item['item_name'] ?? '';
            $itemSku  = $item['item_sku'] ?? '';
            $imageUrl = '';
            if (!empty($item['image']['image_url_list'])) {
                $imageUrl = $item['image']['image_url_list'][0];
            }

            $hasModels = !empty($item['has_model']);

            if ($hasModels) {
                // ═══════════════════════════════════════
                // STEP 3A: Get variations (models)
                // ═══════════════════════════════════════
                $modelResult = $shopee->get('/api/v2/product/get_model_list', [
                    'item_id' => $itemId,
                ], $accessToken, $shopId);

                $models = $modelResult['response']['model'] ?? [];

                foreach ($models as $model) {
                    $varSku = $model['model_sku'] ?? '';
                    $varName = '';

                    if (isset($modelResult['response']['tier_variation'])) {
                        $tiers = $modelResult['response']['tier_variation'];
                        $tierIndex = $model['tier_index'] ?? [];
                        $nameParts = [];
                        foreach ($tierIndex as $i => $idx) {
                            if (isset($tiers[$i]['option_list'][$idx]['option'])) {
                                $nameParts[] = $tiers[$i]['option_list'][$idx]['option'];
                            }
                        }
                        $varName = implode(' / ', $nameParts);
                    }

                    $stock = 0;
                    if (isset($model['stock_info_v2']['summary_info']['total_available_stock'])) {
                        $stock = (int) $model['stock_info_v2']['summary_info']['total_available_stock'];
                    } elseif (isset($model['stock_info'][0]['current_stock'])) {
                        $stock = (int) $model['stock_info'][0]['current_stock'];
                    }

                    $price = 0;
                    if (isset($model['price_info'][0]['current_price'])) {
                        $price = (float) $model['price_info'][0]['current_price'];
                    }

                    $allProducts[] = [
                        'shopee_item_id'        => $itemId,
                        'shopee_model_id'       => $model['model_id'] ?? null,
                        'shopee_product_name'   => $itemName,
                        'shopee_variation_name' => $varName ?: null,
                        'shopee_parent_sku'     => $itemSku,
                        'shopee_variation_sku'  => $varSku,
                        'has_variation'         => 1,
                        'shopee_stock'          => $stock,
                        'shopee_price'          => $price,
                        'shopee_image_url'      => $imageUrl,
                    ];
                }
            } else {
                // ═══════════════════════════════════════
                // STEP 3B: No variations — single product
                // ═══════════════════════════════════════
                $stock = 0;
                if (isset($item['stock_info_v2']['summary_info']['total_available_stock'])) {
                    $stock = (int) $item['stock_info_v2']['summary_info']['total_available_stock'];
                } elseif (isset($item['stock_info'][0]['current_stock'])) {
                    $stock = (int) $item['stock_info'][0]['current_stock'];
                }

                $price = isset($item['price_info'][0]['current_price'])
                    ? (float) $item['price_info'][0]['current_price'] : 0;

                $allProducts[] = [
                    'shopee_item_id'        => $itemId,
                    'shopee_model_id'       => null,
                    'shopee_product_name'   => $itemName,
                    'shopee_variation_name' => null,
                    'shopee_parent_sku'     => $itemSku,
                    'shopee_variation_sku'  => null,
                    'has_variation'         => 0,
                    'shopee_stock'          => $stock,
                    'shopee_price'          => $price,
                    'shopee_image_url'      => $imageUrl,
                ];
            }
        }
    }

    // ═══════════════════════════════════════
    // STEP 4: Save to DB (No Auto-match)
    // ═══════════════════════════════════════
    $inserted = 0;
    $updated = 0;
    $autoMatched = 0; // Keeping variable for response payload compatibility

    foreach ($allProducts as $product) {
        // Check if already exists
        $checkStmt = $conn->prepare("
            SELECT id, matched_pos_sku, mapping_status FROM shopee_product_mappings 
            WHERE shopee_item_id = ? AND (shopee_model_id = ? OR (shopee_model_id IS NULL AND ? IS NULL))
            LIMIT 1
        ");
        $modelId = $product['shopee_model_id'];
        $checkStmt->execute([$product['shopee_item_id'], $modelId, $modelId]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        // Determine match key to check if missing
        $matchKey = $product['has_variation']
            ? ($product['shopee_variation_sku'] ?? '')
            : ($product['shopee_parent_sku'] ?? '');

        $mappingStatus = 'unmapped';
        if (empty($matchKey)) {
            $mappingStatus = 'missing_sku';
        }
        
        $matchedPosSku = null;
        $posProductId  = null;

        if ($existing) {
            // Update existing record (keep manual mappings)
            if ($existing['mapping_status'] === 'manual' && $existing['matched_pos_sku']) {
                // Don't overwrite manual mappings
                $stmt = $conn->prepare("
                    UPDATE shopee_product_mappings SET
                        shopee_product_name = ?, shopee_variation_name = ?,
                        shopee_parent_sku = ?, shopee_variation_sku = ?,
                        has_variation = ?, shopee_stock = ?, shopee_price = ?,
                        shopee_image_url = ?, last_synced_at = NOW(), updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $product['shopee_product_name'], $product['shopee_variation_name'],
                    $product['shopee_parent_sku'], $product['shopee_variation_sku'],
                    $product['has_variation'], $product['shopee_stock'], $product['shopee_price'],
                    $product['shopee_image_url'], $existing['id']
                ]);
            } else {
                // When we update, if it was already mapped manually or auto, we should NOT revert it to unmapped, 
                // UNLESS the SKU changed to missing.
                $finalMappingStatus = $mappingStatus;
                $finalPosSku = $matchedPosSku;
                $finalPosId = $posProductId;

                if ($existing['mapping_status'] !== 'unmapped' && $existing['mapping_status'] !== 'missing_sku' && $mappingStatus !== 'missing_sku') {
                    // Keep existing mapping
                    $finalMappingStatus = $existing['mapping_status'];
                    $finalPosSku = $existing['matched_pos_sku'];
                    // We need the pos_product_id if we want to retain it, but it's not fetched.
                    // Wait, existing query doesn't fetch pos_product_id, let me just let it be.
                    // Actually we shouldn't wipe pos_product_id.
                    // Let's just update the shopee-specific fields and leave POS ones alone if it was already mapped.
                }

                if ($existing['mapping_status'] === 'auto' || $existing['mapping_status'] === 'manual') {
                    // Just update Shopee fields, don't touch POS matching fields to avoid unlinking
                    $stmt = $conn->prepare("
                        UPDATE shopee_product_mappings SET
                            shopee_product_name = ?, shopee_variation_name = ?,
                            shopee_parent_sku = ?, shopee_variation_sku = ?,
                            has_variation = ?, shopee_stock = ?, shopee_price = ?,
                            shopee_image_url = ?, last_synced_at = NOW(), updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $product['shopee_product_name'], $product['shopee_variation_name'],
                        $product['shopee_parent_sku'], $product['shopee_variation_sku'],
                        $product['has_variation'], $product['shopee_stock'], $product['shopee_price'],
                        $product['shopee_image_url'], $existing['id']
                    ]);
                } else {
                    $stmt = $conn->prepare("
                        UPDATE shopee_product_mappings SET
                            shopee_product_name = ?, shopee_variation_name = ?,
                            shopee_parent_sku = ?, shopee_variation_sku = ?,
                            has_variation = ?, shopee_stock = ?, shopee_price = ?,
                            shopee_image_url = ?, matched_pos_sku = ?, pos_product_id = ?,
                            mapping_status = ?, last_synced_at = NOW(), updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $product['shopee_product_name'], $product['shopee_variation_name'],
                        $product['shopee_parent_sku'], $product['shopee_variation_sku'],
                        $product['has_variation'], $product['shopee_stock'], $product['shopee_price'],
                        $product['shopee_image_url'], $matchedPosSku, $posProductId,
                        $mappingStatus, $existing['id']
                    ]);
                }
            }
            $updated++;
        } else {
            // Insert new
            $stmt = $conn->prepare("
                INSERT INTO shopee_product_mappings (
                    shopee_item_id, shopee_model_id, shopee_product_name, shopee_variation_name,
                    shopee_parent_sku, shopee_variation_sku, has_variation,
                    shopee_stock, shopee_price, shopee_image_url,
                    matched_pos_sku, pos_product_id, mapping_status, last_synced_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $product['shopee_item_id'], $product['shopee_model_id'],
                $product['shopee_product_name'], $product['shopee_variation_name'],
                $product['shopee_parent_sku'], $product['shopee_variation_sku'],
                $product['has_variation'], $product['shopee_stock'], $product['shopee_price'],
                $product['shopee_image_url'], $matchedPosSku, $posProductId, $mappingStatus
            ]);
            $inserted++;
        }
    }

    $totalFetched = count($items);
    
    $conn->prepare("
        INSERT INTO shopee_sync_logs (event_type, product_name, source, status, new_value, created_by, created_at)
        VALUES ('product_import', ?, 'Shopee API Import', 'success', ?, ?, NOW())
    ")->execute([
        "Fetched page offset {$offset} → " . count($allProducts) . " rows",
        "Inserted: {$inserted}, Updated: {$updated} (No auto-match)",
        $_SESSION['user_id'] ?? null
    ]);

    echo json_encode([
        'success'      => true,
        'message'      => "Successfully imported products from Shopee",
        'total_items'  => $totalFetched,
        'total_rows'   => count($allProducts),
        'inserted'     => $inserted,
        'updated'      => $updated,
        'auto_matched' => $autoMatched,
        'has_next_page'=> $hasNextPage,
        'next_offset'  => $offset + $pageSize
    ]);

} catch (Exception $e) {
    // Log failure
    try {
        $conn->prepare("
            INSERT INTO shopee_sync_logs (event_type, source, status, error_message, created_at)
            VALUES ('product_import', 'Shopee API Import', 'failed', ?, NOW())
        ")->execute([$e->getMessage()]);
    } catch (Exception $logErr) {}

    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
