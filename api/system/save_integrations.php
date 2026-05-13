<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission Denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['platform']) || !isset($input['partner_id']) || !isset($input['partner_key'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $p = trim((string) $input['platform']);
    $pid = trim((string) $input['partner_id']);
    $pkey = trim((string) $input['partner_key']);
    $wurl = trim((string) ($input['webhook_url'] ?? ''));
    $sid = trim((string) ($input['shop_id'] ?? ''));
    $istest = isset($input['is_test']) ? (int) $input['is_test'] : 1;

    $stmt = $conn->prepare("
        INSERT INTO api_platforms (platform_name, partner_id, partner_key, webhook_url, shop_id, is_test, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            partner_id = VALUES(partner_id),
            partner_key = VALUES(partner_key),
            webhook_url = VALUES(webhook_url),
            shop_id = VALUES(shop_id),
            is_test = VALUES(is_test),
            is_active = VALUES(is_active)
    ");

    $is_active = isset($input['is_active']) ? (int) $input['is_active'] : 0;
    $stmt->execute([$p, $pid, $pkey, $wurl, $sid, $istest, $is_active]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
