<?php
// api/inventory/export_movements.php - Export stock movements to CSV
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

// Get filters
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build Query (matching movements.php)
$sql = "
    SELECT 
        sm.movement_id,
        sm.type,
        sm.quantity,
        sm.previous_stock,
        sm.new_stock,
        sm.reference,
        sm.remarks,
        sm.created_at,
        pv.variation_name,
        pv.barcode,
        p.product_name,
        p.brand_name,
        u.full_name as created_by_name,
        sm.store_id
    FROM stock_movements sm
    JOIN product_variations pv ON sm.variation_id = pv.variation_id
    JOIN products p ON pv.product_id = p.product_id
    LEFT JOIN users u ON sm.created_by = u.id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (p.product_name LIKE ? OR p.brand_name LIKE ? OR pv.barcode LIKE ? OR sm.reference LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if (!empty($type_filter)) {
    $sql .= " AND sm.type = ?";
    $params[] = $type_filter;
}

if (!empty($date_from)) {
    $sql .= " AND DATE(sm.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $sql .= " AND DATE(sm.created_at) <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY sm.created_at DESC LIMIT 500";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Type labels for export
$type_labels = [
    'stock_in' => 'Stock In',
    'stock_out' => 'Stock Out',
    'sales' => 'Sales',
    'adjustment' => 'Adjustment',
    'return' => 'Return'
];

// Set headers for CSV download
$filename = 'stock_movements';
if (!empty($date_from) && !empty($date_to)) {
    $filename .= '_' . $date_from . '_to_' . $date_to;
} elseif (!empty($date_from)) {
    $filename .= '_from_' . $date_from;
} elseif (!empty($date_to)) {
    $filename .= '_to_' . $date_to;
} else {
    $filename .= '_' . date('Y-m-d');
}
$filename .= '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV Headers
fputcsv($output, [
    'Date & Time',
    'Product',
    'Brand',
    'Variation',
    'Barcode',
    'Type',
    'Quantity Change',
    'Previous Stock',
    'New Stock',
    'Reference',
    'Remarks',
    'Created By',
    'Store'
]);

// CSV Data
foreach ($movements as $m) {
    $qty = (float)$m['quantity'];
    $qty_display = ($qty > 0 ? '+' : '') . $qty;
    $type_label = $type_labels[$m['type']] ?? $m['type'];
    $store_label = ($m['store_id'] == 2) ? 'Online Shop' : 'Physical Store';

    fputcsv($output, [
        $m['created_at'],
        $m['product_name'],
        $m['brand_name'],
        $m['variation_name'],
        $m['barcode'],
        $type_label,
        $qty_display,
        $m['previous_stock'],
        $m['new_stock'],
        $m['reference'] ?? '',
        $m['remarks'] ?? '',
        $m['created_by_name'] ?? 'System',
        $store_label
    ]);
}

fclose($output);
exit;
