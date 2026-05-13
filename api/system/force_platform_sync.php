<?php
/**
 * api/system/force_platform_sync.php
 * Manually queues ALL linked products for a stock update push to external platforms.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/SyncHelper.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission Denied']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $count = SyncHelper::queueAllLinkedProducts($conn);

    echo json_encode([
        'success' => true,
        'message' => "Successfully queued {$count} product(s) for platform stock synchronization.",
        'count' => $count
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
