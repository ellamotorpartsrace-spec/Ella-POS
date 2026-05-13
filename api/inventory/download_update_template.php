<?php
// api/inventory/download_update_template.php - Generate CSV with product data for mass updates
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    http_response_code(403);
    die("Permission Denied.");
}

// Get filter parameters
$brand_filter = $_GET['brand'] ?? '';
$search_filter = $_GET['search'] ?? '';
$batch_filter = $_GET['batch'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$db = new Database();
$conn = $db->getConnection();

// Build query with optional filters
$sql = "
    SELECT 
        p.product_id,
        v.variation_id,
        p.product_name,
        p.brand_name,
        v.variation_name,
        v.sku,
        v.barcode,
        v.unit_type,
        v.price_capital,
        v.price_retail,
        v.price_wholesale,
        v.price_dealer,
        COALESCE(i.quantity, 0) as current_stock,
        v.low_stock_threshold,
        v.status
    FROM product_variations v
    JOIN products p ON v.product_id = p.product_id
    LEFT JOIN inventory i ON v.variation_id = i.variation_id AND i.store_id = 1
    WHERE v.status = 'active'
";

$params = [];

if (!empty($batch_filter)) {
    $sql .= " AND v.variation_id IN (SELECT DISTINCT variation_id FROM stock_movements WHERE reference = ?)";
    $params[] = $batch_filter;
} elseif (!empty($date_from) || !empty($date_to)) {
    // Filter by date range if no specific batch
    $sql .= " AND v.variation_id IN (SELECT DISTINCT variation_id FROM stock_movements WHERE 1=1";
    if (!empty($date_from)) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $date_from;
    }
    if (!empty($date_to)) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $date_to;
    }
    $sql .= " AND type = 'stock_in')";
}

if (!empty($brand_filter)) {
    if (is_array($brand_filter)) {
        // multiple brands
        $placeholders = rtrim(str_repeat('?,', count($brand_filter)), ',');
        $sql .= " AND p.brand_name IN ($placeholders)";
        foreach ($brand_filter as $b) {
            $params[] = $b;
        }
    } else {
        $sql .= " AND p.brand_name LIKE ?";
        $params[] = "%$brand_filter%";
    }
}

if (!empty($search_filter)) {
    // split by comma
    $search_terms = array_map('trim', explode(',', $search_filter));
    $search_terms = array_filter($search_terms, 'strlen');

    if (!empty($search_terms)) {
        $search_conditions = [];
        foreach ($search_terms as $term) {
            $search_conditions[] = "(p.product_name LIKE ? OR v.sku LIKE ? OR v.barcode LIKE ?)";
            $params[] = "%$term%";
            $params[] = "%$term%";
            $params[] = "%$term%";
        }
        $sql .= " AND (" . implode(' OR ', $search_conditions) . ")";
    }
}

$sql .= " ORDER BY p.brand_name, p.product_name, v.variation_name";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
$brand_str = "";
if (!empty($brand_filter)) {
    if (is_array($brand_filter)) {
        $brand_str = count($brand_filter) > 2 ? "multiple_brands" : implode('_', $brand_filter);
    } else {
        $brand_str = $brand_filter;
    }
    $brand_str = preg_replace('/[^a-zA-Z0-9]/', '_', $brand_str) . "_";
}

$filename = "mass_update_" . $brand_str . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open output stream
$output = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Write header row
$headers = [
    'product_id',
    'variation_id',
    'product_name',
    'brand_name',
    'variation_name',
    'sku',
    'barcode',
    'unit_type',
    'price_capital',
    'price_retail',
    'price_wholesale',
    'price_dealer',
    'current_stock',
    'low_stock_threshold',
    'status'
];
fputcsv($output, $headers);

// Write product data
foreach ($products as $row) {
    fputcsv($output, [
        $row['product_id'],
        $row['variation_id'],
        $row['product_name'],
        $row['brand_name'],
        $row['variation_name'],
        $row['sku'],
        $row['barcode'],
        $row['unit_type'],
        $row['price_capital'],
        $row['price_retail'],
        $row['price_wholesale'],
        $row['price_dealer'],
        $row['current_stock'],
        $row['low_stock_threshold'],
        $row['status']
    ]);
}

fclose($output);
exit;
?>