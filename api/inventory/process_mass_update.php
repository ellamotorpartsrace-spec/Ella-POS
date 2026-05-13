<?php
// api/inventory/process_mass_update.php - Process CSV for mass updating products
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// ── Debug Log Setup ──────────────────────────────────────────────────────────
$log_dir = __DIR__ . '/../../logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0777, true);
}
$log_file = $log_dir . '/mass_update_' . date('Y-m-d_His') . '.log';

function mass_log(string $message): void
{
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

mass_log('=== MASS UPDATE STARTED ===');

// Security Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../views/inventory/mass_update.php");
    exit;
}

requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    $_SESSION['error'] = 'Permission Denied';
    header("Location: ../../views/inventory/mass_update.php");
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    header("Location: ../../views/inventory/mass_update.php?error=" . urlencode("No file uploaded or upload error occurred"));
    exit;
}

$file_tmp = $_FILES['csv_file']['tmp_name'];
$file_name = $_FILES['csv_file']['name'];
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

// Process Excel files (.xlsx, .xls) - Convert to array
if (in_array($file_ext, ['xlsx', 'xls'])) {
    $excelClass = 'PhpOffice\\PhpSpreadsheet\\IOFactory';

    if (!class_exists($excelClass)) {
        header("Location: ../../views/inventory/mass_update.php?error=" . urlencode("Excel support not available. Please convert to CSV format."));
        exit;
    }

    try {
        $spreadsheet = $excelClass::load($file_tmp);
        $worksheet = $spreadsheet->getActiveSheet();
        $data = $worksheet->toArray();
    } catch (Exception $e) {
        header("Location: ../../views/inventory/mass_update.php?error=" . urlencode("Error reading Excel file: " . $e->getMessage()));
        exit;
    }
} else {
    // Detect delimiter (comma or semicolon)
    $delimiter = ',';
    $handle_peek = fopen($file_tmp, 'r');
    if ($handle_peek) {
        $first_line = fgets($handle_peek);
        if ($first_line && substr_count($first_line, ';') > substr_count($first_line, ',')) {
            $delimiter = ';';
        }
        fclose($handle_peek);
    }

    // Process CSV file
    $data = [];
    if (($handle = fopen($file_tmp, 'r')) !== false) {
        while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
            $data[] = $row;
        }
        fclose($handle);
    } else {
        header("Location: ../../views/inventory/mass_update.php?error=" . urlencode("Could not open CSV file"));
        exit;
    }
}

// Validate data
if (empty($data) || count($data) < 2) {
    header("Location: ../../views/inventory/mass_update.php?error=" . urlencode("File is empty or has no data rows"));
    exit;
}

// Extract headers (first row)
$headers = $data[0];

// Clean ALL headers
foreach ($headers as $key => $value) {
    $clean_value = preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/', '', $value); // Remove BOM
    $headers[$key] = trim(strtolower($clean_value));
}

mass_log('CSV Headers detected: [' . implode(', ', $headers) . ']');
mass_log('Total data rows (excluding header): ' . (count($data) - 1));

// Required: must have variation_id OR sku OR barcode for matching
if (!in_array('variation_id', $headers) && !in_array('sku', $headers) && !in_array('barcode', $headers)) {
    mass_log('ERROR: Missing required identifier column (variation_id/sku/barcode)');
    header("Location: ../../views/inventory/mass_update.php?error=" . urlencode("Missing required column: 'variation_id', 'sku', or 'barcode' is needed to identify products."));
    exit;
}

// Get column indices
$col_indexes = array_flip($headers);

// Define updatable fields
$updatable_variation_fields = ['price_capital', 'price_retail', 'price_wholesale', 'price_dealer', 'low_stock_threshold', 'status', 'variation_name', 'unit_type', 'sku', 'barcode'];
$updatable_product_fields = ['product_name', 'brand_name'];

