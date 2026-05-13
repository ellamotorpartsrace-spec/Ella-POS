<?php
// api/users/get_user_sales.php
// Get sales for a specific user with date filtering

header("Content-Type: application/json");
require_once '../../config/database.php';

$user_id = $_GET['user_id'] ?? null;
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$method = $_GET['method'] ?? 'all';

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Build query with filters
    $payment_subquery = "";
    if ($method !== 'all' && in_array($method, ['cash', 'gcash', 'bank'])) {
        $db_payment = ($method === 'bank') ? 'bank_transfer' : $method;
        $payment_subquery = ", (SELECT COALESCE(SUM(amount), 0) FROM pos_sale_payments WHERE sale_id = pos_sales.sale_id AND payment_type = '$db_payment') as filtered_method_amount";
    }

    $sql = "SELECT sale_id, sale_ref, created_at, grand_total, payment_method, status
            $payment_subquery
            FROM pos_sales 
            WHERE user_id = :user_id 
              AND DATE(created_at) BETWEEN :date_from AND :date_to";

    $params = [
        ':user_id' => $user_id,
        ':date_from' => $date_from,
        ':date_to' => $date_to
    ];

    if ($method !== 'all') {
        if ($method === 'financing') {
            $sql .= " AND (payment_method = 'financing' OR payment_method = 'home_credit')";
        } elseif (in_array($method, ['cash', 'gcash', 'bank'])) {
            $db_payment = ($method === 'bank') ? 'bank_transfer' : $method;
            $sql .= " AND (payment_method = :method OR sale_id IN (SELECT sale_id FROM pos_sale_payments WHERE payment_type = :db_payment))";
            $params[':method'] = $method;
            $params[':db_payment'] = $db_payment;
        } else {
            $sql .= " AND payment_method = :method";
            $params[':method'] = $method;
        }
    }

    $sql .= " ORDER BY created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate stats (exclude voided from totals)
    $count = 0;
    $total = 0;
    $isInHandFilter = ($method !== 'all' && in_array($method, ['cash', 'gcash', 'bank']));

    foreach ($sales as $sale) {
        if ($sale['status'] !== 'voided') {
            $count++;
            if ($isInHandFilter && isset($sale['filtered_method_amount'])) {
                $total += floatval($sale['filtered_method_amount']);
            } else {
                $total += floatval($sale['grand_total']);
            }
        }
    }

    echo json_encode([
        'success' => true,
        'sales' => $sales,
        'stats' => [
            'count' => $count,
            'total' => $total
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
