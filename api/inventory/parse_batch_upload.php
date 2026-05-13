<?php
// api/inventory/parse_batch_upload.php - Parse CSV/Excel for preview
declare(strict_types=1);

header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission Denied']);
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file_tmp = $_FILES['csv_file']['tmp_name'];
$file_name = $_FILES['csv_file']['name'];
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

$data = [];

// Process Excel
if (in_array($file_ext, ['xlsx', 'xls'])) {
    $excelClass = 'PhpOffice\PhpSpreadsheet\IOFactory';
    if (!class_exists($excelClass)) {
        echo json_encode(['success' => false, 'error' => 'Excel support not available']);
        exit;
    }
    try {
        $spreadsheet = $excelClass::load($file_tmp);
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $worksheet->toArray();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Excel error: ' . $e->getMessage()]);
        exit;
    }
} else {
    // Process CSV
    $delimiter = ',';
    $handle_peek = fopen($file_tmp, 'r');
    if ($handle_peek) {
        $first_line = fgets($handle_peek);
        if ($first_line && substr_count($first_line, ';') > substr_count($first_line, ',')) {
            $delimiter = ';';
        }
        fclose($handle_peek);
    }
    if (($handle = fopen($file_tmp, 'r')) !== false) {
        while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
            $data[] = $row;
        }
        fclose($handle);
    }
}

if (empty($data) || count($data) < 2) {
    echo json_encode(['success' => false, 'error' => 'File is empty or has no data']);
    exit;
}

// Clean Headers
$headers = $data[0];
foreach ($headers as $key => $value) {
    $clean_value = preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/', '', $value);
    $headers[$key] = trim(strtolower((string) $clean_value));
}

// Map Data
$col_indexes = array_flip($headers);
$rows = [];

// Get Categories for validation
$db = new Database();
$conn = $db->getConnection();
$catMap = [];
$stmtCats = $conn->query("SELECT category_id, category_name FROM categories");
while ($rowCat = $stmtCats->fetch(PDO::FETCH_ASSOC)) {
    $catMap[strtolower(trim($rowCat['category_name']))] = $rowCat['category_id'];
}

for ($i = 1; $i < count($data); $i++) {
    $row = $data[$i];
    if (empty(array_filter($row)))
        continue;

    $mapped = [];
    foreach ($col_indexes as $head => $idx) {
        $mapped[$head] = trim((string) ($row[$idx] ?? ''));
    }

    // Basic Validation / Enrichment
    $warnings = [];
    if (empty($mapped['product_name'] ?? ''))
        $warnings[] = "Missing product name";

    $cat_name = $mapped['category_name'] ?? '';
    $cat_id = $mapped['category_id'] ?? '';
    if (empty($cat_id) && empty($cat_name)) {
        $warnings[] = "Missing category";
    } elseif (!empty($cat_name) && !isset($catMap[strtolower($cat_name)])) {
        // We'll auto-create this anyway, but good to know
        $mapped['is_new_category'] = true;
    }

    $mapped['_warnings'] = $warnings;
    $rows[] = $mapped;
}

echo json_encode([
    'success' => true,
    'headers' => $headers,
    'rows' => $rows,
    'rowCount' => count($rows)
]);
