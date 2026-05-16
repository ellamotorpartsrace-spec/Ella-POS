<?php
/**
 * api/shopee/callback.php — OAuth callback from Shopee
 * Shopee redirects here after shop owner authorizes.
 * URL: /ella-pos/api/shopee/callback.php?code=xxx&shop_id=xxx
 */
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/ShopeeApi.php';

try {
    $code   = $_GET['code'] ?? '';
    $shopId = $_GET['shop_id'] ?? '';

    if (empty($code) || empty($shopId)) {
        die("Authorization failed: Missing code or shop_id from Shopee.");
    }

    $db = new Database();
    $conn = $db->getConnection();

    // Load active config
    $stmt = $conn->prepare("SELECT * FROM shopee_config WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        die("No Shopee configuration found.");
    }

    $isTest = $config['environment'] === 'test';
    $shopee = new ShopeeAPI($config['partner_id'], $config['partner_key'], $isTest);

    // Exchange code for access token
    $tokenResult = $shopee->getAccessToken($code, $shopId);

    if (isset($tokenResult['error']) && !empty($tokenResult['error'])) {
        $errorMsg = is_string($tokenResult['error']) ? $tokenResult['error'] : json_encode($tokenResult['error']);
        die("Token exchange failed: " . $errorMsg);
    }

    $accessToken  = $tokenResult['access_token'] ?? '';
    $refreshToken = $tokenResult['refresh_token'] ?? '';
    $expireIn     = $tokenResult['expire_in'] ?? 14400; // ~4 hours default

    if (empty($accessToken)) {
        die("Token exchange failed: No access_token received. Response: " . json_encode($tokenResult));
    }

    // Save tokens to database
    $expiresAt = date('Y-m-d H:i:s', time() + $expireIn);
    $stmt = $conn->prepare("
        UPDATE shopee_config 
        SET shop_id = ?, access_token = ?, refresh_token = ?, token_expires_at = ?, updated_at = NOW()
        WHERE is_active = 1
    ");
    $stmt->execute([$shopId, $accessToken, $refreshToken, $expiresAt]);

    // Log success
    $stmt = $conn->prepare("
        INSERT INTO shopee_sync_logs (event_type, source, status, created_at)
        VALUES ('token_refresh', ?, 'success', NOW())
    ");
    $stmt->execute(['OAuth authorization — Shop ID: ' . $shopId]);

    // Redirect to settings page with success
    header("Location: " . BASE_URL . "views/shopee/settings.php?auth=success&shop_id=" . $shopId);
    exit;

} catch (Exception $e) {
    die("Authorization error: " . $e->getMessage());
}
