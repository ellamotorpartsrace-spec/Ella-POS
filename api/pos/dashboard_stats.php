<?php
// api/pos/dashboard_stats.php - Dashboard statistics API
header("Content-Type: application/json");
require_once '../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Today's Sales Stats
    $todayStmt = $conn->prepare("
        SELECT 
            COUNT(*) as transaction_count,
            COALESCE(SUM(grand_total), 0) as total_sales
        FROM pos_sales 
        WHERE DATE(created_at) = CURDATE() 
        AND status = 'completed'
    ");
    $todayStmt->execute();
    $todayStats = $todayStmt->fetch();

    $profitStmt = $conn->prepare("
        SELECT COALESCE(SUM((si.price_at_sale - si.cost_at_sale) * si.quantity), 0) as gross_profit
        FROM pos_sale_items si
        JOIN pos_sales s ON si.sale_id = s.sale_id
        WHERE DATE(s.created_at) = CURDATE() 
        AND s.status = 'completed'
    ");
    $profitStmt->execute();
    $todayGrossProfit = $profitStmt->fetch()['gross_profit'];

    $expenseStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_expenses
        FROM expenses
        WHERE expense_date = CURDATE()
    ");
    $expenseStmt->execute();
    $todayExpenses = $expenseStmt->fetch()['total_expenses'];


    // 2. Weekly Sales Trend (Last 7 Days)
    $weeklyStmt = $conn->prepare("
        SELECT 
            DATE(created_at) as sale_date,
            COALESCE(SUM(grand_total), 0) as daily_total,
            COUNT(*) as transaction_count
        FROM pos_sales 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        AND status = 'completed'
        GROUP BY DATE(created_at)
        ORDER BY sale_date ASC
    ");
    $weeklyStmt->execute();
    $weeklyData = $weeklyStmt->fetchAll();

    // Fill in missing days with zero
    $weeklyTrend = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayName = date('D', strtotime($date));
        $found = false;
        foreach ($weeklyData as $row) {
            if ($row['sale_date'] === $date) {
                $weeklyTrend[] = [
                    'date' => $date,
                    'day' => $dayName,
                    'total' => floatval($row['daily_total']),
                    'count' => intval($row['transaction_count'])
                ];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $weeklyTrend[] = [
                'date' => $date,
                'day' => $dayName,
                'total' => 0,
                'count' => 0
            ];
        }
    }

    // 3. Top 10 Selling Products (Last 30 Days) - BY QUANTITY
    $topProductsQtyStmt = $conn->prepare("
        SELECT 
            IF(si.variation_name IS NOT NULL AND si.variation_name != '', 
               CONCAT(si.product_name, ' (', si.variation_name, ')'), 
               si.product_name) as product_name,
            SUM(si.quantity) as total_qty,
            SUM(si.subtotal) as total_revenue
        FROM pos_sale_items si
        JOIN pos_sales s ON si.sale_id = s.sale_id
        WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND s.status = 'completed'
        GROUP BY si.variation_id
        ORDER BY total_qty DESC
        LIMIT 10
    ");
    $topProductsQtyStmt->execute();
    $topProductsQty = $topProductsQtyStmt->fetchAll();

    // 3.1 Top 10 Selling Products (Last 30 Days) - BY REVENUE
    $topProductsRevStmt = $conn->prepare("
        SELECT 
            IF(si.variation_name IS NOT NULL AND si.variation_name != '', 
               CONCAT(si.product_name, ' (', si.variation_name, ')'), 
               si.product_name) as product_name,
            SUM(si.quantity) as total_qty,
            SUM(si.subtotal) as total_revenue
        FROM pos_sale_items si
        JOIN pos_sales s ON si.sale_id = s.sale_id
        WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND s.status = 'completed'
        GROUP BY si.variation_id
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $topProductsRevStmt->execute();
    $topProductsRev = $topProductsRevStmt->fetchAll();

    // 4. Low Stock Items
    $lowStockStmt = $conn->prepare("
        SELECT 
            p.product_name,
            p.brand_name,
            v.variation_name,
            (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE variation_id = v.variation_id AND store_id = 1) as current_stock,
            v.low_stock_threshold
        FROM product_variations v
        JOIN products p ON v.product_id = p.product_id
        WHERE v.status = 'active'
        AND (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE variation_id = v.variation_id AND store_id = 1) <= v.low_stock_threshold
        ORDER BY current_stock ASC
        LIMIT 10
    ");
    $lowStockStmt->execute();
    $lowStockItems = $lowStockStmt->fetchAll();

    // 5. Payment Method Distribution (Last 30 Days)
    $paymentStmt = $conn->prepare("
        SELECT 
            payment_type,
            COUNT(*) as count,
            COALESCE(SUM(amount), 0) as total_amount
        FROM pos_sale_payments sp
        JOIN pos_sales s ON sp.sale_id = s.sale_id
        WHERE s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND s.status = 'completed'
        GROUP BY payment_type
    ");
    $paymentStmt->execute();
    $paymentMethods = $paymentStmt->fetchAll();

    // 6. Total Product Count
    $productCountStmt = $conn->query("SELECT COUNT(*) as count FROM product_variations WHERE status = 'active'");
    $productCount = $productCountStmt->fetch()['count'];

    // 7. Pending Receivables
    $receivablesStmt = $conn->prepare("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(amount - paid_amount), 0) as total_pending
        FROM pos_sale_payments
        WHERE payment_status = 'pending'
    ");
    $receivablesStmt->execute();
    $receivables = $receivablesStmt->fetch();

    // 8. Top 10 Buyers (Current Month, Exclude Walk-ins)
    $topBuyersStmt = $conn->prepare("
        SELECT 
            CASE 
                WHEN s.walkin_name IS NOT NULL AND s.walkin_name != '' THEN s.walkin_name 
                ELSE 'Unknown Customer'
            END as buyer_name,
            SUM(s.grand_total) as total_spent,
            COUNT(*) as transaction_count
        FROM pos_sales s
        WHERE s.buyer_id IS NULL OR s.buyer_id = 0 
           OR s.walkin_name NOT IN ('Walk-in Customer', 'Walk-in', '')
        AND MONTH(s.created_at) = MONTH(CURDATE()) 
        AND YEAR(s.created_at) = YEAR(CURDATE())
        AND s.status = 'completed'
        GROUP BY s.walkin_name
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    /* 
       Wait, the requirement is "not walk in". 
       Usually "walk in" has buyer_id NULL or 0. Registered buyers have buyer_id > 0.
       Let's correct the query to target registered buyers (buyer_id > 0).
    */
    // 8. Top 10 Buyers (Filtered by Month/Year, Exclude Walk-ins)
    $filterMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
    $filterYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

    $topBuyersStmt = $conn->prepare("
        SELECT 
            b.buyer_name,
            SUM(s.grand_total) as total_spent,
            COUNT(*) as transaction_count
        FROM pos_sales s
        JOIN buyers b ON s.buyer_id = b.buyer_id
        WHERE s.buyer_id > 0
        AND MONTH(s.created_at) = :month 
        AND YEAR(s.created_at) = :year
        AND s.status = 'completed'
        GROUP BY s.buyer_id
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $topBuyersStmt->execute([
        ':month' => $filterMonth,
        ':year' => $filterYear
    ]);
    $topBuyers = $topBuyersStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'today' => [
            'sales' => floatval($todayStats['total_sales']),
            'transactions' => intval($todayStats['transaction_count']),
            'gross_profit' => floatval($todayGrossProfit),
            'expenses' => floatval($todayExpenses),
            'net_profit' => floatval($todayGrossProfit) - floatval($todayExpenses)
        ],
        'weekly_trend' => $weeklyTrend,
        'top_products' => $topProductsQty, // Keep for backward compatibility if needed, or just use top_products_qty
        'top_products_qty' => $topProductsQty,
        'top_products_revenue' => $topProductsRev,
        'top_buyers' => $topBuyers,
        'low_stock' => [
            'count' => count($lowStockItems),
            'items' => $lowStockItems
        ],
        'payment_methods' => $paymentMethods,
        'total_products' => intval($productCount),
        'receivables' => [
            'count' => intval($receivables['count']),
            'total' => floatval($receivables['total_pending'])
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
