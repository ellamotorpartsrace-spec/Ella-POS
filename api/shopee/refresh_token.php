<?php
/**
 * api/shopee/refresh_token.php — Manually refresh Shopee access token
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

    if (!$config) {
        echo json_encode(['success' => false, 'error' => 'Shopee configuration not found.']);
        exit;
    }

    if (empty($config['refresh_token'])) {
        echo json_encode(['success' => false, 'error' => 'No refresh token found. Please authorize your shop first.']);
        exit;
    }

    $isTest = $config['environment'] === 'test';
    $shopee = new ShopeeAPI($config['partner_id'], $config['partner_key'], $isTest);
    
    $shopId = $config['shop_id'];
    $refreshToken = $config['refresh_token'];

    $result = $shopee->refreshToken($refreshToken, $shopId);

    if (isset($result['error']) && !empty($result['error'])) {
        $errorMsg = $result['message'] ?? (is_string($result['error']) ? $result['error'] : json_encode($result['error']));
        
        // Log the failure
        $conn->prepare("INSERT INTO shopee_sync_logs (event_type, source, status, error_message, created_at) VALUES ('token_refresh', 'Manual Refresh', 'failed', ?, NOW())")
             ->execute([$errorMsg]);

        echo json_encode(['success' => false, 'error' => 'Shopee API Error: ' . $errorMsg]);
        exit;
    }

    if (!isset($result['access_token'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid response from Shopee API: ' . json_encode($result)]);
        exit;
    }

    $newAccessToken = $result['access_token'];
    $newRefreshToken = $result['refresh_token'];
    $expireIn = $result['expire_in'] ?? 14400;
    $expiresAt = date('Y-m-d H:i:s', time() + $expireIn);

    // Update DB
    $stmt = $conn->prepare("
        UPDATE shopee_config 
        SET access_token = ?, refresh_token = ?, token_expires_at = ?, updated_at = NOW()
        WHERE is_active = 1
    ");
    $stmt->execute([$newAccessToken, $newRefreshToken, $expiresAt]);

    // Log success
    $conn->prepare("INSERT INTO shopee_sync_logs (event_type, source, status, created_at) VALUES ('token_refresh', 'Manual Refresh', 'success', NOW())")
         ->execute();

    echo json_encode([
        'success' => true, 
        'message' => 'Tokens refreshed successfully',
        'expires_at' => $expiresAt
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
