<?php
// api/inventory/download_template.php - Generate CSV template for batch upload
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    http_response_code(403);
    die("Permission Denied.");
}

$db = new Database();
$conn = $db->getConnection();

// Fetch categories for reference
$cats = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC")->fetchAll();

// Set headers for CSV download
$filename = 'product_import_template_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV Headers (Column names that the upload script expects)
fputcsv($output, [
    'product_name',
    'brand_name',
    'category_id',     // Optional if category_name is provided
    'category_name',   // Optional if category_id is provided
    'description',
    'variation_name',
    'unit_type',
    'sku',
    'barcode',
    'price_capital',
    'price_retail',
    'price_wholesale',
    'price_dealer',
    'initial_stock',
    'low_stock_threshold'
]);

// Add 3 example rows to show format
fputcsv($output, [
    'Michelin City Grip Front',
    'Michelin',
    $cats[0]['category_id'] ?? '1',
    $cats[0]['category_name'] ?? 'Tires',
    'High performance tire for city riding',
    '110/70-17',
    'pc',
    'MICH-110-70-17',
    '1234567890123',
    '2500.00',
    '3500.00',
    '3200.00',
    '2800.00',
    '10',
    '5'
]);

fputcsv($output, [
    'Castrol Engine Oil 4T',
    'Castrol',
    '',  // ID can be empty if name is provided
    'Oils & Lubricants',
    'Fully synthetic 4-stroke engine oil',
    '1 Liter',
    'bottle',
    'CAST-4T-1L',
    '9876543210987',
    '450.00',
    '650.00',
    '600.00',
    '550.00',
    '50',
    '10'
]);

fputcsv($output, [
    'Brake Pad Set',
    'Yamaha',
    '',
    'Brakes', // Will auto-create 'Brakes' category if not exists
    'Genuine Yamaha brake pad set',
    'Front',
    'set',
    'YAM-BRK-FRONT',
    '',
    '380.00',
    '550.00',
    '',
    '',
    '20',
    '3'
]);

// Add a blank row
fputcsv($output, []);

// Add comment rows with instructions
fputcsv($output, ['## INSTRUCTIONS - DELETE THESE ROWS BEFORE UPLOADING ##']);
fputcsv($output, []);
fputcsv($output, ['REQUIRED FIELDS:', 'product_name, AND (category_id OR category_name), AND at least one price']);
fputcsv($output, []);
fputcsv($output, ['SMART CATEGORIES:', 'You can use "category_name" instead of ID. If the category does not exist, it will be created automatically!']);
fputcsv($output, []);
fputcsv($output, ['AVAILABLE UNIT TYPES:', 'pc, set, box, pair, bottle, kit, roll, liter, gallon, pack']);
fputcsv($output, []);
fputcsv($output, ['DEFAULT VALUES:', 'unit_type=pc, initial_stock=0, low_stock_threshold=5']);
fputcsv($output, []);
fputcsv($output, ['EXISTING CATEGORY IDs:']);

// Add category reference
foreach ($cats as $cat) {
    fputcsv($output, [
        'ID: ' . $cat['category_id'],
        'Name: ' . $cat['category_name']
    ]);
}

fclose($output);
exit;
?>