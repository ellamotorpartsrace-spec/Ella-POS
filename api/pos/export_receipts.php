<?php
// api/pos/export_receipts.php - Export receipts to CSV
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('make_sales') && !hasPermission('view_profit') && !in_array($_SESSION['role'], ['manager', 'cashier'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Get filters
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$payment = $_GET['payment'] ?? '';
$search = $_GET['search'] ?? '';

// Build query - matching list_transactions_enhanced.php structure
$payment_subquery = "";
if (!empty($payment) && in_array($payment, ['cash', 'gcash', 'bank'])) {
    $db_payment = ($payment === 'bank') ? 'bank_transfer' : $payment;
    $payment_subquery = ", (SELECT COALESCE(SUM(amount), 0) FROM pos_sale_payments WHERE sale_id = s.sale_id AND payment_type = '$db_payment') as filtered_method_amount";
}

$sql = "SELECT 
            s.sale_id,
            s.sale_ref,
            s.created_at,
            COALESCE(s.walkin_name, b.buyer_name, 'Walk-in') as customer_name,
            COALESCE(s.buyer_shop_name, b.shop_name, '') as shop_name,
            s.payment_method,
            s.subtotal as sale_subtotal,
            s.discount_amount as sale_discount,
            s.grand_total as sale_grand_total,
            s.amount_tendered,
            s.change_due,
            s.status,
            s.remarks,
            si.product_name,
            si.brand_name,
            si.variation_name,
            si.quantity,
            si.price_at_sale,
            (si.quantity * si.price_at_sale) as item_total
            $payment_subquery
        FROM pos_sales s
        LEFT JOIN buyers b ON s.buyer_id = b.buyer_id
        LEFT JOIN pos_sale_items si ON s.sale_id = si.sale_id
        WHERE DATE(s.created_at) BETWEEN :date_from AND :date_to";

$params = [
    ':date_from' => $dateFrom,
    ':date_to' => $dateTo
];

// Status filter
if (!empty($status)) {
    $sql .= " AND s.status = :status";
    $params[':status'] = $status;
}

// Payment filter
if (!empty($payment)) {
    if ($payment === 'financing') {
        $sql .= " AND (s.payment_method = 'financing' OR s.payment_method = 'home_credit')";
    } elseif (in_array($payment, ['cash', 'gcash', 'bank'])) {
        $db_payment = ($payment === 'bank') ? 'bank_transfer' : $payment;
        $sql .= " AND (s.payment_method = :payment OR s.sale_id IN (SELECT sale_id FROM pos_sale_payments WHERE payment_type = :db_payment))";
        $params[':payment'] = $payment;
        $params[':db_payment'] = $db_payment;
    } else {
        $sql .= " AND s.payment_method = :payment";
        $params[':payment'] = $payment;
    }
}

// Search filter
if (!empty($search)) {
    $sql .= " AND (
        s.sale_ref LIKE :search 
        OR s.walkin_name LIKE :search 
        OR b.buyer_name LIKE :search 
        OR b.shop_name LIKE :search
        OR s.sale_id IN (
            SELECT DISTINCT si_inner.sale_id 
            FROM pos_sale_items si_inner 
            WHERE si_inner.product_name LIKE :search 
               OR si_inner.brand_name LIKE :search 
               OR si_inner.variation_name LIKE :search
        )
    )";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " ORDER BY s.created_at DESC, s.sale_id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
$filename = 'receipts_details_' . $dateFrom . '_to_' . $dateTo . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV Headers
$headers = [
    'Reference',
    'Date & Time',
    'Customer',
    'Shop',
    'Payment Method',
    'Product Name',
    'Brand',
    'Variation',
    'Qty',
    'Unit Price',
    'Item Total',
    'Sale Subtotal',
    'Sale Discount'
];

$isInHandFilter = !empty($payment) && in_array($payment, ['cash', 'gcash', 'bank']);
if ($isInHandFilter) {
    $headers[] = 'Method Portion (' . ucfirst($payment) . ')';
}

$headers = array_merge($headers, [
    'Sale Grand Total',
    'Status',
    'Remarks'
]);

fputcsv($output, $headers);

// CSV Data
$lastSaleId = null;
foreach ($transactions as $t) {
    // We can choose to repeat sale info or not. Repeating is better for analysis.
    $row = [
        $t['sale_ref'],
        $t['created_at'],
        $t['customer_name'],
        $t['shop_name'],
        ucfirst(str_replace('_', ' ', $t['payment_method'] ?? 'Cash')),
        $t['product_name'],
        $t['brand_name'],
        $t['variation_name'],
        $t['quantity'],
        number_format($t['price_at_sale'], 2, '.', ''),
        number_format($t['item_total'], 2, '.', ''),
        number_format($t['sale_subtotal'], 2, '.', ''),
        number_format($t['sale_discount'] ?? 0, 2, '.', '')
    ];

    if ($isInHandFilter) {
        $row[] = number_format($t['filtered_method_amount'] ?? $t['sale_grand_total'], 2, '.', '');
    }

    $row = array_merge($row, [
        number_format($t['sale_grand_total'], 2, '.', ''),
        ucfirst($t['status']),
        $t['remarks'] ?? ''
    ]);

    fputcsv($output, $row);
    $lastSaleId = $t['sale_id'];
}


fclose($output);
exit;
