<?php
/**
 * api/inventory/process_bulk_platform_links.php
 * Handles the upload of bulk platform links via CSV, matching by Local SKU.
 */
declare(strict_types=1);
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/SyncHelper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireLogin();
if (!hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['admin', 'manager', 'stockman'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission Denied']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File upload failed.']);
    exit;
}

$fileTmpPath = $_FILES['file']['tmp_name'];
$fileName = $_FILES['file']['name'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

$rows = [];

if ($fileExtension === 'csv') {
    // Detect delimiter
    $fileHandleForDelimiter = fopen($fileTmpPath, 'r');
    $firstLine = fgets($fileHandleForDelimiter);
    fclose($fileHandleForDelimiter);
    
    $delimiter = ',';
    if (strpos($firstLine, ';') !== false) $delimiter = ';';
    if (strpos($firstLine, "\t") !== false) $delimiter = "\t";

    if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
        // Handle BOM
        $bom = fread($handle, 3);
        if ($bom !== b"\xEF\xBB\xBF") {
            rewind($handle);
        }
        while (($data = fgetcsv($handle, 10000, $delimiter)) !== FALSE) {
            $rows[] = $data;
        }
        fclose($handle);
    }
} else {
    // Unsupported format (no phpspreadsheet by default here)
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please upload a CSV file.']);
    exit;
}

if (count($rows) < 2) {
    echo json_encode(['success' => false, 'message' => 'File is empty or missing data rows.']);
    exit;
}

$headers = array_map('strtolower', array_map('trim', $rows[0]));

// Detect columns dynamically
$idx_sku = -1;
$idx_platform = -1;
$idx_var_id = -1;
$idx_prod_id = -1;
$idx_plat_sku = -1;

foreach ($headers as $index => $header) {
    if (strpos($header, 'local sku') !== false || $header === 'sku') $idx_sku = $index;
    if (strpos($header, 'platform') !== false && strpos($header, 'sku') === false && strpos($header, 'id') === false) $idx_platform = $index;
    if (strpos($header, 'variation id') !== false) $idx_var_id = $index;
    if (strpos($header, 'product id') !== false) $idx_prod_id = $index;
    if (strpos($header, 'platform sku') !== false) $idx_plat_sku = $index;
}

if ($idx_sku === -1 || $idx_var_id === -1) {
    echo json_encode(['success' => false, 'message' => 'Invalid template format. Could not find Local SKU or Variation ID columns.']);
    exit;
}

$results = [];
$successCount = 0;
$failCount = 0;

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Prepared statements
    $stmtFindVar = $conn->prepare("SELECT variation_id, variation_name FROM product_variations WHERE sku = ?");
    
    $stmtInsertLink = $conn->prepare("
        INSERT INTO online_platform_links 
        (variation_id, platform, online_product_id, online_variation_id, platform_sku, linked_by, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE 
            online_product_id = VALUES(online_product_id),
            online_variation_id = VALUES(online_variation_id),
            platform_sku = VALUES(platform_sku),
            is_active = 1,
            linked_by = VALUES(linked_by)
    ");

    $userId = $_SESSION['user_id'] ?? 0;

    $conn->beginTransaction();

    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        
        // Skip empty lines
        if (empty(array_filter($row))) continue;

        $sku = isset($row[$idx_sku]) ? trim($row[$idx_sku]) : '';
        $var_id_val = isset($row[$idx_var_id]) ? trim($row[$idx_var_id]) : '';
        
        // Allowed to be empty on row
        if (empty($sku) || empty($var_id_val)) {
            continue; // Ignore blank rows without failing
        }

        $platform_val = ($idx_platform !== -1 && isset($row[$idx_platform])) ? ucfirst(trim($row[$idx_platform])) : 'Shopee';
        if (empty($platform_val)) $platform_val = 'Shopee';

        $prod_id_val = ($idx_prod_id !== -1 && isset($row[$idx_prod_id])) ? trim($row[$idx_prod_id]) : '';
        $plat_sku_val = ($idx_plat_sku !== -1 && isset($row[$idx_plat_sku])) ? trim($row[$idx_plat_sku]) : '';

        // 1. Resolve Local Variation ID from SKU
        $stmtFindVar->execute([$sku]);
        $varResult = $stmtFindVar->fetch(PDO::FETCH_ASSOC);

        if (!$varResult) {
            $failCount++;
            $results[] = [
                'sku' => $sku,
                'status' => 'failed',
                'reason' => 'SKU not found in local inventory.'
            ];
            continue;
        }

        $internal_var_id = $varResult['variation_id'];

        // 2. Perform Link Insert/Update
        try {
            $stmtInsertLink->execute([
                $internal_var_id,
                $platform_val,
                $prod_id_val,
                $var_id_val,
                $plat_sku_val,
                $userId
            ]);

            // 3. Queue a stock sync immediately
            SyncHelper::queueStockUpdate($conn, (int)$internal_var_id);

            $successCount++;
            $results[] = [
                'sku' => $sku,
                'status' => 'success',
                'reason' => 'Linked to ' . $platform_val . ' (Var: ' . $var_id_val . ')'
            ];

        } catch (Exception $e) {
            $failCount++;
            $results[] = [
                'sku' => $sku,
                'status' => 'failed',
                'reason' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Bulk import complete. {$successCount} linked, {$failCount} failed.",
        'details' => $results,
        'summary' => [
            'success' => $successCount,
            'failed' => $failCount,
            'total' => $successCount + $failCount
        ]
    ]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