// Initialize counters
$success_count = 0;
$skip_count = 0;
$not_found_count = 0;
$error_count = 0;
$warnings = [];

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Process each row (skip header)
    for ($i = 1; $i < count($data); $i++) {
        $row = $data[$i];

        // Skip empty rows
        if (empty(array_filter($row)))
            continue;
        // Skip comment rows
        if (isset($row[0]) && strpos(trim($row[0]), '#') === 0)
            continue;

        try {
            // Get identifiers (priority: variation_id > sku > barcode)
            $variation_id = isset($col_indexes['variation_id']) ? trim($row[$col_indexes['variation_id']] ?? '') : '';
            $sku = isset($col_indexes['sku']) ? trim($row[$col_indexes['sku']] ?? '') : '';
            $barcode = isset($col_indexes['barcode']) ? trim($row[$col_indexes['barcode']] ?? '') : '';

            mass_log("--- Row " . ($i + 1) . " ---");
            mass_log("  Raw values => variation_id='$variation_id', sku='$sku', barcode='$barcode'");

            // Must have at least one identifier
            if (empty($variation_id) && empty($sku) && empty($barcode)) {
                mass_log("  SKIPPED: No identifier provided");
                $warnings[] = "Row " . ($i + 1) . ": No variation_id, SKU, or Barcode provided, skipped.";
                $skip_count++;
                continue;
            }

            // Find the product variation - track which identifier was used for matching
            $find_sql = "SELECT v.variation_id, v.product_id, v.sku AS current_sku, v.barcode AS current_barcode,
                                v.price_capital, v.price_retail, v.price_wholesale, v.price_dealer, 
                                p.product_name 
                         FROM product_variations v 
                         JOIN products p ON v.product_id = p.product_id 
                         WHERE v.status != 'deleted'";
            $find_params = [];
            $matched_by = ''; // Track which field was used to identify the row

            if (!empty($variation_id)) {
                $find_sql .= " AND v.variation_id = ?";
                $find_params[] = $variation_id;
                $matched_by = 'variation_id';
            } elseif (!empty($sku)) {
                $find_sql .= " AND v.sku = ?";
                $find_params[] = $sku;
                $matched_by = 'sku';
            } elseif (!empty($barcode)) {
                $find_sql .= " AND v.barcode = ?";
                $find_params[] = $barcode;
                $matched_by = 'barcode';
            }

            mass_log("  Matching by: $matched_by");

            $stmtFind = $conn->prepare($find_sql);
            $stmtFind->execute($find_params);
            $existing = $stmtFind->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                $identifier = !empty($variation_id) ? "ID: $variation_id" : (!empty($sku) ? "SKU: $sku" : "Barcode: $barcode");
                mass_log("  NOT FOUND: Product ($identifier) does not exist or is deleted");
                $warnings[] = "Row " . ($i + 1) . ": Product not found ($identifier), skipped.";
                $not_found_count++;
                continue;
            }

            $var_id = $existing['variation_id'];
            $prod_id = $existing['product_id'];
            mass_log("  FOUND: variation_id=$var_id, product_id=$prod_id, product='" . $existing['product_name'] . "'");

            // Build dynamic update query for variations
            $var_update_parts = [];
            $var_update_params = [];

            // Price fields (with history tracking)
            $new_capital = null;
            $new_retail = null;
            $new_wholesale = null;
            $new_dealer = null;

            if (isset($col_indexes['price_capital']) && trim($row[$col_indexes['price_capital']] ?? '') !== '') {
                $new_capital = floatval($row[$col_indexes['price_capital']]);
                $var_update_parts[] = "price_capital = ?";
                $var_update_params[] = $new_capital;
            }

            if (isset($col_indexes['price_retail']) && trim($row[$col_indexes['price_retail']] ?? '') !== '') {
                $new_retail = floatval($row[$col_indexes['price_retail']]);
                $var_update_parts[] = "price_retail = ?";
                $var_update_params[] = $new_retail;
            }

            if (isset($col_indexes['price_wholesale']) && trim($row[$col_indexes['price_wholesale']] ?? '') !== '') {
                $new_wholesale = floatval($row[$col_indexes['price_wholesale']]);
                $var_update_parts[] = "price_wholesale = ?";
                $var_update_params[] = $new_wholesale;
            }

            if (isset($col_indexes['price_dealer']) && trim($row[$col_indexes['price_dealer']] ?? '') !== '') {
                $new_dealer = floatval($row[$col_indexes['price_dealer']]);
                $var_update_parts[] = "price_dealer = ?";
                $var_update_params[] = $new_dealer;
            }

            if (isset($col_indexes['low_stock_threshold']) && trim($row[$col_indexes['low_stock_threshold']] ?? '') !== '') {
                $var_update_parts[] = "low_stock_threshold = ?";
                $var_update_params[] = intval($row[$col_indexes['low_stock_threshold']]);
            }

            if (isset($col_indexes['status']) && trim($row[$col_indexes['status']] ?? '') !== '') {
                $status_val = strtolower(trim($row[$col_indexes['status']]));
                if (in_array($status_val, ['active', 'inactive'])) {
                    $var_update_parts[] = "status = ?";
                    $var_update_params[] = $status_val;
                }
            }

            if (isset($col_indexes['variation_name']) && trim($row[$col_indexes['variation_name']] ?? '') !== '') {
                $var_update_parts[] = "variation_name = ?";
                $var_update_params[] = trim($row[$col_indexes['variation_name']]);
            }

            if (isset($col_indexes['unit_type']) && trim($row[$col_indexes['unit_type']] ?? '') !== '') {
                $var_update_parts[] = "unit_type = ?";
                $var_update_params[] = trim($row[$col_indexes['unit_type']]);
            }

            // Update SKU — only if SKU was NOT used as the matching identifier
            if ($matched_by !== 'sku' && isset($col_indexes['sku']) && trim($row[$col_indexes['sku']] ?? '') !== '') {
                $new_sku = trim($row[$col_indexes['sku']]);
                // Only update if value actually changed
                if ($new_sku !== ($existing['current_sku'] ?? '')) {
                    $var_update_parts[] = "sku = ?";
                    $var_update_params[] = $new_sku ?: null;
                    mass_log("  SKU update queued: '" . ($existing['current_sku'] ?? '') . "' => '$new_sku'");
                } else {
                    mass_log("  SKU unchanged: '$new_sku' (same as current)");
                }
            } elseif ($matched_by === 'sku') {
                mass_log("  SKU not updated: used as matching identifier");
            }

            // Update barcode — only if barcode was NOT used as the matching identifier
            if ($matched_by !== 'barcode' && isset($col_indexes['barcode']) && trim($row[$col_indexes['barcode']] ?? '') !== '') {
                $new_barcode = trim($row[$col_indexes['barcode']]);
                // Only update if value actually changed
                if ($new_barcode !== ($existing['current_barcode'] ?? '')) {
                    $var_update_parts[] = "barcode = ?";
                    $var_update_params[] = $new_barcode ?: null;
                    mass_log("  Barcode update queued: '" . ($existing['current_barcode'] ?? '') . "' => '$new_barcode'");
                } else {
                    mass_log("  Barcode unchanged: '$new_barcode' (same as current)");
                }
            } elseif ($matched_by === 'barcode') {
                mass_log("  Barcode not updated: used as matching identifier");
            }

            // Build update for product table
            $prod_update_parts = [];
            $prod_update_params = [];

            if (isset($col_indexes['product_name']) && trim($row[$col_indexes['product_name']] ?? '') !== '') {
                $prod_update_parts[] = "product_name = ?";
                $prod_update_params[] = trim($row[$col_indexes['product_name']]);
            }

            if (isset($col_indexes['brand_name']) && trim($row[$col_indexes['brand_name']] ?? '') !== '') {
                $prod_update_parts[] = "brand_name = ?";
                $prod_update_params[] = trim($row[$col_indexes['brand_name']]);
            }

            // Skip if nothing to update
            if (empty($var_update_parts) && empty($prod_update_parts)) {
                mass_log("  SKIPPED: No fields changed for this row");
                $warnings[] = "Row " . ($i + 1) . ": No values to update, skipped.";
                $skip_count++;
                continue;
            }

            mass_log("  Fields to update: [" . implode(', ', $var_update_parts) . "] " . (!empty($prod_update_parts) ? '+ [' . implode(', ', $prod_update_parts) . ']' : ''));

            // Start transaction
            $conn->beginTransaction();

            // Log price history if any price changed
            if (
                ($new_capital !== null && $new_capital != floatval($existing['price_capital'])) ||
                ($new_retail !== null && $new_retail != floatval($existing['price_retail'])) ||
                ($new_wholesale !== null && $new_wholesale != floatval($existing['price_wholesale'])) ||
                ($new_dealer !== null && $new_dealer != floatval($existing['price_dealer']))
            ) {
                $sqlHistory = "INSERT INTO product_price_history 
                    (variation_id, user_id, 
                     old_capital, new_capital, 
                     old_retail, new_retail, 
                     old_wholesale, new_wholesale, 
                     old_dealer, new_dealer) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmtHist = $conn->prepare($sqlHistory);
                $stmtHist->execute([
                    $var_id,
                    $_SESSION['user_id'] ?? 1,
                    $existing['price_capital'],
                    $new_capital ?? $existing['price_capital'],
                    $existing['price_retail'],
                    $new_retail ?? $existing['price_retail'],
                    $existing['price_wholesale'],
                    $new_wholesale ?? $existing['price_wholesale'],
                    $existing['price_dealer'],
                    $new_dealer ?? $existing['price_dealer']
                ]);
            }

            // Execute variation update
            if (!empty($var_update_parts)) {
                $var_update_params[] = $var_id;
                $var_sql = "UPDATE product_variations SET " . implode(', ', $var_update_parts) . " WHERE variation_id = ?";
                $stmtVar = $conn->prepare($var_sql);
                $stmtVar->execute($var_update_params);
            }

            // Execute product update
            if (!empty($prod_update_parts)) {
                $prod_update_params[] = $prod_id;
                $prod_sql = "UPDATE products SET " . implode(', ', $prod_update_parts) . " WHERE product_id = ?";
                $stmtProd = $conn->prepare($prod_sql);
                $stmtProd->execute($prod_update_params);
            }

            // Handle Stock Update
            if (isset($col_indexes['current_stock']) && trim($row[$col_indexes['current_stock']] ?? '') !== '') {
                $new_stock = intval($row[$col_indexes['current_stock']]);

                // Get current stock from inventory
                $stmtStock = $conn->prepare("SELECT COALESCE(quantity, 0) as quantity FROM inventory WHERE variation_id = ? AND store_id = 1");
                $stmtStock->execute([$var_id]);
                $stockData = $stmtStock->fetch(PDO::FETCH_ASSOC);
                $currentStock = $stockData ? intval($stockData['quantity']) : 0;

                $stock_diff = $new_stock - $currentStock;

                // Only process if there's a change
                if ($stock_diff != 0) {
                    // Update or insert inventory
                    $stmtInv = $conn->prepare("
                        INSERT INTO inventory (variation_id, quantity, store_id) 
                        VALUES (?, ?, 1)
                        ON DUPLICATE KEY UPDATE quantity = ?
                    ");
                    $stmtInv->execute([$var_id, $new_stock, $new_stock]);

                    // Record stock movement
                    $movement_type = $stock_diff > 0 ? 'stock_in' : 'adjustment';
                    $stmtMove = $conn->prepare("
                        INSERT INTO stock_movements 
                        (variation_id, type, quantity, previous_stock, new_stock, reference, remarks, created_by, store_id, capital_cost) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
                    ");
                    $stmtMove->execute([
                        $var_id,
                        $movement_type,
                        abs($stock_diff),
                        $currentStock,
                        $new_stock,
                        'MASS-UPDATE-' . date('YmdHis'),
                        'Mass Update: Stock adjustment via CSV',
                        $_SESSION['user_id'] ?? 1,
                        $new_capital ?? $existing['price_capital']
                    ]);
                }
            }

            $conn->commit();
            $success_count++;
            mass_log("  SUCCESS: Row " . ($i + 1) . " updated");

        } catch (Exception $e) {
            if ($conn->inTransaction())
                $conn->rollBack();
            mass_log("  ERROR: Row " . ($i + 1) . " - " . $e->getMessage());
            $warnings[] = "Row " . ($i + 1) . ": Error (" . $e->getMessage() . ")";
            $error_count++;
        }
    }

    // Results
    $msg = "Update Complete: $success_count products updated";
    if ($not_found_count > 0)
        $msg .= ", $not_found_count not found";
    if ($skip_count > 0)
        $msg .= ", $skip_count skipped";
    if ($error_count > 0)
        $msg .= ", $error_count errors";

    mass_log('=== MASS UPDATE FINISHED ===');
    mass_log("Results: $success_count updated, $not_found_count not found, $skip_count skipped, $error_count errors");
    mass_log('Log file: ' . $log_file);

    $url = "../../views/inventory/mass_update.php?success=" . urlencode($msg);
    if (!empty($warnings))
        $url .= "&warnings=" . urlencode(json_encode($warnings));
    $url .= "&log=" . urlencode(basename($log_file));
    header("Location: $url");

} catch (Exception $e) {
    mass_log('FATAL ERROR: ' . $e->getMessage());
    header("Location: ../../views/inventory/mass_update.php?error=" . urlencode("Fatal Error: " . $e->getMessage()));
}
?>