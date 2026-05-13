<?php
// api/inventory/validate_csv.php - Validate and show CSV structure
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    die("No file uploaded");
}

$file_tmp = $_FILES['csv_file']['tmp_name'];

// Read the file
$data = [];
if (($handle = fopen($file_tmp, 'r')) !== false) {
    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
        $data[] = $row;
    }
    fclose($handle);
}

header('Content-Type: application/json');

if (empty($data)) {
    echo json_encode([
        'success' => false,
        'error' => 'CSV file is empty'
    ]);
    exit;
}

// Process headers
$headers_raw = $data[0];
$headers_processed = array_map('trim', $headers_raw);

// Check for BOM
$has_bom = false;
if (!empty($headers_processed[0])) {
    $first_header = $headers_processed[0];
    if (strpos($first_header, "\xEF\xBB\xBF") !== false) {
        $has_bom = true;
    }
    // Remove BOM
    $headers_processed[0] = str_replace("\xEF\xBB\xBF", '', $headers_processed[0]);
    $headers_processed[0] = str_replace("\xFF\xFE", '', $headers_processed[0]);
    $headers_processed[0] = str_replace("\xFE\xFF", '', $headers_processed[0]);
    $headers_processed[0] = preg_replace('/^[\x00-\x1F\x7F]+/', '', $headers_processed[0]);
    $headers_processed[0] = trim($headers_processed[0]);
}

$headers_lower = array_map('strtolower', $headers_processed);

$required = ['product_name', 'category_id'];
$missing = [];
foreach ($required as $field) {
    if (!in_array($field, $headers_lower)) {
        $missing[] = $field;
    }
}

echo json_encode([
    'success' => empty($missing),
    'total_rows' => count($data),
    'data_rows' => count($data) - 1,
    'headers_raw' => $headers_raw,
    'headers_processed' => $headers_processed,
    'headers_lower' => $headers_lower,
    'has_bom' => $has_bom,
    'required_fields' => $required,
    'missing_fields' => $missing,
    'message' => empty($missing)
        ? 'CSV structure is valid!'
        : 'Missing required columns: ' . implode(', ', $missing)
]);
?>