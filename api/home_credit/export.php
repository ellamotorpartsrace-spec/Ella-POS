<?php
// api/home_credit/export.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireLogin();
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && !hasPermission('view_profit') && !in_array($_SESSION['role'], ['manager', 'accountant']))) {
    http_response_code(403);
    die("Permission Denied.");
}

$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $sql = "
        SELECT 
            s.sale_ref,
            s.created_at,
            COALESCE(s.walkin_name, b.buyer_name, 'Walk-in Customer') as customer_name,
            (
                SELECT GROUP_CONCAT(CONCAT(si.quantity, 'x ', si.product_name) SEPARATOR ', ')
                FROM pos_sale_items si
                WHERE si.sale_id = s.sale_id
            ) as items_sold,
            (
                SELECT p.reference_no
                FROM pos_sale_payments p 
                WHERE p.sale_id = s.sale_id AND p.payment_type = 'home_credit' 
                LIMIT 1
            ) as contract_ref,
            (
                SELECT p.amount
                FROM pos_sale_payments p 
                WHERE p.sale_id = s.sale_id 
                  AND p.payment_type IN ('cash', 'gcash', 'bank_transfer')
                  AND p.reference_no LIKE 'DP-%'
                LIMIT 1
            ) as down_payment_amount,
            (
                SELECT p.payment_type
                FROM pos_sale_payments p 
                WHERE p.sale_id = s.sale_id 
                  AND p.payment_type IN ('cash', 'gcash', 'bank_transfer')
                  AND p.reference_no LIKE 'DP-%'
                LIMIT 1
            ) as down_payment_method,
            (
                SELECT p.amount 
                FROM pos_sale_payments p 
                WHERE p.sale_id = s.sale_id AND p.payment_type = 'home_credit' 
                LIMIT 1
            ) as financed_amount,
            s.grand_total,
            s.status
        FROM pos_sales s
        LEFT JOIN buyers b ON s.buyer_id = b.buyer_id
        WHERE s.payment_method = 'home_credit'
          AND DATE(s.created_at) BETWEEN :date_from AND :date_to
    ";

    $params = [
        ':date_from' => $date_from,
        ':date_to' => $date_to
    ];

    if (!empty($search)) {
        $sql .= " AND (
            s.sale_ref LIKE :search 
            OR s.walkin_name LIKE :search 
            OR b.buyer_name LIKE :search
            OR s.sale_id IN (
                SELECT p.sale_id 
                FROM pos_sale_payments p 
                WHERE p.reference_no LIKE :search
            )
        )";
        $params[':search'] = "%$search%";
    }

    $sql .= " ORDER BY s.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $filename = "Home_Credit_Records_" . date('Ymd_His') . ".csv";
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Date/Time',
        'POS Reference',
        'Customer',
        'Items Sold',
        'HC Contract Ref',
        'Down Payment',
        'DP Method',
        'Financed Amount',
        'Grand Total',
        'Status'
    ]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['created_at'],
            $row['sale_ref'],
            $row['customer_name'],
            $row['items_sold'] ?: 'No items',
            $row['contract_ref'] ?: 'N/A',
            number_format((float) $row['down_payment_amount'], 2, '.', ''),
            strtoupper($row['down_payment_method'] ?: 'N/A'),
            number_format((float) $row['financed_amount'], 2, '.', ''),
            number_format((float) $row['grand_total'], 2, '.', ''),
            strtoupper($row['status'])
        ]);
    }

    fclose($output);

} catch (Exception $e) {
    http_response_code(500);
    echo "Export failed: " . $e->getMessage();
}
