<?php
/**
 * api/shopee/auth.php — Generate Shopee OAuth authorization URL
 */
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/ShopeeApi.php';

requireLogin();

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Load active config
    $stmt = $conn->prepare("SELECT * FROM shopee_config WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        echo json_encode(['success' => false, 'error' => 'No Shopee credentials configured. Please save your Partner ID and Key first.']);
        exit;
    }

    $isTest = $config['environment'] === 'test';
    $shopee = new ShopeeAPI($config['partner_id'], $config['partner_key'], $isTest);

    // Detect protocol (handle ngrok forwarding)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (strpos($_SERVER['HTTP_HOST'] ?? '', 'ngrok') !== false);
    
    $protocol = $isHttps ? "https://" : "http://";
    
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Shopee is extremely strict and may require EXACT string match with the console.
    // So we just send the base domain (e.g., https://...ngrok.dev).
    // The root index.php will catch the callback and forward it to /ella-pos/...
    $callbackUrl = $protocol . $host;

    $authUrl = $shopee->getAuthUrl($callbackUrl);

    echo json_encode([
        'success'     => true,
        'auth_url'    => $authUrl,
        'environment' => $config['environment'],
        'callback'    => $callbackUrl,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
