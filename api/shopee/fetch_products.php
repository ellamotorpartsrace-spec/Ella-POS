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
    // STEP 1: Determine Sync Mode & Fetch Items
    // ═══════════════════════════════════════
    $mode = $_GET['mode'] ?? 'full';
    $queueId = $_GET['queue_id'] ?? null;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $pageSize = 100;
    
    $allProducts = [];
    $hasNextPage = false;
    $totalFetched = 0;
    $inserted = 0; $updated = 0; $skipped = 0; $autoMatched = 0;

    // Mode: Mapping Only (No API call needed)
    if ($mode === 'mapping') {
        if ($offset == 0) {
            $selectStmt = $conn->prepare("
                SELECT spm.id, spm.shopee_item_id, spm.shopee_product_name, spm.shopee_variation_name, 
                       spm.shopee_variation_sku, spm.shopee_parent_sku, pv.sku AS pos_sku, pv.product_id AS pos_id
                FROM shopee_product_mappings spm
                JOIN product_variations pv ON pv.sku COLLATE utf8mb4_unicode_ci = (CASE WHEN spm.has_variation = 1 THEN spm.shopee_variation_sku ELSE spm.shopee_parent_sku END)
                WHERE spm.mapping_status IN ('unmapped', 'missing_sku') AND pv.sku IS NOT NULL AND pv.sku != ''
            ");
            $selectStmt->execute();
            $matches = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $autoMatched = 0;
            if (!empty($matches)) {
                $updateStmt = $conn->prepare("
                    UPDATE shopee_product_mappings 
                    SET matched_pos_sku = ?, pos_product_id = ?, mapping_status = 'auto', updated_at = NOW() 
                    WHERE id = ?
                ");
                $logStmt = $conn->prepare("
                    INSERT INTO shopee_sync_logs (event_type, shopee_item_id, product_name, sku, old_value, new_value, source, status, created_by, created_at)
                    VALUES ('mapping', ?, ?, ?, 'Unmapped', ?, 'Auto-Match', 'success', ?, NOW())
                ");

                foreach ($matches as $row) {
                    $updateStmt->execute([$row['pos_sku'], $row['pos_id'], $row['id']]);
                    
                    $prodName = $row['shopee_product_name'];
                    if (!empty($row['shopee_variation_name'])) {
                        $prodName .= ' — ' . $row['shopee_variation_name'];
                    }
                    $shopeeSku = $row['shopee_variation_sku'] ?: $row['shopee_parent_sku'] ?: '—';
                    $newValLog = "Linked to POS SKU: {$row['pos_sku']}";
                    
                    $logStmt->execute([
                        $row['shopee_item_id'],
                        $prodName,
                        $shopeeSku,
                        $newValLog,
                        $_SESSION['user_id'] ?? null
                    ]);
                    $autoMatched++;
                }
            }
        }
        
        echo json_encode([
            'success'      => true,
            'message'      => 'Mapping Sync Complete',
            'total_items'  => 0, 'total_rows' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0,
            'auto_matched' => $autoMatched,
            'has_next_page'=> false,
            'next_offset'  => 0
        ]);
        exit;
    }

    $apiParams = [
        'offset'      => $offset,
        'page_size'   => $pageSize,
        'item_status' => 'NORMAL',
    ];

    // Modes: Quick, Stock, Price - Use update_time_from
    if (in_array($mode, ['quick', 'stock', 'price'])) {
        $lastSyncStmt = $conn->query("SELECT MAX(last_synced_at) as last_sync FROM shopee_product_mappings");
        $lastSyncRow = $lastSyncStmt->fetch();
        if ($lastSyncRow && $lastSyncRow['last_sync']) {
            // Shopee update_time_from is UNIX timestamp
            // Subtract 1 hour as buffer
            $apiParams['update_time_from'] = strtotime($lastSyncRow['last_sync']) - 3600; 
            $apiParams['update_time_to'] = time() + 3600;
        }
    }

    $listResult = $shopee->get('/api/v2/product/get_item_list', $apiParams, $accessToken, $shopId);

    if (isset($listResult['error']) && $listResult['error'] !== '' && $listResult['error'] !== 0) {
        echo json_encode(['success' => false, 'error' => 'Shopee API error: ' . ($listResult['message'] ?? json_encode($listResult['error']))]);
        exit;
    }

    $items = $listResult['response']['item'] ?? [];
    $hasNextPage = $listResult['response']['has_next_page'] ?? false;

    if (!empty($items)) {
        $itemIds = array_column($items, 'item_id');

        // ═══════════════════════════════════════
        // STEP 2: Get base info for these items (chunked in batches of 50 as permitted by Shopee)
        // ═══════════════════════════════════════
        $itemInfoList = [];
        $itemIdChunks = array_chunk($itemIds, 50);
        foreach ($itemIdChunks as $chunk) {
            $itemIdList = implode(',', $chunk);
            $infoResult = $shopee->get('/api/v2/product/get_item_base_info', [
                'item_id_list' => $itemIdList,
            ], $accessToken, $shopId);
            if (isset($infoResult['response']['item_list'])) {
                $itemInfoList = array_merge($itemInfoList, $infoResult['response']['item_list']);
            }
        }

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
    $updated  = 0;
    $skipped  = 0;
    $autoMatched = 0;

    // Optimize DB reads by loading all existing mappings for the current batch at once
    $existingMappings = [];
    if (!empty($itemIds)) {
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $stmt = $conn->prepare("
            SELECT id, shopee_item_id, shopee_model_id, matched_pos_sku, mapping_status, sync_hash 
            FROM shopee_product_mappings 
            WHERE shopee_item_id IN ($placeholders)
        ");
        $stmt->execute($itemIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = $row['shopee_item_id'] . '_' . ($row['shopee_model_id'] ?? 'parent');
            $existingMappings[$key] = $row;
        }
    }

    foreach ($allProducts as $product) {
        // Content hash for smart skip (avoids unnecessary DB writes on re-sync)
        $contentHash = md5(implode('|', [
            $product['shopee_product_name'],
            $product['shopee_variation_name'] ?? '',
            $product['shopee_parent_sku'] ?? '',
            $product['shopee_variation_sku'] ?? '',
            $product['shopee_stock'],
            $product['shopee_price'],
        ]));

        // Check if already exists in pre-fetched cache
        $modelId = $product['shopee_model_id'];
        $cacheKey = $product['shopee_item_id'] . '_' . ($modelId ?? 'parent');
        $existing = $existingMappings[$cacheKey] ?? null;

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
            if (isset($existing['sync_hash']) && $existing['sync_hash'] === $contentHash && $mode !== 'stock' && $mode !== 'price') {
                $conn->prepare("UPDATE shopee_product_mappings SET last_synced_at=NOW() WHERE id=?")
                    ->execute([$existing['id']]);
                $skipped++;
                continue;
            }

            // If mode is 'stock' or 'price', ONLY update those fields
            if ($mode === 'stock') {
                $conn->prepare("UPDATE shopee_product_mappings SET shopee_stock=?, last_stock_sync_at=NOW(), last_synced_at=NOW() WHERE id=?")->execute([$product['shopee_stock'], $existing['id']]);
                $updated++;
                continue;
            }
            if ($mode === 'price') {
                $conn->prepare("UPDATE shopee_product_mappings SET shopee_price=?, last_price_sync_at=NOW(), last_synced_at=NOW() WHERE id=?")->execute([$product['shopee_price'], $existing['id']]);
                $updated++;
                continue;
            }

            // Update existing record (keep manual mappings)
            if ($existing['mapping_status'] === 'manual') {
                // Don't overwrite manual mappings
                $stmt = $conn->prepare("
                    UPDATE shopee_product_mappings SET
                        shopee_product_name=?, shopee_variation_name=?,
                        shopee_parent_sku=?, shopee_variation_sku=?,
                        has_variation=?, shopee_stock=?, shopee_price=?,
                        shopee_image_url=?, sync_hash=?, last_synced_at=NOW(), updated_at=NOW()
                    WHERE id=?
                ");
                $stmt->execute([
                    $product['shopee_product_name'], $product['shopee_variation_name'],
                    $product['shopee_parent_sku'], $product['shopee_variation_sku'],
                    $product['has_variation'], $product['shopee_stock'], $product['shopee_price'],
                    $product['shopee_image_url'], $contentHash, $existing['id']
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
                            shopee_product_name=?, shopee_variation_name=?,
                            shopee_parent_sku=?, shopee_variation_sku=?,
                            has_variation=?, shopee_stock=?, shopee_price=?,
                            shopee_image_url=?, sync_hash=?, last_synced_at=NOW(), updated_at=NOW()
                        WHERE id=?
                    ");
                    $stmt->execute([
                        $product['shopee_product_name'], $product['shopee_variation_name'],
                        $product['shopee_parent_sku'], $product['shopee_variation_sku'],
                        $product['has_variation'], $product['shopee_stock'], $product['shopee_price'],
                        $product['shopee_image_url'], $contentHash, $existing['id']
                    ]);
                } else {
                    $stmt = $conn->prepare("
                        UPDATE shopee_product_mappings SET
                            shopee_product_name=?, shopee_variation_name=?,
                            shopee_parent_sku=?, shopee_variation_sku=?,
                            has_variation=?, shopee_stock=?, shopee_price=?,
                            shopee_image_url=?, matched_pos_sku=?, pos_product_id=?,
                            mapping_status=?, sync_hash=?, last_synced_at=NOW(), updated_at=NOW()
                        WHERE id=?
                    ");
                    $stmt->execute([
                        $product['shopee_product_name'], $product['shopee_variation_name'],
                        $product['shopee_parent_sku'], $product['shopee_variation_sku'],
                        $product['has_variation'], $product['shopee_stock'], $product['shopee_price'],
                        $product['shopee_image_url'], $matchedPosSku, $posProductId,
                        $mappingStatus, $contentHash, $existing['id']
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
                    matched_pos_sku, pos_product_id, mapping_status, sync_hash, last_synced_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $product['shopee_item_id'], $product['shopee_model_id'],
                $product['shopee_product_name'], $product['shopee_variation_name'],
                $product['shopee_parent_sku'], $product['shopee_variation_sku'],
                $product['has_variation'], $product['shopee_stock'], $product['shopee_price'],
                $product['shopee_image_url'], $matchedPosSku, $posProductId, $mappingStatus, $contentHash
            ]);
            $inserted++;
        }
    }

    $totalFetched = count($items);
    
    // Update queue stats and accumulate batch counts
    if ($queueId) {
        $conn->prepare("
            UPDATE shopee_sync_queues 
            SET processed_items = processed_items + ?,
                inserted_count = inserted_count + ?,
                updated_count = updated_count + ?,
                skipped_count = skipped_count + ?
            WHERE id = ?
        ")->execute([$totalFetched, $inserted, $updated, $skipped, $queueId]);
    } else {
        // Direct, unqueued sync - log individual page details for backward compatibility
        $conn->prepare("
            INSERT INTO shopee_sync_logs (event_type, product_name, source, status, new_value, created_by, created_at)
            VALUES ('product_import', ?, 'Shopee API Import', 'success', ?, ?, NOW())
        ")->execute([
            "Fetched page offset {$offset} → " . count($allProducts) . " rows",
            "Inserted: {$inserted}, Updated: {$updated} (No auto-match)",
            $_SESSION['user_id'] ?? null
        ]);
    }

    echo json_encode([
        'success'      => true,
        'message'      => 'Successfully synced products from Shopee',
        'total_items'  => $totalFetched,
        'total_rows'   => count($allProducts),
        'inserted'     => $inserted,
        'updated'      => $updated,
        'skipped'      => $skipped,
        'auto_matched' => $autoMatched,
        'has_next_page'=> $hasNextPage,
        'next_offset'  => $offset + $pageSize
    ]);

} catch (Exception $e) {
    // Log failure
    try {
        $conn->prepare("
            INSERT INTO shopee_sync_logs (event_type, source, status, error_message, created_by, created_at)
            VALUES ('product_import', 'Shopee API Import', 'failed', ?, ?, NOW())
        ")->execute([$e->getMessage(), $_SESSION['user_id'] ?? null]);
    } catch (Exception $logErr) {}

    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
