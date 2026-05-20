<?php
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireLogin();

try {
    $db   = new Database();
    $conn = $db->getConnection();

    // Parent product count (unique item IDs)
    $totalParents = (int)$conn->query("SELECT COUNT(DISTINCT shopee_item_id) FROM shopee_product_mappings")->fetchColumn();
    // Total variations (all rows)
    $totalVars    = (int)$conn->query("SELECT COUNT(*) FROM shopee_product_mappings")->fetchColumn();

    // Mapping stats
    $mapStats = $conn->query("
        SELECT
            SUM(CASE WHEN mapping_status IN ('auto','manual')          THEN 1 ELSE 0 END) AS matched,
            SUM(CASE WHEN mapping_status = 'unmapped'                  THEN 1 ELSE 0 END) AS unmatched,
            SUM(CASE WHEN mapping_status IN ('missing_sku','duplicate') THEN 1 ELSE 0 END) AS errors
        FROM shopee_product_mappings
    ")->fetch(PDO::FETCH_ASSOC);

    // Stock stats
    $stockStats = $conn->query("
        SELECT
            SUM(CASE WHEN shopee_stock = 0              THEN 1 ELSE 0 END) AS oos,
            SUM(CASE WHEN shopee_stock > 0 AND shopee_stock <= 5 THEN 1 ELSE 0 END) AS low_stock
        FROM shopee_product_mappings
    ")->fetch(PDO::FETCH_ASSOC);

    // Waiting for mapping (unmapped + has SKU)
    $waitingForMapping = (int)$conn->query("
        SELECT COUNT(*) FROM shopee_product_mappings
        WHERE mapping_status = 'unmapped'
        AND (shopee_variation_sku != '' OR shopee_parent_sku != '')
    ")->fetchColumn();

    // Last sync info fallback strategy
    // 1. Check successful log
    $lastSyncTime = $conn->query("
        SELECT created_at FROM shopee_sync_logs
        WHERE status='success' AND event_type IN ('product_import', 'stock_update')
        ORDER BY created_at DESC LIMIT 1
    ")->fetchColumn();

    // 2. Fallback to latest queue completion
    if (!$lastSyncTime) {
        $lastSyncTime = $conn->query("
            SELECT completed_at FROM shopee_sync_queues
            WHERE status='completed'
            ORDER BY completed_at DESC LIMIT 1
        ")->fetchColumn();
    }

    // 3. Fallback to latest product mapping sync
    if (!$lastSyncTime) {
        $lastSyncTime = $conn->query("
            SELECT MAX(last_synced_at) FROM shopee_product_mappings
        ")->fetchColumn();
    }


    // Recently synced (last 24h)
    $recentlySynced = (int)$conn->query("
        SELECT COUNT(DISTINCT shopee_item_id) FROM shopee_product_mappings
        WHERE last_synced_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ")->fetchColumn();

    // Config / connection status
    $config = $conn->query("SELECT is_active, access_token, token_expires_at, environment, shop_id FROM shopee_config WHERE is_active=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $connected  = !empty($config) && !empty($config['access_token']);
    $expTs      = $connected ? strtotime($config['token_expires_at'] ?? '') : 0;
    $tokenValid = $expTs && $expTs > time();

    // Latest Queue Status
    $queueRow = $conn->query("SELECT sync_mode, status, error_count, created_at FROM shopee_sync_queues ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    // Resolution Center Open Errors
    $openErrors = (int)$conn->query("SELECT COUNT(*) FROM shopee_error_logs WHERE status = 'open'")->fetchColumn();
    $missingOpenErrors = (int)$conn->query("SELECT COUNT(*) FROM shopee_error_logs WHERE error_type = 'missing_sku' AND status = 'open'")->fetchColumn();
    $duplicateOpenErrors = (int)$conn->query("SELECT COUNT(DISTINCT sku) FROM shopee_error_logs WHERE error_type = 'duplicate_sku' AND status = 'open'")->fetchColumn();

    echo json_encode([
        'success' => true,
        'kpi' => [
            'total_products'    => $totalParents,
            'total_variations'  => $totalVars,
            'matched'           => (int)($mapStats['matched']   ?? 0),
            'unmatched'         => (int)($mapStats['unmatched'] ?? 0),
            'errors'            => (int)($mapStats['errors']    ?? 0),
            'oos'               => (int)($stockStats['oos']      ?? 0),
            'low_stock'         => (int)($stockStats['low_stock']?? 0),
            'waiting_mapping'   => $waitingForMapping,
            'recently_synced'   => $recentlySynced,
        ],
        'status' => [
            'connected'    => $connected,
            'token_valid'  => $tokenValid,
            'environment'  => $config['environment'] ?? 'test',
            'shop_id'      => $config['shop_id']     ?? null,
            'last_sync'    => $lastSyncTime ? date('M d, Y g:i A', strtotime($lastSyncTime)) : 'Never',
        ],
        'health' => [
            'queue_mode'   => $queueRow ? $queueRow['sync_mode'] : null,
            'queue_status' => $queueRow ? $queueRow['status'] : 'idle',
            'queue_time'   => $queueRow ? date('M d, H:i', strtotime($queueRow['created_at'])) : null,
            'open_errors'  => $openErrors,
            'missing_errors' => $missingOpenErrors,
            'duplicate_errors' => $duplicateOpenErrors,
        ],
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
