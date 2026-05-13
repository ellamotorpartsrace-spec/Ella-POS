<?php
// api/inventory/product_transaction_history.php
// Returns all sales transactions for a specific product variation
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';

$variation_id = intval($_GET['variation_id'] ?? 0);
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

if (!$variation_id) {
    echo json_encode(['success' => false, 'error' => 'Missing variation_id']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Fetch product info
    $sqlProduct = "
        SELECT 
            v.variation_id,
            p.product_id,
            p.product_name,
            p.brand_name,
            p.image_path,
            v.variation_name,
            v.sku,
            v.barcode,
            v.unit_type,
            v.price_retail,
            v.price_wholesale,
            v.price_dealer,
            COALESCE(i.quantity, 0) AS current_stock
        FROM product_variations v
        INNER JOIN products p ON v.product_id = p.product_id
        LEFT JOIN inventory i ON v.variation_id = i.variation_id AND i.store_id = 1
        WHERE v.variation_id = ?
    ";
    $stmtProduct = $conn->prepare($sqlProduct);
    $stmtProduct->execute([$variation_id]);
    $product = $stmtProduct->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit;
    }

    // 2. Fetch all transactions for this variation
    $sqlTransactions = "
        SELECT 
            si.sale_item_id,
            si.sale_id,
            si.price_at_sale,
            si.original_price,
            si.cost_at_sale,
            si.item_discount,
            si.quantity,
            si.subtotal,
            ((si.price_at_sale - si.cost_at_sale) * si.quantity) as item_profit,
            s.sale_ref,
            s.created_at,
            s.status,
            s.price_tier,
            s.payment_method,
            COALESCE(s.walkin_name, b.buyer_name, 'Walk-in') as customer_name,
            COALESCE(s.buyer_shop_name, b.shop_name) as shop_name
        FROM pos_sale_items si
        INNER JOIN pos_sales s ON si.sale_id = s.sale_id
        LEFT JOIN buyers b ON s.buyer_id = b.buyer_id
        WHERE si.variation_id = ?
        AND DATE(s.created_at) BETWEEN ? AND ?
        ORDER BY s.created_at DESC
    ";
    $stmtTx = $conn->prepare($sqlTransactions);
    $stmtTx->execute([$variation_id, $date_from, $date_to]);
    $transactions = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

    // 3. Calculate summary stats (excluding voided)
    $totalQty = 0;
    $totalRevenue = 0;
    $totalProfit = 0;
    $prices = [];
    $txCount = 0;

    foreach ($transactions as $t) {
        if ($t['status'] !== 'voided') {
            $totalQty += intval($t['quantity']);
            $totalRevenue += floatval($t['subtotal']);
            $totalProfit += floatval($t['item_profit']);
            $prices[] = floatval($t['price_at_sale']);
            $txCount++;
        }
    }

    $stats = [
        'total_qty_sold' => $totalQty,
        'total_revenue' => $totalRevenue,
        'total_profit' => $totalProfit,
        'transaction_count' => $txCount,
        'avg_price' => $txCount > 0 ? $totalRevenue / $totalQty : 0,
        'min_price' => !empty($prices) ? min($prices) : 0,
        'max_price' => !empty($prices) ? max($prices) : 0,
    ];

    // 4. Suggestions: Frequently bought together (top 5)
    $sqlTogether = "
        SELECT 
            si2.product_name,
            si2.brand_name,
            si2.variation_name,
            COUNT(*) as times_together,
            SUM(si2.quantity) as total_qty
        FROM pos_sale_items si
        INNER JOIN pos_sales s ON si.sale_id = s.sale_id
        INNER JOIN pos_sale_items si2 ON si.sale_id = si2.sale_id AND si2.variation_id != si.variation_id
        WHERE si.variation_id = ?
        AND DATE(s.created_at) BETWEEN ? AND ?
        AND s.status != 'voided'
        GROUP BY si2.product_name, si2.brand_name, si2.variation_name
        ORDER BY times_together DESC
        LIMIT 5
    ";
    $stmtTogether = $conn->prepare($sqlTogether);
    $stmtTogether->execute([$variation_id, $date_from, $date_to]);
    $boughtTogether = $stmtTogether->fetchAll(PDO::FETCH_ASSOC);

    // 5. Suggestions: Best customer (who bought the most of this product)
    $sqlBestCustomer = "
        SELECT 
            COALESCE(s.walkin_name, b.buyer_name, 'Walk-in') as customer_name,
            COALESCE(s.buyer_shop_name, b.shop_name) as shop_name,
            SUM(si.quantity) as total_qty,
            SUM(si.subtotal) as total_spent,
            COUNT(*) as purchase_count
        FROM pos_sale_items si
        INNER JOIN pos_sales s ON si.sale_id = s.sale_id
        LEFT JOIN buyers b ON s.buyer_id = b.buyer_id
        WHERE si.variation_id = ?
        AND DATE(s.created_at) BETWEEN ? AND ?
        AND s.status != 'voided'
        GROUP BY customer_name, shop_name
        ORDER BY total_qty DESC
        LIMIT 5
    ";
    $stmtBest = $conn->prepare($sqlBestCustomer);
    $stmtBest->execute([$variation_id, $date_from, $date_to]);
    $bestCustomers = $stmtBest->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'product' => $product,
        'transactions' => $transactions,
        'stats' => $stats,
        'suggestions' => [
            'bought_together' => $boughtTogether,
            'best_customers' => $bestCustomers
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
