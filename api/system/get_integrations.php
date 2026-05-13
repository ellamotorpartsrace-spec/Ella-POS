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

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->query("SELECT platform_name, partner_id, partner_key, webhook_url, shop_id, is_test, token_expiry, is_active FROM api_platforms");
    $platforms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($platforms as $p) {
        $results[$p['platform_name']] = [
            'partner_id' => $p['partner_id'] ?? '',
            'partner_key' => $p['partner_key'] ?? '',
            'webhook_url' => $p['webhook_url'] ?? '',
            'shop_id' => $p['shop_id'] ?? '',
            'is_test' => $p['is_test'] ?? 1,
            'is_active' => (bool) $p['is_active'],
            'token_expiry' => $p['token_expiry'],
            'is_expired' => $p['token_expiry'] && time() > $p['token_expiry']
        ];
    }

    echo json_encode(['success' => true, 'data' => $results]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
