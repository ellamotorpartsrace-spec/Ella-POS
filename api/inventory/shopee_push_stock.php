<?php
/**
 * api/inventory/shopee_push_stock.php
 * Automated worker endpoint designed to push Ella POS online inventory levels (store_id = 2)
 * directly to Shopee to ensure their storefront is perfectly matched.
 * Reads from api_sync_queue.
 */
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/ShopeeAPI.php';

// Check if run via CLI or with a secret cron key
$is_cli = (php_sapi_name() === 'cli');
$cron_key = $_GET['cron_key'] ?? '';
$valid_key = 'ella_shopee_push_secret_123'; // Replace with a more secure key later

if (!$is_cli && $cron_key !== $valid_key) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized access to worker.']));
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Get active Shopee config
    $stmt = $conn->query("SELECT * FROM api_platforms WHERE platform_name = 'shopee' AND is_active = 1");
    $shopee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shopee) {
        die(json_encode(['success' => false, 'message' => 'Shopee API not active.']));
    }

    $api = new ShopeeAPI($shopee['partner_id'], $shopee['partner_key'], true);

    // 2. Refresh Token if needed
    if ($shopee['token_expiry'] && (time() + 600) > $shopee['token_expiry']) {
        $refresh = $api->refreshToken($shopee['refresh_token'], (string) $shopee['shop_id']);
        if (isset($refresh['access_token'])) {
            $shopee['access_token'] = $refresh['access_token'];
            $shopee['refresh_token'] = $refresh['refresh_token'];
            $shopee['token_expiry'] = time() + (int) $refresh['expire_in'] - 300;

            $upd = $conn->prepare("UPDATE api_platforms SET access_token = ?, refresh_token = ?, token_expiry = ? WHERE platform_name = 'shopee'");
            $upd->execute([$shopee['access_token'], $shopee['refresh_token'], $shopee['token_expiry']]);
        }
    }

    // 3. Fetch pending items from queue
    // Processing in small batches to avoid timeouts
    $stmtQueue = $conn->prepare("
        SELECT q.queue_id, q.variation_id, q.new_quantity, l.online_product_id, l.online_variation_id 
        FROM api_sync_queue q
        JOIN online_platform_links l ON q.variation_id = l.variation_id
        WHERE q.platform = 'shopee' AND q.status = 'pending' AND l.platform = 'Shopee' AND l.is_active = 1
        ORDER BY q.created_at ASC
        LIMIT 20
    ");
    $stmtQueue->execute();
    $jobs = $stmtQueue->fetchAll(PDO::FETCH_ASSOC);

    if (empty($jobs)) {
        echo json_encode(['success' => true, 'message' => 'No pending items in queue.']);
        exit;
    }

    $processed = 0;
    $errors = 0;

    // 4. Mark jobs as 'processing'
    $jobIds = array_column($jobs, 'queue_id');
    $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
    $conn->prepare("UPDATE api_sync_queue SET status = 'processing' WHERE queue_id IN ($placeholders)")->execute($jobIds);

    // 5. Build batches grouped by online_product_id (item_id)
    $batches = [];
    foreach ($jobs as $job) {
        $pid = $job['online_product_id'];
        if (!$pid)
            continue;

        if (!isset($batches[$pid])) {
            $batches[$pid] = [
                'item_id' => (int) $pid,
                'stock_list' => [],
                'queue_ids' => []
            ];
        }

        $batches[$pid]['stock_list'][] = [
            'model_id' => (int) $job['online_variation_id'],
            'normal_stock' => (int) $job['new_quantity']
        ];
        $batches[$pid]['queue_ids'][] = $job['queue_id'];
    }

    // 6. Push each batch to Shopee
    foreach ($batches as $batch) {
        try {
            $response = $api->post('/api/v2/product/update_stock', [
                'item_id' => $batch['item_id'],
                'stock_list' => $batch['stock_list']
            ], $shopee['access_token'], (string) $shopee['shop_id']);

            if (isset($response['error']) && !empty($response['error'])) {
                throw new Exception($response['message'] ?? $response['error']);
            }

            // Success: Update queue
            $ids = implode(',', $batch['queue_ids']);
            $conn->prepare("UPDATE api_sync_queue SET status = 'success' WHERE queue_id IN ($ids)")->execute();
            $processed += count($batch['queue_ids']);

        } catch (Exception $e) {
            // Failed: Update queue with error
            $errMsg = $e->getMessage();
            $stmtFail = $conn->prepare("UPDATE api_sync_queue SET status = 'failed', last_error = ?, attempts = attempts + 1 WHERE queue_id = ?");
            foreach ($batch['queue_ids'] as $qid) {
                $stmtFail->execute([$errMsg, $qid]);
            }
            $errors += count($batch['queue_ids']);
        }
    }

    echo json_encode([
        'success' => true,
        'summary' => [
            'processed' => $processed,
            'errors' => $errors
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Worker Fatal Error: ' . $e->getMessage()]);
}
