<?php
// api/system/test_shopee.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/ShopeeAPI.php';

requireLogin();
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission Denied']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->query("SELECT * FROM api_platforms WHERE platform_name = 'shopee'");
    $shopee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shopee || !$shopee['access_token']) {
        echo json_encode(['success' => false, 'message' => 'Shopee not authorized. Please link your shop first.']);
        exit;
    }

    $api = new ShopeeAPI($shopee['partner_id'], $shopee['partner_key'], (bool)$shopee['is_test']);
    
    // Check if token is expired
    if ($shopee['token_expiry'] && time() > $shopee['token_expiry']) {
        // Attempt to refresh
        $refresh = $api->refreshToken($shopee['refresh_token'], $shopee['shop_id']);
        if (isset($refresh['access_token'])) {
            $shopee['access_token'] = $refresh['access_token'];
            $shopee['refresh_token'] = $refresh['refresh_token'];
            
            // Update DB
            $expiry = time() + ($refresh['expire_in'] ?? 14400) - 300;
            $upd = $conn->prepare("UPDATE api_platforms SET access_token = ?, refresh_token = ?, token_expiry = ? WHERE platform_name = 'shopee'");
            $upd->execute([$shopee['access_token'], $shopee['refresh_token'], $expiry]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Token expired and refresh failed. Please re-authorize.', 'debug' => $refresh]);
            exit;
        }
    }

    // Call Shop Info API
    // Path: /api/v2/shop/get_shop_info
    $response = $api->get('/api/v2/shop/get_shop_info', [], $shopee['access_token'], $shopee['shop_id']);

    if (isset($response['error']) && !empty($response['error'])) {
        echo json_encode(['success' => false, 'message' => 'Shopee API Error: ' . ($response['message'] ?? $response['error'])]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'shop_name' => $response['shop_name'] ?? 'Unknown Shop',
            'region' => $response['region'] ?? 'Unknown',
            'status' => $response['status'] ?? 'Unknown'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
