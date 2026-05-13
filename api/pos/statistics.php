<?php
// api/pos/statistics.php - Detailed Statistics API
header("Content-Type: application/json");
require_once '../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Date range params
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');   // Default: 1st of this month
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');     // Default: today
    $period = isset($_GET['period']) ? $_GET['period'] : 'day';           // day, week, month

    // Validate dates
    $startDate = date('Y-m-d', strtotime($startDate));
    $endDate = date('Y-m-d', strtotime($endDate));
    $endDateNext = date('Y-m-d', strtotime($endDate . ' +1 day'));

    // Previous period for comparison (same duration before the start date)
    $daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400;
    $prevStart = date('Y-m-d', strtotime($startDate . " -" . ($daysDiff + 1) . " days"));
    $prevEnd = date('Y-m-d', strtotime($startDate . " -1 day"));
    $prevEndNext = date('Y-m-d', strtotime($prevEnd . ' +1 day'));

    // ============================================================
    // 0. EXPENSES - Current & Previous
    // ============================================================
    $expensesStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_expenses 
        FROM expenses 
        WHERE expense_date >= :start AND expense_date <= :end
    ");
    $expensesStmt->execute([':start' => $startDate, ':end' => $endDate]);
    $totalExpenses = floatval($expensesStmt->fetch(PDO::FETCH_ASSOC)['total_expenses']);

    $prevExpensesStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_expenses 
        FROM expenses 
        WHERE expense_date >= :start AND expense_date <= :end
    ");
    $prevExpensesStmt->execute([':start' => $prevStart, ':end' => $prevEnd]);
    $prevTotalExpenses = floatval($prevExpensesStmt->fetch(PDO::FETCH_ASSOC)['total_expenses']);

    // Recent Expenses
    $recentExpensesStmt = $conn->prepare("
        SELECT e.*, u.full_name as created_by_name
        FROM expenses e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.expense_date >= :start AND e.expense_date <= :end
        ORDER BY e.expense_date DESC, e.id DESC
        LIMIT 10
    ");
    $recentExpensesStmt->execute([':start' => $startDate, ':end' => $endDate]);
    $recentExpenses = $recentExpensesStmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================
    // 1. SUMMARY CARDS - Current Period
    // ============================================================
    $summaryStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(s.grand_total), 0) as total_revenue,
            COUNT(*) as total_transactions,
            COALESCE(AVG(s.grand_total), 0) as avg_order_value,
            COUNT(DISTINCT COALESCE(s.buyer_id, s.walkin_name)) as unique_customers
        FROM pos_sales s
        WHERE s.created_at >= :start AND s.created_at < :end
        AND s.status = 'completed'
    ");
    $summaryStmt->execute([':start' => $startDate, ':end' => $endDateNext]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    // Profit + Items Sold
    $profitStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(si.quantity - (
                SELECT COALESCE(SUM(ri.quantity), 0)
                FROM pos_return_items ri
                INNER JOIN pos_returns pr ON ri.return_id = pr.return_id
                WHERE ri.sale_item_id = si.sale_item_id AND pr.status = 'completed'
            )), 0) as items_sold,
            COALESCE(SUM((si.price_at_sale - si.cost_at_sale) * (si.quantity - (
                SELECT COALESCE(SUM(ri.quantity), 0)
                FROM pos_return_items ri
                INNER JOIN pos_returns pr ON ri.return_id = pr.return_id
                WHERE ri.sale_item_id = si.sale_item_id AND pr.status = 'completed'
            ))), 0) as total_profit
        FROM pos_sale_items si
        JOIN pos_sales s ON si.sale_id = s.sale_id
        WHERE s.created_at >= :start AND s.created_at < :end
        AND s.status = 'completed'
    ");
    $profitStmt->execute([':start' => $startDate, ':end' => $endDateNext]);
    $profitData = $profitStmt->fetch(PDO::FETCH_ASSOC);

    // ============================================================
    // 1b. SUMMARY CARDS - Previous Period (for comparison)
    // ============================================================
    $prevSummaryStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(s.grand_total), 0) as total_revenue,
            COUNT(*) as total_transactions,
            COALESCE(AVG(s.grand_total), 0) as avg_order_value,
            COUNT(DISTINCT COALESCE(s.buyer_id, s.walkin_name)) as unique_customers
        FROM pos_sales s
        WHERE s.created_at >= :start AND s.created_at < :end
        AND s.status = 'completed'
    ");
    $prevSummaryStmt->execute([':start' => $prevStart, ':end' => $startDate]);
    $prevSummary = $prevSummaryStmt->fetch(PDO::FETCH_ASSOC);

    $prevProfitStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(si.quantity), 0) as items_sold,
            COALESCE(SUM((si.price_at_sale - si.cost_at_sale) * si.quantity), 0) as total_profit
        FROM pos_sale_items si
        JOIN pos_sales s ON si.sale_id = s.sale_id
        WHERE s.created_at >= :start AND s.created_at < :end
        AND s.status = 'completed'
    ");
    $prevProfitStmt->execute([':start' => $prevStart, ':end' => $startDate]);
    $prevProfitData = $prevProfitStmt->fetch(PDO::FETCH_ASSOC);

    // ============================================================
    // 2. SALES OVER TIME
    // ============================================================
    if ($period === 'month') {
        $groupExpr = "DATE_FORMAT(s.created_at, '%Y-%m')";
        $labelExpr = "DATE_FORMAT(s.created_at, '%b %Y')";
    } elseif ($period === 'week') {
        $groupExpr = "YEARWEEK(s.created_at, 1)";
        $labelExpr = "CONCAT('Week ', WEEK(s.created_at, 1), ' ', YEAR(s.created_at))";
    } else {
        $groupExpr = "DATE(s.created_at)";
        $labelExpr = "DATE_FORMAT(s.created_at, '%b %d')";
    }

    $salesOverTimeStmt = $conn->prepare("
        SELECT 
            {$groupExpr} as period_key,
            {$labelExpr} as label,
            COALESCE(SUM(s.grand_total), 0) as revenue,
            COUNT(*) as transactions
        FROM pos_sales s
        WHERE s.created_at >= :start AND s.created_at < :end
        AND s.status = 'completed'
        GROUP BY period_key
        ORDER BY period_key ASC
    ");
    $salesOverTimeStmt->execute([':start' => $startDate, ':end' => $endDateNext]);
    $salesOverTime = $salesOverTimeStmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================
    // 3. PROFIT OVER TIME
    // ============================================================
    $profitOverTimeStmt = $conn->prepare("
        SELECT 
            {$groupExpr} as period_key,
            {$labelExpr} as label,
            COALESCE(SUM((si.price_at_sale - si.cost_at_sale) * (si.quantity - (
                SELECT COALESCE(SUM(ri.quantity), 0)
                FROM pos_return_items ri
                INNER JOIN pos_returns pr ON ri.return_id = pr.return_id
                WHERE ri.sale_item_id = si.sale_item_id AND pr.status = 'completed'
            ))), 0) as profit,
            COALESCE(SUM(s.grand_total), 0) as revenue
        FROM pos_sales s
        JOIN pos_sale_items si ON s.sale_id = si.sale_id
        WHERE s.created_at >= :start AND s.created_at < :end
        AND s.status = 'completed'
        GROUP BY period_key
        ORDER BY period_key ASC
    ");
    $profitOverTimeStmt->execute([':start' => $startDate, ':end' => $endDateNext]);
    $profitOverTime = $profitOverTimeStmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================
    // 4. SALES BY CATEGORY
    // ============================================================
    $categoryStmt = $conn->prepare("
        SELECT 
            COALESCE(c.category_name, 'Uncategorized') as category_name,
            COALESCE(c.color, '#6b7280') as color,
            COALESCE(SUM(si.price_at_sale * (si.quantity - (
                SELECT COALESCE(SUM(ri.quantity), 0)
                FROM pos_return_items ri
                INNER JOIN pos_returns pr ON ri.return_id = pr.return_id
                WHERE ri.sale_item_id = si.sale_item_id AND pr.status = 'completed'
            ))), 0) as revenue,
            COUNT(DISTINCT s.sale_id) as transactions
        FROM pos_sale_items si
        JOIN pos_sales s ON si.sale_id = s.sale_id
        LEFT JOIN product_variations pv ON si.variation_id = pv.variation_id
        LEFT JOIN products p ON pv.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE s.created_at >= :start AND s.created_at < :end
        AND s.status = 'completed'
        GROUP BY c.category_id
        ORDER BY revenue DESC
    ");
    $categoryStmt->execute([':start' => $startDate, ':end' => $endDateNext]);
    $categoryData = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================
    // 5. PAYMENT METHOD DISTRIBUTION
    // ============================================================
    $paymentStmt = $conn->prepare("
        SELECT 
            sp.payment_type,
            COUNT(*) as count,
            COALESCE(SUM(sp.amount), 0) as total_amount
        FROM pos_sale_payments sp
        JOIN pos_sales s ON sp.sale_id = s.sale_id
        WHERE s.created_at >= :start AND s.created_at < :end
        AND s.status = 'completed'
        GROUP BY sp.payment_type
        ORDER BY total_amount DESC
    ");
    $paymentStmt->execute([':start' => $startDate, ':end' => $endDateNext]);
    $paymentData = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================
    // 6. TOP 10 PRODUCTS (BY QTY + BY REVENUE)
    // ============================================================
    $topQtyStmt = $conn->prepare("
        SELECT 
            CONCAT(si.product_name, 
                CASE WHEN si.variation_name IS NOT NULL AND si.variation_name != '' 
                     THEN CONCAT(' (', si.variation_name, ')') ELSE '' END
            ) as product_name,
            SUM(si.quantity - (
                SELECT COALESCE(SUM(ri.quantity), 0)
                FROM pos_return_items ri
                INNER JOIN pos_returns pr ON ri.return_id = pr.return_id
                WHERE ri.sale_item_id = si.sale_item_id AND pr.status = 'completed'
            )) as total_qty,
            SUM(si.price_at_sale * (si.quantity - (
                SELECT COALESCE(SUM(ri.quantity), 0)
                FROM pos_return_items ri
                INNER JOIN pos_returns pr ON ri.return_id = pr.return_id
                WHERE ri.sale_item_id = si.sale_item_id AND pr.status = 'completed'
            ))) as total_revenue
        FROM pos_sale_items si
        JOIN pos_sales s ON si.sale_id = s.sale_id
        WHERE s.created_at >= :start AND s.created_at < :end
        AND s.status = 'completed'
        GROUP BY si.variation_id
        ORDER BY total_qty DESC
        LIMIT 10
    ");
    $topQtyStmt->execute([':start' => $startDate, ':end' => $endDateNext]);
    $topByQty = $topQtyStmt->fetchAll(PDO::FETCH_ASSOC);

    $topRevStmt = $conn->prepare("
        SELECT 
            CONCAT(si.product_name, 
                CASE WHEN si.variation_name IS NOT NULL AND si.variation_name != '' 
                     THEN CONCAT(' (', si.variation_name, ')') ELSE '' END
            ) as product_name,
            SUM(si.quantity - (
                SELECT COALESCE(SUM(ri.quantity), 0)
                FROM pos_return_items ri
                INNER JOIN pos_returns pr ON ri.return_id = pr.return_id
                WHERE ri.sale_item_id = si.sale_item_id AND pr.status = 'completed'
            )) as total_qty,
            SUM(si.price_at_sale * (si.quantity - (
                SELECT COALESCE(SUM(ri.quantity), 0)
                FROM pos_return_items ri
                INNER JOIN pos_returns pr ON ri.return_id = pr.return_id
                WHERE ri.sale_item_id = si.sale_item_id AND pr.status = 'completed'
            ))) as total_revenue
        FROM pos_sale_items si
        JOIN pos_sales s ON si.sale_id = s.sale_id
        WHERE s.created_at >= :start AND s.created_at < :end
        AND s.status = 'completed'
        GROUP BY si.variation_id
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $topRevStmt->execute([':start' => $startDate, ':end' => $endDateNext]);
    $topByRevenue = $topRevStmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================
    // 7. TOP 10 BUYERS
    // ============================================================
    $buyersStmt = $conn->prepare("
        SELECT 
            b.buyer_name,
            SUM(s.grand_total) as total_spent,
            COUNT(*) as transaction_count
        FROM pos_sales s
        JOIN buyers b ON s.buyer_id = b.buyer_id
        WHERE s.created_at >= :start AND s.created_at < :end
        AND s.status = 'completed'
        GROUP BY s.buyer_id
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $buyersStmt->execute([':start' => $startDate, ':end' => $endDateNext]);
    $topBuyers = $buyersStmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================
    // 8. CASHIER / USER PERFORMANCE
    // ============================================================
    $cashierStmt = $conn->prepare("
        SELECT 
            u.full_name as cashier_name,
            COUNT(*) as transaction_count,
            COALESCE(SUM(s.grand_total), 0) as total_sales
        FROM pos_sales s
        JOIN users u ON s.user_id = u.id
        WHERE s.created_at >= :start AND s.created_at < :end
        AND s.status = 'completed'
        GROUP BY s.user_id
        ORDER BY total_sales DESC
    ");
    $cashierStmt->execute([':start' => $startDate, ':end' => $endDateNext]);
    $cashierData = $cashierStmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================
    // 9. DAILY SALES HEATMAP (Day of Week x Hour)
    // ============================================================
    $heatmapStmt = $conn->prepare("
        SELECT 
            DAYOFWEEK(s.created_at) as dow,
            HOUR(s.created_at) as hour,
            COALESCE(SUM(s.grand_total), 0) as revenue,
            COUNT(*) as transactions
        FROM pos_sales s
        WHERE s.created_at >= :start AND s.created_at < :end
        AND s.status = 'completed'
        GROUP BY DAYOFWEEK(s.created_at), HOUR(s.created_at)
        ORDER BY dow, hour
    ");
    $heatmapStmt->execute([':start' => $startDate, ':end' => $endDateNext]);
    $heatmapRaw = $heatmapStmt->fetchAll(PDO::FETCH_ASSOC);

    // Build 7x24 matrix
    $heatmap = [];
    $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    for ($d = 1; $d <= 7; $d++) {
        $row = ['day' => $dayNames[$d - 1], 'hours' => []];
        for ($h = 0; $h < 24; $h++) {
            $row['hours'][$h] = 0;
        }
        $heatmap[$d] = $row;
    }
    foreach ($heatmapRaw as $r) {
        $heatmap[intval($r['dow'])]['hours'][intval($r['hour'])] = floatval($r['revenue']);
    }
    $heatmap = array_values($heatmap);

    // ============================================================
    // 10. INVENTORY VALUE
    // ============================================================
    $invValueStmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(i.quantity * pv.price_retail), 0) as retail_value,
            COALESCE(SUM(i.quantity * pv.price_capital), 0) as cost_value,
            COALESCE(SUM(i.quantity), 0) as total_units
        FROM inventory i
        JOIN product_variations pv ON i.variation_id = pv.variation_id
        WHERE pv.status = 'active' AND i.store_id = 1
    ");
    $invValueStmt->execute();
    $inventoryValue = $invValueStmt->fetch(PDO::FETCH_ASSOC);

    // ============================================================
    // Helper: Calculate % change
    // ============================================================
    function pctChange($current, $previous)
    {
        if ($previous == 0)
            return $current > 0 ? 100 : 0;
        return round((($current - $previous) / $previous) * 100, 1);
    }

    // ============================================================
    // SEND RESPONSE
    // ============================================================
    echo json_encode([
        'success' => true,
        'date_range' => [
            'start' => $startDate,
            'end' => $endDate,
            'period' => $period
        ],
        'summary' => [
            'total_revenue' => floatval($summary['total_revenue']),
            'total_transactions' => intval($summary['total_transactions']),
            'avg_order_value' => round(floatval($summary['avg_order_value']), 2),
            'total_profit' => floatval($profitData['total_profit']),
            'unique_customers' => intval($summary['unique_customers']),
            'items_sold' => intval($profitData['items_sold']),
            // Comparison with previous period
            'changes' => [
                'revenue' => pctChange($summary['total_revenue'], $prevSummary['total_revenue']),
                'transactions' => pctChange($summary['total_transactions'], $prevSummary['total_transactions']),
                'avg_order' => pctChange($summary['avg_order_value'], $prevSummary['avg_order_value']),
                'profit' => pctChange($profitData['total_profit'], $prevProfitData['total_profit']),
                'customers' => pctChange($summary['unique_customers'], $prevSummary['unique_customers']),
                'items' => pctChange($profitData['items_sold'], $prevProfitData['items_sold']),
                'expenses' => pctChange($totalExpenses, $prevTotalExpenses),
                'net_profit' => pctChange($profitData['total_profit'] - $totalExpenses, $prevProfitData['total_profit'] - $prevTotalExpenses)
            ],
            'total_expenses' => $totalExpenses,
            'net_profit' => $profitData['total_profit'] - $totalExpenses
        ],
        'sales_over_time' => $salesOverTime,
        'profit_over_time' => $profitOverTime,
        'recent_expenses' => $recentExpenses,
        'categories' => $categoryData,
        'payment_methods' => $paymentData,
        'top_products_qty' => $topByQty,
        'top_products_revenue' => $topByRevenue,
        'top_buyers' => $topBuyers,
        'cashier_performance' => $cashierData,
        'heatmap' => $heatmap,
        'inventory_value' => [
            'retail_value' => floatval($inventoryValue['retail_value']),
            'cost_value' => floatval($inventoryValue['cost_value']),
            'total_units' => intval($inventoryValue['total_units'])
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
