<?php
// api/inventory/export_products.php - Export complete product inventory to CSV
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Get search filter
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

// Build Query (matching index.php)
$sql = "
    SELECT 
        v.variation_id, 
        v.variation_name, 
        v.sku, 
        v.barcode,
        v.unit_type,
        v.price_capital, 
        v.price_retail, 
        v.price_wholesale,
        v.price_dealer,
        v.status, 
        v.low_stock_threshold,
        p.product_id,
        p.product_name, 
        p.brand_name,
        (COALESCE(i_phys.quantity, 0) + COALESCE(i_online.quantity, 0)) as current_stock
    FROM product_variations v
    JOIN products p ON v.product_id = p.product_id
    LEFT JOIN inventory i_phys ON v.variation_id = i_phys.variation_id AND i_phys.store_id = 1
    LEFT JOIN inventory i_online ON v.variation_id = i_online.variation_id AND i_online.store_id = 2
    WHERE v.status = 'active'
";

$params = [];
if (!empty($search)) {
    $sql .= " AND (p.product_name LIKE ? OR p.brand_name LIKE ? OR v.sku LIKE ? OR v.barcode LIKE ?)";
    $term = "%$search%";
    $params = [$term, $term, $term, $term];
}

if ($filter === 'low_stock') {
    $sql .= " AND COALESCE(i_phys.quantity, 0) <= v.low_stock_threshold";
}

$sql .= " ORDER BY p.product_name ASC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
$filename = 'inventory_products_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV Headers
fputcsv($output, [
    'Product ID',
    'Variation ID',
    'Product Name',
    'Brand',
    'Variation',
    'SKU',
    'Barcode',
    'Unit Type',
    'Capital Price',
    'Retail Price',
    'Wholesale Price',
    'Dealer Price',
    'Current Stock',
    'Low Stock Threshold',
    'Stock Value (Cost)',
    'Stock Value (Retail)',
    'Status'
]);

// CSV Data
foreach ($products as $p) {
    $stock_value_cost = $p['price_capital'] * $p['current_stock'];
    $stock_value_retail = $p['price_retail'] * $p['current_stock'];

    // Determine stock status
    $stock_status = 'OK';
    if ($p['current_stock'] == 0) {
        $stock_status = 'Out of Stock';
    } elseif ($p['current_stock'] <= $p['low_stock_threshold']) {
        $stock_status = 'Low Stock';
    }

    fputcsv($output, [
        $p['product_id'],
        $p['variation_id'],
        $p['product_name'],
        $p['brand_name'],
        $p['variation_name'],
        $p['sku'] ?? '',
        $p['barcode'] ?? '',
        $p['unit_type'] ?? 'pcs',
        number_format($p['price_capital'], 2),
        number_format($p['price_retail'], 2),
        number_format($p['price_wholesale'] ?? 0, 2),
        number_format($p['price_dealer'] ?? 0, 2),
        $p['current_stock'],
        $p['low_stock_threshold'],
        number_format($stock_value_cost, 2),
        number_format($stock_value_retail, 2),
        $stock_status
    ]);
}

fclose($output);
exit;
