<?php
/**
 * api/external/sync_catalog.php
 * Public API for syncing product catalog with external website (React/Node.js)
 * 
 * Authentication: Requires 'x-api-key' header matching the 'partner_key' for 'website' platform.
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// Allow CORS for Vercel deployment (the user's website)
// In production, they should replace * with their actual domain
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, x-api-key");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once '../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Get the Website API Key from database
    $stmtKey = $conn->prepare("SELECT partner_key, is_active FROM api_platforms WHERE platform_name = 'website' LIMIT 1");
    $stmtKey->execute();
    $platform = $stmtKey->fetch();

    if (!$platform || !$platform['is_active']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'API Integration is not active or not configured.']);
        exit;
    }

    // 2. Validate API Key
    $providedKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    if (empty($providedKey) || $providedKey !== $platform['partner_key']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid or missing API Key.']);
        exit;
    }

    // 3. Fetch Products, Variations, Prices, and Stocks
    $sql = "
        SELECT 
            p.product_id,
            p.product_name,
            p.brand_name,
            p.description,
            p.image_path,
            c.category_name,
            v.variation_id,
            v.variation_name,
            v.sku,
            v.barcode,
            v.price_retail as price,
            v.status,
            COALESCE(i_phys.quantity, 0) as physical_stock,
            COALESCE(i_online.quantity, 0) as online_stock,
            (COALESCE(i_phys.quantity, 0) + COALESCE(i_online.quantity, 0)) as current_stock
        FROM product_variations v
        JOIN products p ON v.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN inventory i_phys ON v.variation_id = i_phys.variation_id AND i_phys.store_id = 1
        LEFT JOIN inventory i_online ON v.variation_id = i_online.variation_id AND i_online.store_id = 2
        WHERE v.status = 'active'
        ORDER BY p.product_name ASC, v.variation_name ASC
    ";

    $stmt = $conn->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = dirname(dirname(dirname($_SERVER['SCRIPT_NAME'])));
    $scriptDir = str_replace('\\', '/', $scriptDir);
    if ($scriptDir === '/' || $scriptDir === '\\') {
        $scriptDir = '';
    }
    $baseUrl = $protocol . "://" . $host . $scriptDir;

    // Format the response for the website
    $catalog = array_map(function ($item) use ($baseUrl) {
        return [
            'id' => $item['variation_id'],
            'product_id' => $item['product_id'],
            'name' => $item['product_name'],
            'variation' => $item['variation_name'],
            'sku' => $item['sku'],
            'barcode' => $item['barcode'],
            'brand' => $item['brand_name'],
            'category' => $item['category_name'],
            'description' => $item['description'],
            'price' => (float) $item['price'],
            'stock' => (int) $item['current_stock'],
            'physical_stock' => (int) $item['physical_stock'],
            'online_stock' => (int) $item['online_stock'],
            'image' => $item['image_path'] ? $baseUrl . '/assets/img/products/' . basename($item['image_path']) : null
        ];
    }, $products);

    echo json_encode([
        'success' => true,
        'count' => count($catalog),
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $catalog
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error',
        'error' => $e->getMessage()
    ]);
}
