<?php
// api/home_credit/list.php
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
requireLogin();
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && !hasPermission('view_profit') && !in_array($_SESSION['role'], ['manager', 'accountant']))) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Base query for home credit sales
    $sql = "
        SELECT 
            s.sale_id,
            s.sale_ref,
            s.grand_total,
            s.status,
            s.created_at,
            COALESCE(s.walkin_name, b.buyer_name, 'Walk-in Customer') as customer_name,
            
            -- Combine items into a distinct string
            (
                SELECT GROUP_CONCAT(CONCAT(si.quantity, 'x ', si.product_name) SEPARATOR ', ')
                FROM pos_sale_items si
                WHERE si.sale_id = s.sale_id
            ) as items_sold,

            -- Fetch Financed Amount (from pos_sale_payments where type = 'home_credit')
            (
                SELECT p.amount 
                FROM pos_sale_payments p 
                WHERE p.sale_id = s.sale_id AND p.payment_type = 'home_credit' 
                LIMIT 1
            ) as financed_amount,

            -- Fetch Contract Ref (from pos_sale_payments where type = 'home_credit')
            (
                SELECT p.reference_no
                FROM pos_sale_payments p 
                WHERE p.sale_id = s.sale_id AND p.payment_type = 'home_credit' 
                LIMIT 1
            ) as contract_ref,

            -- Fetch Down Payment Amount (from pos_sale_payments where type IN ('cash','gcash','bank_transfer') and ref LIKE 'DP-%')
            (
                SELECT p.amount
                FROM pos_sale_payments p 
                WHERE p.sale_id = s.sale_id 
                  AND p.payment_type IN ('cash', 'gcash', 'bank_transfer')
                  AND p.reference_no LIKE 'DP-%'
                LIMIT 1
            ) as down_payment_amount,

            -- Fetch Down Payment Method
            (
                SELECT p.payment_type
                FROM pos_sale_payments p 
                WHERE p.sale_id = s.sale_id 
                  AND p.payment_type IN ('cash', 'gcash', 'bank_transfer')
                  AND p.reference_no LIKE 'DP-%'
                LIMIT 1
            ) as down_payment_method

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

    $sql .= " ORDER BY s.created_at DESC LIMIT 300";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate sum stats
    $stats = [
        'count' => count($transactions),
        'total_sales' => 0,
        'total_financed' => 0,
        'total_downpayment' => 0
    ];

    foreach ($transactions as $t) {
        if ($t['status'] !== 'voided') {
            $stats['total_sales'] += floatval($t['grand_total']);
            $stats['total_financed'] += floatval($t['financed_amount']);
            $stats['total_downpayment'] += floatval($t['down_payment_amount']);
        }
    }

    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'stats' => $stats
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
