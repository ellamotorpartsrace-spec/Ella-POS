<?php
// api/inventory/export_stockin_records_csv.php - Export stock-in records to CSV
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    die("Permission denied.");
}

$supplier_id = $_GET['supplier_id'] ?? null;
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

if (!$supplier_id) {
    die("No supplier selected");
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get supplier name
    $stmtSupp = $conn->prepare("SELECT supplier_name FROM suppliers WHERE supplier_id = ?");
    $stmtSupp->execute([$supplier_id]);
    $supplier = $stmtSupp->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        die("Supplier not found");
    }

    $supplier_name = $supplier['supplier_name'];

    // Build query - same logic as get_stockin_records.php but without limit/offset
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
            pv.sku,
            pv.barcode,
            COALESCE(sm.capital_cost, pv.price_capital) as price_capital,
            pv.unit_type,
            p.product_name,
            p.brand_name,
            p.image_path,
            u.full_name as created_by_name
        FROM stock_movements sm
        JOIN product_variations pv ON sm.variation_id = pv.variation_id
        JOIN products p ON pv.product_id = p.product_id
        LEFT JOIN users u ON sm.created_by = u.id
        WHERE sm.type = 'stock_in'
        AND (
            sm.remarks LIKE ? 
            OR sm.remarks LIKE ?
            OR sm.reference IN (SELECT po_ref FROM purchase_orders WHERE supplier_id = ?)
        )
    ";

    $params = [
        "%Restock: {$supplier_name}%",
        "%Batch Restock: {$supplier_name}%",
        $supplier_id
    ];

    if (!empty($date_from)) {
        $sql .= " AND DATE(sm.created_at) >= ?";
        $params[] = $date_from;
    }

    if (!empty($date_to)) {
        $sql .= " AND DATE(sm.created_at) <= ?";
        $params[] = $date_to;
    }

    $sql .= " ORDER BY sm.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Output CSV
    $filename = "StockIn_Records_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $supplier_name) . "_" . date('Ymd_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');

    // Add BOM for Excel utf-8
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

    fputcsv($output, [
        'Date & Time',
        'Product Name',
        'Brand',
        'Variation',
        'SKU',
        'Capital Price',
        'Qty Added',
        'Total Cost',
        'Previous Stock',
        'New Stock',
        'Reference',
        'Created By'
    ]);

    foreach ($records as $row) {
        $capital = (float) $row['price_capital'];
        $qty = abs((int) $row['quantity']);
        $total = $capital * $qty;

        fputcsv($output, [
            $row['created_at'],
            $row['product_name'],
            $row['brand_name'] ?? '',
            $row['variation_name'] ?? '',
            $row['sku'] ?? '',
            number_format($capital, 2, '.', ''),
            $qty,
            number_format($total, 2, '.', ''),
            $row['previous_stock'],
            $row['new_stock'],
            $row['reference'] ?? '',
            $row['created_by_name'] ?? 'System'
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
