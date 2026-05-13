<?php
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/ShopeeAPI.php';

requireLogin();
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die("Permission Denied");
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->query("SELECT partner_id, partner_key, is_test, is_active FROM api_platforms WHERE platform_name = 'shopee'");
    $shopee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shopee || empty($shopee['partner_id']) || empty($shopee['partner_key'])) {
        die("Shopee Partner ID and Key not configured. Please save them first.");
    }

    $is_test = (bool)$shopee['is_test'];
    $api = new ShopeeAPI($shopee['partner_id'], $shopee['partner_key'], $is_test);
    
    // Webhook/Callback URL points back to our own system
    // Using a dynamic or static base path depending on the host
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $redirect_url = $protocol . '://' . $host . rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\') . '/system/shopee_auth_callback.php';

    $authUrl = $api->getAuthUrl($redirect_url);
    
    header("Location: " . $authUrl);
    exit;

} catch (Exception $e) {
    die("Error initializing Shopee Auth: " . $e->getMessage());
}
