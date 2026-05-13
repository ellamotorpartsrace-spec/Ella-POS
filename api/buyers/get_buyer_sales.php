<?php
// api/buyers/get_buyer_sales.php
// Get sales for a specific buyer with date filtering

header("Content-Type: application/json");
require_once '../../config/database.php';

$buyer_id = $_GET['buyer_id'] ?? null;
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

if (!$buyer_id) {
    echo json_encode(['success' => false, 'error' => 'Buyer ID required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Fetch global stats (All Time)
    $stmtGlobal = $conn->prepare("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(grand_total), 0) as total,
            COALESCE(SUM((
                SELECT SUM((si.price_at_sale - si.cost_at_sale) * si.quantity)
                FROM pos_sale_items si 
                WHERE si.sale_id = s.sale_id
            )), 0) as profit
        FROM pos_sales s 
        WHERE s.buyer_id = ? AND s.status != 'voided'
    ");
    $stmtGlobal->execute([$buyer_id]);
    $global_stats = $stmtGlobal->fetch(PDO::FETCH_ASSOC);

    // Fetch sales with date filter
    $search = trim($_GET['search'] ?? '');
    $type = $_GET['type'] ?? 'sales'; // 'sales' or 'items'
    $payment_method = $_GET['payment_method'] ?? '';
    $payment_status = $_GET['payment_status'] ?? '';
    $exclude_voided = ($_GET['exclude_voided'] ?? '0') === '1';

    // Map UI values to DB values
    $db_payment_method = ($payment_method === 'paylater') ? 'pay_later' : $payment_method;
    $db_payment_status = ($payment_status === 'completed') ? 'paid' : $payment_status;

    if ($type === 'items') {
        // --- ITEM SEARCH MODE ---
        $sql = "SELECT i.sale_id, i.product_name, i.quantity, i.price_at_sale, i.subtotal, 
                       i.original_price, i.item_discount,
                       s.sale_ref, s.created_at, s.status, s.payment_method, s.payment_status,
                       ((i.price_at_sale - i.cost_at_sale) * i.quantity) as item_profit,
                       (SELECT COALESCE(SUM(paid_amount), 0) FROM pos_sale_payments WHERE sale_id = s.sale_id) as total_paid
                FROM pos_sale_items i
                JOIN pos_sales s ON i.sale_id = s.sale_id
                WHERE s.buyer_id = :buyer_id 
                  AND DATE(s.created_at) BETWEEN :date_from AND :date_to ";

        if ($exclude_voided) {
            $sql .= " AND s.status != 'voided' ";
        }

        if (!empty($search)) {
            $sql .= " AND (i.product_name LIKE :search 
                           OR i.brand_name LIKE :search
                           OR i.barcode LIKE :search
                           OR s.sale_ref LIKE :search) ";
        }

        if (!empty($db_payment_method)) {
            $sql .= " AND s.payment_method = :payment_method ";
        }
        if (!empty($db_payment_status)) {
            $subquery = "(SELECT COALESCE(SUM(paid_amount), 0) FROM pos_sale_payments WHERE sale_id = s.sale_id)";
            if ($db_payment_status === 'paid') {
                $sql .= " AND $subquery >= s.grand_total ";
            } else if ($db_payment_status === 'partial') {
                $sql .= " AND $subquery > 0 AND $subquery < s.grand_total ";
            } else if ($db_payment_status === 'unpaid') {
                $sql .= " AND $subquery <= 0 ";
            }
        }

        $sql .= " ORDER BY s.created_at DESC";

        $params = [
            ':buyer_id' => $buyer_id,
            ':date_from' => $date_from,
            ':date_to' => $date_to
        ];

        if (!empty($search)) {
            $params[':search'] = "%$search%";
        }
        if (!empty($db_payment_method)) {
            $params[':payment_method'] = $db_payment_method;
        }


        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate stats for items (sum of subtotals of non-voided items)
        $count = 0;
        $total = 0;
        $total_profit = 0;
        foreach ($results as $row) {
            if ($row['status'] !== 'voided') {
                $count++; // Count of distinct items
                $total += $row['subtotal'];
                $total_profit += $row['item_profit'];
            }
        }

        echo json_encode([
            'success' => true,
            'type' => 'items',
            'data' => $results,
            'stats' => [
                'count' => $count,
                'total' => $total,
                'profit' => $total_profit
            ],
            'global_stats' => $global_stats
        ]);

    } else {
        // --- DEFAULT SALES MODE ---
        $sql = "SELECT s.sale_id, s.sale_ref, s.created_at, s.grand_total, s.discount_amount, s.payment_method, s.payment_status, s.status,
                       SUM((i.price_at_sale - i.cost_at_sale) * i.quantity) as total_profit,
                       (SELECT COALESCE(SUM(paid_amount), 0) FROM pos_sale_payments WHERE sale_id = s.sale_id) as total_paid
                FROM pos_sales s 
                LEFT JOIN pos_sale_items i ON s.sale_id = i.sale_id ";

        $sql .= " WHERE s.buyer_id = :buyer_id 
                  AND DATE(s.created_at) BETWEEN :date_from AND :date_to ";

        if ($exclude_voided) {
            $sql .= " AND s.status != 'voided' ";
        }

        if (!empty($search)) {
            $sql .= " AND (s.sale_ref LIKE :search 
                           OR i.product_name LIKE :search 
                           OR i.brand_name LIKE :search
                           OR i.barcode LIKE :search) ";
        }

        if (!empty($db_payment_method)) {
            $sql .= " AND s.payment_method = :payment_method ";
        }
        if (!empty($db_payment_status)) {
            $subquery = "(SELECT COALESCE(SUM(paid_amount), 0) FROM pos_sale_payments WHERE sale_id = s.sale_id)";
            if ($db_payment_status === 'paid') {
                $sql .= " AND $subquery >= s.grand_total ";
            } else if ($db_payment_status === 'partial') {
                $sql .= " AND $subquery > 0 AND $subquery < s.grand_total ";
            } else if ($db_payment_status === 'unpaid') {
                $sql .= " AND $subquery <= 0 ";
            }
        }

        $sql .= " GROUP BY s.sale_id ORDER BY s.created_at DESC";

        $stmt = $conn->prepare($sql);

        $params = [
            ':buyer_id' => $buyer_id,
            ':date_from' => $date_from,
            ':date_to' => $date_to
        ];

        if (!empty($search)) {
            $params[':search'] = "%$search%";
        }
        if (!empty($db_payment_method)) {
            $params[':payment_method'] = $db_payment_method;
        }

        $stmt->execute($params);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate stats (exclude voided from totals)
        $count = 0;
        $total = 0;
        $total_profit = 0;
        foreach ($sales as $sale) {
            if ($sale['status'] !== 'voided') {
                $count++;
                $total += $sale['grand_total'];
                $total_profit += ($sale['total_profit'] ?? 0);
            }
        }

        echo json_encode([
            'success' => true,
            'type' => 'sales',
            'sales' => $sales, // Kept for backward compat, or client can use 'data' if we unified it
            'data' => $sales,
            'stats' => [
                'count' => $count,
                'total' => $total,
                'profit' => $total_profit
            ],
            'global_stats' => $global_stats
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
