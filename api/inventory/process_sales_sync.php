<?php
/**
 * api/inventory/process_sales_sync.php
 * Compares platform-reported stock vs Ella POS online stock and
 * records the difference as auto-detected online sales.
 *
 * Supports two modes via ?action= query param or JSON body:
 *   preview — validate and show what would be recorded (NO DB writes)
 *   commit  — actually deduct stock and log sales
 */
declare(strict_types=1);
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission Denied']);
    exit;
}

// -----------------------------------------------------------------------
// 1. PARSE INPUT  (file upload  OR  JSON body)
// -----------------------------------------------------------------------
$rows     = [];   // [ ['online_variation_id' => '...', 'platform_stock' => N], ... ]
$platform = 'Other';

if (isset($_FILES['sync_file']) && $_FILES['sync_file']['error'] === UPLOAD_ERR_OK) {
    $file_tmp  = $_FILES['sync_file']['tmp_name'];
    $file_name = $_FILES['sync_file']['name'];
    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $platform  = trim($_POST['platform'] ?? 'Other');
    $data      = [];

    if (in_array($file_ext, ['xlsx', 'xls'])) {
        $excelClass = 'PhpOffice\\PhpSpreadsheet\\IOFactory';
        if (class_exists($excelClass)) {
            try {
                $spreadsheet = $excelClass::load($file_tmp);
                $data        = $spreadsheet->getActiveSheet()->toArray();
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Error reading Excel: ' . $e->getMessage()]);
                exit;
            }
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Excel support not available on server. Upload a CSV instead.']);
            exit;
        }
    } else {
        // CSV
        $delimiter = ',';
        $peek = fopen($file_tmp, 'r');
        if ($peek) {
            $first = fgets($peek);
            if ($first && substr_count($first, ';') > substr_count($first, ',')) $delimiter = ';';
            fclose($peek);
        }
        if (($handle = fopen($file_tmp, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) $data[] = $row;
            fclose($handle);
        }
    }

    // Detect header row dynamically (platform exports have info rows on top)
    $header_idx = 0;
    $col_id     = false;
    $col_stock  = false;

    foreach ($data as $idx => $row) {
        $h = array_map('trim', array_map('strtolower', (array)$row));

        $id_i = array_search('platform variation id', $h);
        if ($id_i === false) $id_i = array_search('variation id', $h);
        if ($id_i === false) $id_i = array_search('online variation id', $h);
        if ($id_i === false) $id_i = array_search('sku id', $h);
        if ($id_i === false) $id_i = array_search('id', $h);

        // Prefer "stock" over "quantity" for platform exports showing remaining stock
        $qty_i = array_search('stock', $h);
        if ($qty_i === false) $qty_i = array_search('quantity', $h);
        if ($qty_i === false) $qty_i = array_search('qty', $h);
        if ($qty_i === false) $qty_i = array_search('current stock', $h);

        if ($id_i !== false && $qty_i !== false) {
            $header_idx = $idx;
            $col_id     = $id_i;
            $col_stock  = $qty_i;
            break;
        }
    }

    if ($col_id === false || $col_stock === false) {
        $col_id    = 0;
        $col_stock = 1;
    }

    for ($i = $header_idx + 1; $i < count($data); $i++) {
        $row    = $data[$i];
        if (empty(array_filter($row))) continue;

        $tid   = trim((string)($row[$col_id] ?? ''));
        $stock = (int) str_replace(',', '', (string)($row[$col_stock] ?? '-1'));

        if ($tid !== '' && is_numeric($tid) && $stock >= 0) {
            $rows[] = ['online_variation_id' => $tid, 'platform_stock' => $stock];
        }
    }
} else {
    $input    = json_decode(file_get_contents('php://input'), true) ?? [];
    $rows     = $input['rows']     ?? [];
    $platform = $input['platform'] ?? 'Other';
}

$action = $_GET['action'] ?? $input['action'] ?? 'preview';

if (empty($rows)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No valid rows found. Upload a file or provide sync data.']);
    exit;
}

// -----------------------------------------------------------------------
// 2. PROCESS
// -----------------------------------------------------------------------
try {
    $db   = new Database();
    $conn = $db->getConnection();

    $results      = [];
    $synced_count = 0;
    $skip_count   = 0;

    foreach ($rows as $item) {
        $platform_id    = trim((string)($item['online_variation_id'] ?? ''));
        $platform_stock = (int)($item['platform_stock'] ?? -1);

        if ($platform_id === '' || $platform_stock < 0) {
            $skip_count++;
            $results[] = [
                'online_variation_id' => $platform_id,
                'status'              => 'skip',
                'message'             => 'Invalid data row'
            ];
            continue;
        }

        try {
            // Look up via platform link
            $stmtLink = $conn->prepare("
                SELECT l.variation_id, l.platform,
                       p.product_name, v.variation_name, v.price_capital, v.price_retail, v.sku
                FROM   online_platform_links l
                JOIN   product_variations v ON l.variation_id = v.variation_id
                JOIN   products p           ON v.product_id   = p.product_id
                WHERE  l.online_variation_id = ? AND l.is_active = 1
                LIMIT  1
            ");
            $stmtLink->execute([$platform_id]);
            $link = $stmtLink->fetch(PDO::FETCH_ASSOC);

            if (!$link) {
                $skip_count++;
                $results[] = [
                    'online_variation_id' => $platform_id,
                    'platform_stock'      => $platform_stock,
                    'status'              => 'skip',
                    'message'             => 'Platform ID not linked to any product'
                ];
                continue;
            }

            $variationId = (int)$link['variation_id'];
            $itemName    = $link['product_name'] . ($link['variation_name'] ? " ({$link['variation_name']})" : '');

            // Get current Ella POS online stock (store_id = 2)
            $stmtOnline = $conn->prepare("SELECT quantity FROM inventory WHERE variation_id = ? AND store_id = 2");
            $stmtOnline->execute([$variationId]);
            $ellaStock  = (int)($stmtOnline->fetchColumn() ?: 0);

            $sold = $ellaStock - $platform_stock;

            if ($sold <= 0) {
                // Platform stock >= Ella POS stock: nothing to record
                $skip_count++;
                $results[] = [
                    'online_variation_id' => $platform_id,
                    'product_name'        => $itemName,
                    'sku'                 => $link['sku'],
                    'ella_stock'          => $ellaStock,
                    'platform_stock'      => $platform_stock,
                    'sold'                => 0,
                    'status'              => 'no_change',
                    'message'             => $sold < 0
                        ? "Platform stock ($platform_stock) is higher than Ella POS ($ellaStock) — possible restock?"
                        : 'Stock matches — no sales to record'
                ];
                continue;
            }

            // ---- COMMIT: deduct + log ----
            if ($action === 'commit') {
                $conn->beginTransaction();

                // Deduct
                $stmtDeduct = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE variation_id = ? AND store_id = 2");
                $stmtDeduct->execute([$sold, $variationId]);

                $newStock = $ellaStock - $sold;
                $refStr   = 'SYNC-' . strtoupper(substr($platform, 0, 3)) . '-' . date('YmdHis');
                $remarks  = "Auto-synced sale via {$platform} | Platform ID: {$platform_id}";
                $retail   = (float)($link['price_retail'] ?? 0);

                $stmtMov = $conn->prepare("
                    INSERT INTO stock_movements
                        (variation_id, store_id, type, quantity, previous_stock, new_stock, reference, remarks, created_by, capital_cost)
                    VALUES (?, 2, 'online_sale', ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtMov->execute([
                    $variationId,
                    -$sold,
                    $ellaStock,
                    $newStock,
                    $refStr,
                    $remarks,
                    $_SESSION['user_id'],
                    $link['price_capital']
                ]);

                $conn->commit();

                $results[] = [
                    'online_variation_id' => $platform_id,
                    'product_name'        => $itemName,
                    'sku'                 => $link['sku'],
                    'ella_stock'          => $ellaStock,
                    'platform_stock'      => $platform_stock,
                    'sold'                => $sold,
                    'new_ella_stock'      => $newStock,
                    'status'              => 'synced',
                    'reference'           => $refStr
                ];
            } else {
                // PREVIEW — no DB write
                $results[] = [
                    'online_variation_id' => $platform_id,
                    'product_name'        => $itemName,
                    'sku'                 => $link['sku'],
                    'ella_stock'          => $ellaStock,
                    'platform_stock'      => $platform_stock,
                    'sold'                => $sold,
                    'status'              => 'will_sync',
                    'message'             => "Will record {$sold} units sold"
                ];
            }

            $synced_count++;

        } catch (Exception $e) {
            if ($action === 'commit' && $conn->inTransaction()) $conn->rollBack();
            $skip_count++;
            $results[] = [
                'online_variation_id' => $platform_id,
                'platform_stock'      => $platform_stock,
                'status'              => 'error',
                'message'             => $e->getMessage()
            ];
        }
    }

    echo json_encode([
        'success'  => true,
        'mode'     => $action,
        'platform' => $platform,
        'summary'  => [
            'total'   => count($rows),
            'synced'  => $synced_count,
            'skipped' => $skip_count
        ],
        'results'  => $results
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
