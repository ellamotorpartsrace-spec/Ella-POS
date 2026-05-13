<?php
/**
 * api/inventory/process_platform_transfer.php
 * Batch transfers stock from Store 1 to Store 2 using Platform Variation IDs.
 * Supports JSON input and Excel/CSV file uploads.
 */
declare(strict_types=1);
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../classes/SyncHelper.php';

// Security Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireLogin();
// Check permissions
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission Denied']);
    exit;
}

$transfers = [];

// Handle File Upload or JSON input
if (isset($_FILES['pt_file']) && $_FILES['pt_file']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['pt_file']['tmp_name'];
    $file_name = $_FILES['pt_file']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $data = [];

    if (in_array($file_ext, ['xlsx', 'xls'])) {
        $excelClass = 'PhpOffice\\PhpSpreadsheet\\IOFactory';
        if (class_exists($excelClass)) {
            try {
                $spreadsheet = $excelClass::load($file_tmp);
                $data = $spreadsheet->getActiveSheet()->toArray();
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Error reading Excel: ' . $e->getMessage()]);
                exit;
            }
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Excel support not available on server']);
            exit;
        }
    } else {
        // Assume CSV
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

    if (!empty($data)) {
        $header_row_index = 0;
        $col_id = false;
        $col_qty = false;

        // Scan rows to find the headers (useful for platform exports with info rows on top)
        foreach ($data as $index => $row) {
            $headers = array_map('trim', array_map('strtolower', (array) $row));

            $id_idx = array_search('platform variation id', $headers);
            if ($id_idx === false)
                $id_idx = array_search('variation id', $headers);
            if ($id_idx === false)
                $id_idx = array_search('online variation id', $headers);
            if ($id_idx === false)
                $id_idx = array_search('sku id', $headers); // Some platform formats
            if ($id_idx === false)
                $id_idx = array_search('id', $headers);

            $qty_idx = array_search('quantity', $headers);
            if ($qty_idx === false)
                $qty_idx = array_search('stock', $headers);
            if ($qty_idx === false)
                $qty_idx = array_search('qty', $headers);
            if ($qty_idx === false)
                $qty_idx = array_search('current stock', $headers);

            if ($id_idx !== false && $qty_idx !== false) {
                $header_row_index = $index;
                $col_id = $id_idx;
                $col_qty = $qty_idx;
                break;
            }
        }

        // Fallback or exact matches if not perfectly found in loop
        if ($col_id === false || $col_qty === false) {
            $col_id = 0;
            $col_qty = 1;
            $header_row_index = 0;
        }

        for ($i = $header_row_index + 1; $i < count($data); $i++) {
            $row = $data[$i];
            if (empty(array_filter($row)))
                continue;

            $tid = trim((string) ($row[$col_id] ?? ''));
            // Remove commas or formatting if stock comes in as "1,000"
            $tqty_raw = str_replace(',', '', (string) ($row[$col_qty] ?? '0'));
            $tqty = (int) $tqty_raw;

            // Only add if ID is valid (skip informational/instruction rows)
            if ($tid !== '' && $tqty > 0 && is_numeric($tid)) {
                $transfers[] = [
                    'online_variation_id' => $tid,
                    'quantity' => $tqty
                ];
            }
        }
    }
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    $transfers = $input['transfers'] ?? [];
}

$action = $_GET['action'] ?? $input['action'] ?? 'preview';

if (empty($transfers)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No valid transfer data provided. Please paste data or upload a file.']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $results = [];
    $processed_count = 0;
    $error_count = 0;

    foreach ($transfers as $item) {
        $platform_id = trim((string) ($item['online_variation_id'] ?? ''));
        $qty = (int) ($item['quantity'] ?? 0);

        if (empty($platform_id) || $qty <= 0) {
            $results[] = [
                'online_variation_id' => $platform_id,
                'status' => 'error',
                'message' => 'Invalid ID or quantity'
            ];
            $error_count++;
            continue;
        }

        try {
            if ($action === 'commit') {
                $conn->beginTransaction();
            }

            // 1. Find the internal variation_id
            $stmtLink = $conn->prepare("
                SELECT l.variation_id, l.platform, p.product_name, v.variation_name, v.price_capital, v.sku
                FROM online_platform_links l
                JOIN product_variations v ON l.variation_id = v.variation_id
                JOIN products p ON v.product_id = p.product_id
                WHERE l.online_variation_id = ? AND l.is_active = 1
                LIMIT 1
            ");
            $stmtLink->execute([$platform_id]);
            $link = $stmtLink->fetch(PDO::FETCH_ASSOC);

            if (!$link) {
                throw new Exception("Platform Variation ID not found or unlinked");
            }

            $variationId = (int) $link['variation_id'];
            $itemName = $link['product_name'] . ($link['variation_name'] ? " ({$link['variation_name']})" : "");

            // 2. Check Physical Store Stock (Store 1)
            $stmtSrc = $conn->prepare("SELECT quantity FROM inventory WHERE variation_id = ? AND store_id = 1");
            $stmtSrc->execute([$variationId]);
            $sourceStock = (int) ($stmtSrc->fetchColumn() ?: 0);

            if ($sourceStock < $qty) {
                throw new Exception("Insufficient stock in Store 1 (Available: $sourceStock)");
            }

            if ($action === 'commit') {
                // 3. Deduct from Store 1
                $stmtDeduct = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE variation_id = ? AND store_id = 1");
                $stmtDeduct->execute([$qty, $variationId]);

                // 4. Add to Store 2 (Online)
                $stmtAdd = $conn->prepare("
                    INSERT INTO inventory (variation_id, store_id, quantity) 
                    VALUES (?, 2, ?)
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
                ");
                $stmtAdd->execute([$variationId, $qty]);

                // 5. Audit Logging
                $stmtFinalSrc = $conn->prepare("SELECT quantity FROM inventory WHERE variation_id = ? AND store_id = 1");
                $stmtFinalSrc->execute([$variationId]);
                $newSourceStock = (int) $stmtFinalSrc->fetchColumn();

                $stmtFinalDest = $conn->prepare("SELECT quantity FROM inventory WHERE variation_id = ? AND store_id = 2");
                $stmtFinalDest->execute([$variationId]);
                $newDestStock = (int) $stmtFinalDest->fetchColumn();

                $movType = 'allocation_to_online';
                $ref = 'PLAT-TRANS-' . date('YmdHis');
                $remarks = "Bulk Transfer via Platform Variation ID: $platform_id";

                $stmtMov = $conn->prepare("
                    INSERT INTO stock_movements 
                    (variation_id, store_id, type, quantity, previous_stock, new_stock, reference, remarks, created_by, capital_cost)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                // Source (Deduct)
                $stmtMov->execute([
                    $variationId,
                    1,
                    $movType,
                    -$qty,
                    $sourceStock,
                    $newSourceStock,
                    $ref,
                    $remarks,
                    $_SESSION['user_id'],
                    $link['price_capital']
                ]);

                // Destination (Add)
                $stmtMov->execute([
                    $variationId,
                    2,
                    $movType,
                    $qty,
                    $newDestStock - $qty,
                    $newDestStock,
                    $ref,
                    $remarks,
                    $_SESSION['user_id'],
                    $link['price_capital']
                ]);

                $conn->commit();

                // Trigger Sync Queue for this variation
                SyncHelper::queueStockUpdate($conn, $variationId);

                $results[] = [
                    'online_variation_id' => $platform_id,
                    'status' => 'success',
                    'product_name' => $itemName,
                    'sku' => $link['sku'],
                    'moved' => $qty,
                    'new_online_stock' => $newDestStock
                ];
            } else {
                // PREVIEW MODE
                $results[] = [
                    'online_variation_id' => $platform_id,
                    'status' => 'success',
                    'product_name' => $itemName,
                    'sku' => $link['sku'],
                    'quantity' => $qty,
                    'message' => 'Ready to transfer'
                ];
            }

            $processed_count++;

        } catch (Exception $e) {
            if ($action === 'commit' && $conn->inTransaction())
                $conn->rollBack();
            $results[] = [
                'online_variation_id' => $platform_id,
                'status' => 'error',
                'quantity' => $qty,
                'message' => $e->getMessage(),
                'product_name' => $itemName ?? null,
                'sku' => $link['sku'] ?? null
            ];
            $error_count++;
        }
    }

    echo json_encode([
        'success' => true,
        'mode' => $action,
        'summary' => [
            'total' => count($transfers),
            'processed' => $processed_count,
            'errors' => $error_count
        ],
        'results' => $results
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
