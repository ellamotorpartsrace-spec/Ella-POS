<?php
// api/inventory/process_batch_upload.php - Process CSV/Excel batch product upload
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Security Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../views/inventory/batch_upload.php");
    exit;
}

requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    $_SESSION['error'] = 'Permission Denied';
    header("Location: ../../views/inventory/batch_upload.php");
    exit;
}

// Check if file was uploaded OR if JSON data was provided
$json_payload = $_POST['json_data'] ?? '';
$data = [];
$supplier_id = $_POST['supplier_id'] ?? '';
$skip_duplicates = isset($_POST['skip_duplicates']);
$update_existing = isset($_POST['update_existing']);

if (!empty($json_payload)) {
    // Process JSON data from Preview Step
    $decoded = json_decode($json_payload, true);
    if (!is_array($decoded)) {
        header("Location: ../../views/inventory/batch_upload.php?error=" . urlencode("Invalid JSON data provided"));
        exit;
    }

    // Adapt mapped objects to array-of-arrays format
    if (!empty($decoded)) {
        $headers = array_keys($decoded[0]);
        // Remove internal markers
        $headers = array_filter($headers, fn($h) => $h !== '_warnings' && $h !== 'is_new_category');
        $data[] = $headers;
        foreach ($decoded as $item) {
            $row = [];
            foreach ($headers as $h) {
                $row[] = $item[$h] ?? '';
            }
            $data[] = $row;
        }
    }
} elseif (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    // Process File Upload (Direct)
    $file_tmp = $_FILES['csv_file']['tmp_name'];
    $file_name = $_FILES['csv_file']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if (in_array($file_ext, ['xlsx', 'xls'])) {
        $excelClass = 'PhpOffice\\PhpSpreadsheet\\IOFactory';
        if (!class_exists($excelClass)) {
            header("Location: ../../views/inventory/batch_upload.php?error=" . urlencode("Excel support not available."));
            exit;
        }
        try {
            $spreadsheet = $excelClass::load($file_tmp);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();
        } catch (Exception $e) {
            header("Location: ../../views/inventory/batch_upload.php?error=" . urlencode("Error reading Excel: " . $e->getMessage()));
            exit;
        }
    } else {
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
} else {
    header("Location: ../../views/inventory/batch_upload.php?error=" . urlencode("No file uploaded or data provided"));
    exit;
}

// Validate data
if (empty($data) || count($data) < 2) {
    header("Location: ../../views/inventory/batch_upload.php?error=" . urlencode("No data to process"));
    exit;
}

// Extract headers (first row)
$headers = $data[0];
foreach ($headers as $key => $value) {
    $clean_value = preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/', '', $value);
    $headers[$key] = trim(strtolower((string) $clean_value));
}

if (!in_array('product_name', $headers)) {
    header("Location: ../../views/inventory/batch_upload.php?error=" . urlencode("Missing 'product_name' column."));
    exit;
}

$col_indexes = array_flip($headers);
$success_count = 0;
$skip_count = 0;
$error_count = 0;
$warnings = [];

try {
    $db = new Database();
    $conn = $db->getConnection();

    $supplier_name = '';
    if (!empty($supplier_id)) {
        $stmtSup = $conn->prepare("SELECT supplier_name FROM suppliers WHERE supplier_id = ?");
        $stmtSup->execute([$supplier_id]);
        $supplier_name = $stmtSup->fetchColumn() ?: '';
    }

    $catMap = [];
    $stmtCats = $conn->query("SELECT category_id, category_name FROM categories");
    while ($rowCat = $stmtCats->fetch(PDO::FETCH_ASSOC)) {
        $catMap[strtolower(trim($rowCat['category_name']))] = $rowCat['category_id'];
    }

    for ($i = 1; $i < count($data); $i++) {
        $row = $data[$i];
        if (empty(array_filter($row)))
            continue;

        try {
            $product_name = trim((string) ($row[$col_indexes['product_name']] ?? ''));
            $category_id = trim((string) ($row[$col_indexes['category_id']] ?? ''));
            $category_name_input = trim((string) ($row[$col_indexes['category_name']] ?? ''));

            if (empty($category_id) && !empty($category_name_input)) {
                $clean_cat_name = strtolower($category_name_input);
                if (isset($catMap[$clean_cat_name])) {
                    $category_id = $catMap[$clean_cat_name];
                } else {
                    $stmtNewCat = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
                    $stmtNewCat->execute([$category_name_input]);
                    $category_id = (string) $conn->lastInsertId();
                    $catMap[$clean_cat_name] = $category_id;
                }
            }

            if (empty($product_name) || empty($category_id)) {
                $skip_count++;
                continue;
            }

            $brand_name = trim((string) ($row[$col_indexes['brand_name']] ?? ''));
            $description = trim((string) ($row[$col_indexes['description']] ?? ''));
            $variation_name = trim((string) ($row[$col_indexes['variation_name']] ?? ''));
            $unit_type = trim((string) ($row[$col_indexes['unit_type']] ?? 'pc'));
            $sku = trim((string) ($row[$col_indexes['sku']] ?? ''));
            $barcode = trim((string) ($row[$col_indexes['barcode']] ?? ''));
            $price_capital = floatval($row[$col_indexes['price_capital']] ?? 0);
            $price_retail = floatval($row[$col_indexes['price_retail']] ?? 0);
            $price_wholesale = floatval($row[$col_indexes['price_wholesale']] ?? 0);
            $price_dealer = floatval($row[$col_indexes['price_dealer']] ?? 0);
            $initial_stock = intval($row[$col_indexes['initial_stock']] ?? 0);
            $low_stock_threshold = intval($row[$col_indexes['low_stock_threshold']] ?? 5);

            if ($price_retail == 0)
                $price_retail = $price_capital;
            if ($price_capital == 0)
                $price_capital = $price_retail;

            // Duplicate Check
            $existing_var_id = null;
            if (!empty($sku) || !empty($barcode)) {
                $check_sql = "SELECT variation_id FROM product_variations WHERE status = 'active'";
                $check_params = [];
                if (!empty($sku)) {
                    $check_sql .= " AND sku = ?";
                    $check_params[] = $sku;
                } elseif (!empty($barcode)) {
                    $check_sql .= " AND barcode = ?";
                    $check_params[] = $barcode;
                }
                $stmtCheck = $conn->prepare($check_sql);
                $stmtCheck->execute($check_params);
                $existing_var_id = $stmtCheck->fetchColumn();
            }

            if ($existing_var_id) {
                if ($update_existing) {
                    $conn->beginTransaction();
                    $updSql = "UPDATE product_variations SET variation_name=?, unit_type=?, price_capital=?, price_retail=?, price_wholesale=?, price_dealer=?, low_stock_threshold=? WHERE variation_id=?";
                    $conn->prepare($updSql)->execute([$variation_name, $unit_type, $price_capital, $price_retail, $price_wholesale, $price_dealer, $low_stock_threshold, $existing_var_id]);

                    if ($initial_stock > 0) {
                        $stmtInv = $conn->prepare("INSERT INTO inventory (variation_id, quantity, store_id) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
                        $stmtInv->execute([$existing_var_id, $initial_stock, $initial_stock]);

                        $currQtyStmt = $conn->prepare("SELECT quantity FROM inventory WHERE variation_id = ? AND store_id = 1");
                        $currQtyStmt->execute([$existing_var_id]);
                        $currQty = $currQtyStmt->fetchColumn() ?: 0;

                        $remarks = "Batch Upload Update" . ($supplier_name ? " - Supplier: $supplier_name" : "");
                        $movSql = "INSERT INTO stock_movements (variation_id, type, quantity, previous_stock, new_stock, remarks, created_by, store_id, capital_cost) VALUES (?, 'stock_in', ?, ?, ?, ?, ?, 1, ?)";
                        $conn->prepare($movSql)->execute([$existing_var_id, $initial_stock, $currQty - $initial_stock, $currQty, $remarks, $_SESSION['user_id'], $price_capital]);
                    }
                    $conn->commit();
                    $success_count++;
                } else {
                    $skip_count++;
                }
                continue;
            }

            $conn->beginTransaction();
            $stmtProd = $conn->prepare("INSERT INTO products (product_name, brand_name, category_id, description) VALUES (?, ?, ?, ?)");
            $stmtProd->execute([$product_name, $brand_name, $category_id, $description]);
            $prod_id = $conn->lastInsertId();

            $stmtVar = $conn->prepare("INSERT INTO product_variations (product_id, variation_name, unit_type, barcode, sku, price_capital, price_retail, price_wholesale, price_dealer, low_stock_threshold) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtVar->execute([$prod_id, $variation_name, $unit_type, (!empty($barcode) ? $barcode : null), (!empty($sku) ? $sku : null), $price_capital, $price_retail, $price_wholesale, $price_dealer, $low_stock_threshold]);
            $var_id = $conn->lastInsertId();

            if ($initial_stock > 0) {
                $conn->prepare("INSERT INTO inventory (variation_id, store_id, quantity) VALUES (?, 1, ?)")->execute([$var_id, $initial_stock]);
                $remarks = "Initial Stock (Batch Upload)" . ($supplier_name ? " - Supplier: $supplier_name" : "");
                $conn->prepare("INSERT INTO stock_movements (variation_id, type, quantity, previous_stock, new_stock, remarks, created_by, store_id, capital_cost) VALUES (?, 'stock_in', ?, 0, ?, ?, ?, 1, ?)")
                    ->execute([$var_id, $initial_stock, $initial_stock, $remarks, $_SESSION['user_id'], $price_capital]);
            }
            $conn->commit();
            $success_count++;
        } catch (Exception $e) {
            if ($conn->inTransaction())
                $conn->rollBack();
            $warnings[] = "Row " . ($i + 1) . ": " . $e->getMessage();
            $error_count++;
        }
    }

    $msg = "Upload Complete: $success_count processed";
    if ($skip_count > 0)
        $msg .= ", $skip_count skipped";
    header("Location: ../../views/inventory/batch_upload.php?success=" . urlencode($msg) . (!empty($warnings) ? "&warnings=" . urlencode(json_encode($warnings)) : ""));
} catch (Exception $e) {
    header("Location: ../../views/inventory/batch_upload.php?error=" . urlencode("Fatal: " . $e->getMessage()));
}
