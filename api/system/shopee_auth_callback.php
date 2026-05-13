<?php
// api/system/shopee_auth_callback.php
// Receives the ?code= and ?shop_id= from Shopee after the user logs in and authorizes the app.
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

if (!isset($_GET['code']) || !isset($_GET['shop_id'])) {
    die("Shopee Auth Error: Missing authorization code or shop_id in callback. You may have cancelled the authorization.");
}

$code = trim($_GET['code']);
$shop_id = trim($_GET['shop_id']);

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->query("SELECT partner_id, partner_key, is_test FROM api_platforms WHERE platform_name = 'shopee'");
    $shopee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shopee) {
        die("System error: Shopee integration not found in database.");
    }

    $is_test = (bool)$shopee['is_test'];
    $api = new ShopeeAPI($shopee['partner_id'], $shopee['partner_key'], $is_test);

    // Call Shopee's Token API to trade the Auth Code for Access and Refresh Tokens
    $response = $api->getAccessToken($code, $shop_id);

    if (isset($response['error']) && !empty($response['error'])) {
        die("Shopee API Error: " . htmlspecialchars($response['message'] ?? $response['error']));
    }

    $access_token = $response['access_token'];
    $refresh_token = $response['refresh_token'];
    $expire_in = (int) ($response['expire_in'] ?? 14400); // Usually 4 hours
    $token_expiry = time() + $expire_in - 300; // Subtract 5 minutes as safety buffer

    // Update database
    $stmtUpdate = $conn->prepare("
        UPDATE api_platforms 
        SET shop_id = ?, 
            access_token = ?, 
            refresh_token = ?, 
            token_expiry = ?, 
            is_active = 1,
            updated_at = NOW() 
        WHERE platform_name = 'shopee'
    ");
    $stmtUpdate->execute([$shop_id, $access_token, $refresh_token, $token_expiry]);

    // Redirect user back to the Integrations UI
    header("Location: " . BASE_URL . "views/system/integrations.php?success=shopee_linked");
    exit;

} catch (Exception $e) {
    die("System Error during callback: " . $e->getMessage());
}
