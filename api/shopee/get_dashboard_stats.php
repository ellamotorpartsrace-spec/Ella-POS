<?php
/**
 * api/shopee/get_dashboard_stats.php — Get real statistics for the Shopee dashboard
 */
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. KPI Stats
    $kpi = $conn->query("SELECT 
        COUNT(*) as total_synced,
        SUM(shopee_stock) as total_online_stock,
        SUM(CASE WHEN mapping_status IN ('auto','manual') THEN 1 ELSE 0 END) as active_syncs,
        SUM(CASE WHEN shopee_stock <= 5 AND shopee_stock > 0 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN shopee_stock = 0 THEN 1 ELSE 0 END) as out_of_stock
    FROM shopee_product_mappings")->fetch(PDO::FETCH_ASSOC);

    $failedSyncs = $conn->query("SELECT COUNT(*) FROM shopee_sync_logs WHERE status = 'failed' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
    $lastSync = $conn->query("SELECT created_at FROM shopee_sync_logs WHERE status = 'success' AND event_type = 'product_import' ORDER BY created_at DESC LIMIT 1")->fetchColumn();

    // 2. Recent Activity Timeline
    $logs = $conn->query("SELECT event_type, product_name, status, created_at, error_message 
        FROM shopee_sync_logs 
        ORDER BY created_at DESC 
        LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

    $timeline = [];
    foreach ($logs as $log) {
        $dot = 'dot-info';
        if ($log['status'] === 'failed') $dot = 'dot-danger';
        elseif ($log['event_type'] === 'product_import') $dot = 'dot-shopee';
        elseif ($log['event_type'] === 'stock_update') $dot = 'dot-success';

        $time = date('H:i', strtotime($log['created_at']));
        $text = str_replace('_', ' ', ucfirst($log['event_type']));
        if ($log['product_name']) $text .= ": " . $log['product_name'];
        
        $timeline[] = [
            'dot' => $dot,
            'text' => $text,
            'sub' => $log['status'] === 'failed' ? ($log['error_message'] ?? 'Unknown error') : 'Operation completed successfully',
            'time' => $time
        ];
    }

    // 3. Chart Data (Last 7 Days Sync Activity)
    $syncActivity = $conn->query("SELECT DATE(created_at) as date, 
        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM shopee_sync_logs 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at) ASC")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'kpi' => [
            'synced' => (int)$kpi['total_synced'],
            'online' => (int)$kpi['total_online_stock'],
            'active' => (int)$kpi['active_syncs'],
            'low' => (int)$kpi['low_stock'],
            'oos' => (int)$kpi['out_of_stock'],
            'failed' => (int)$failedSyncs,
            'last_sync' => $lastSync ? date('M d, H:i', strtotime($lastSync)) : 'Never'
        ],
        'timeline' => $timeline,
        'charts' => [
            'sync' => $syncActivity
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
