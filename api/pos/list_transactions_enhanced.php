<?php
// api/pos/list_transactions_enhanced.php - Enhanced transaction listing with filters
header("Content-Type: application/json");
require_once '../../config/database.php';

$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$payment = $_GET['payment'] ?? '';
$search = $_GET['search'] ?? '';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Build query with filters
    $payment_subquery = "";
    if (!empty($payment) && in_array($payment, ['cash', 'gcash', 'bank'])) {
        $db_payment = ($payment === 'bank') ? 'bank_transfer' : $payment;
        $payment_subquery = ", (SELECT COALESCE(SUM(amount), 0) FROM pos_sale_payments WHERE sale_id = s.sale_id AND payment_type = '$db_payment') as filtered_method_amount";
    }

    $sql = "
        SELECT 
            s.sale_id,
            s.sale_ref,
            s.grand_total,
            s.subtotal,
            s.status,
            s.created_at,
            s.walkin_name,
            s.buyer_shop_name as shop_name,
            s.payment_method,
            COALESCE(s.walkin_name, b.buyer_name, 'Walk-in') as customer_name,
            COALESCE(s.buyer_shop_name, b.shop_name) as shop_name,
            (SELECT due_date FROM pos_sale_payments WHERE sale_id = s.sale_id AND payment_type = 'pay_later' LIMIT 1) as due_date,
            (
                SELECT COALESCE(SUM(si.cost_at_sale * (si.quantity - (
                    SELECT COALESCE(SUM(ri.quantity), 0)
                    FROM pos_return_items ri
                    INNER JOIN pos_returns pr ON ri.return_id = pr.return_id
                    WHERE ri.sale_item_id = si.sale_item_id AND pr.status = 'completed'
                ))), 0)
                FROM pos_sale_items si
                WHERE si.sale_id = s.sale_id
            ) as total_cost,
            (
                s.grand_total - COALESCE((
                    SELECT SUM(si.cost_at_sale * (si.quantity - (
                        SELECT COALESCE(SUM(ri.quantity), 0)
                        FROM pos_return_items ri
                        INNER JOIN pos_returns pr ON ri.return_id = pr.return_id
                        WHERE ri.sale_item_id = si.sale_item_id AND pr.status = 'completed'
                    )))
                    FROM pos_sale_items si
                    WHERE si.sale_id = s.sale_id
                ), 0)
            ) as profit
            $payment_subquery
        FROM pos_sales s
        LEFT JOIN buyers b ON s.buyer_id = b.buyer_id
        WHERE DATE(s.created_at) BETWEEN ? AND ?
    ";

    $params = [$date_from, $date_to];

    if (!empty($status)) {
        $sql .= " AND s.status = ?";
        $params[] = $status;
    }

    if (!empty($payment)) {
        if ($payment === 'financing') {
            $sql .= " AND (s.payment_method = 'financing' OR s.payment_method = 'home_credit')";
        } elseif (in_array($payment, ['cash', 'gcash', 'bank'])) {
            $db_payment = ($payment === 'bank') ? 'bank_transfer' : $payment;
            $sql .= " AND (s.payment_method = ? OR s.sale_id IN (SELECT sale_id FROM pos_sale_payments WHERE payment_type = ?))";
            $params[] = $payment;
            $params[] = $db_payment;
        } else {
            $sql .= " AND s.payment_method = ?";
            $params[] = $payment;
        }
    }

    if (!empty($search)) {
        // Search in sale reference, customer info, AND products inside the receipt
        $sql .= " AND (
            s.sale_ref LIKE ? 
            OR s.walkin_name LIKE ? 
            OR b.buyer_name LIKE ? 
            OR b.shop_name LIKE ?
            OR s.sale_id IN (
                SELECT DISTINCT si.sale_id 
                FROM pos_sale_items si 
                WHERE si.product_name LIKE ? 
                   OR si.brand_name LIKE ? 
                   OR si.variation_name LIKE ?
            )
        )";
        $term = "%$search%";
        $params = array_merge($params, [$term, $term, $term, $term, $term, $term, $term]);
    }

    $sql .= " ORDER BY s.created_at DESC LIMIT 200";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate stats from results
    $stats = [
        'count' => count($transactions),
        'total' => 0,
        'voided' => 0,
        'pending' => 0,
        'total_profit' => 0
    ];

    $isInHandFilter = !empty($payment) && in_array($payment, ['cash', 'gcash', 'bank']);

    foreach ($transactions as $t) {
        if ($t['status'] !== 'voided') {
            if ($isInHandFilter && isset($t['filtered_method_amount'])) {
                $stats['total'] += floatval($t['filtered_method_amount']);
            } else {
                $stats['total'] += floatval($t['grand_total']);
            }
            $stats['total_profit'] += floatval($t['profit']);
        }
        if ($t['status'] === 'voided') {
            $stats['voided']++;
        }
        if ($t['payment_method'] === 'pay_later') {
            $stats['pending']++;
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
