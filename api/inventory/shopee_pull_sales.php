<?php
/**
 * api/inventory/shopee_pull_sales.php
 * Automated worker endpoint designed to be hit by a CRON job every 10 minutes.
 * Fetches "Completed" or "Shipped" orders from Shopee and automatically deducts stock
 * from the Ella POS online inventory (store_id = 2).
 */
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../classes/ShopeeAPI.php';

// Security: In a production environment, you might protect this script using an API key
// so it can only be triggered by your server/cron.
$cli_or_auth = (php_sapi_name() === 'cli' || isset($_GET['cron_key'])); // Simplified protection

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->query("SELECT * FROM api_platforms WHERE platform_name = 'shopee' AND is_active = 1");
    $shopee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shopee) {
        die(json_encode(['success' => false, 'message' => 'Shopee API not active or not configured.']));
    }

    $api = new ShopeeAPI($shopee['partner_id'], $shopee['partner_key'], true);

    // TODO: Step 1 - Check if access_token is expired (token_expiry). If yes, call $api->refreshToken() 
    // and update database before continuing.

    // TODO: Step 2 - Call Shopee Order API to get recent orders (e.g., using `create_time_from` and `create_time_to`).
    // Example: $api->get('/api/v2/order/get_order_list', ['time_range_field' => 'create_time', ...], $shopee['access_token'], $shopee['shop_id']);

    // TODO: Step 3 - Loop through orders, fetch details.

    // TODO: Step 4 - For each item in the order, map it to `online_platform_links.online_variation_id`.
    
    // TODO: Step 5 - Deduct the exact quantity from `inventory` where `variation_id = X` and `store_id = 2`.
    // And insert a record into `stock_movements`.

    echo json_encode(['success' => true, 'message' => 'Shopee Pull Sales cron executed successfully (Skeleton Mode).']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Worker Error: ' . $e->getMessage()]);
}
