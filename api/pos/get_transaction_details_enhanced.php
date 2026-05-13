<?php
// api/pos/get_transaction_details_enhanced.php - Get full transaction details
header("Content-Type: application/json");
require_once '../../config/database.php';

$sale_id = $_GET['id'] ?? null;

if (!$sale_id) {
    echo json_encode(['error' => 'Missing Sale ID']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Fetch Sale Header
    $sqlSale = "
        SELECT 
            s.sale_id, 
            s.sale_ref, 
            s.created_at,
            s.buyer_id,
            s.walkin_name,
            s.buyer_shop_name,
            s.buyer_address,
            s.buyer_contact,
            s.price_tier,
            s.subtotal,
            s.tax_amount,
            s.discount_amount,
            s.grand_total,
            s.amount_tendered,
            s.change_due,
            s.payment_status,
            s.payment_method,
            s.status,
            s.remarks,
            COALESCE(s.walkin_name, b.buyer_name, 'Walk-in') as customer_name,
            COALESCE(s.buyer_shop_name, b.shop_name) as shop_name,
            u.full_name as cashier_name
        FROM pos_sales s
        LEFT JOIN buyers b ON s.buyer_id = b.buyer_id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.sale_id = ?
    ";
    $stmt = $conn->prepare($sqlSale);
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        throw new Exception("Transaction not found");
    }

    // 2. Fetch Items
    $sqlItems = "
        SELECT 
            si.sale_item_id,
            si.variation_id,
            si.product_name,
            si.brand_name,
            si.variation_name,
            si.unit_type,
            si.barcode,
            si.price_at_sale,
            si.original_price,
            pv.sku,
            pv.price_retail,
            pv.price_wholesale,
            pv.price_dealer,
            si.item_discount,
            si.cost_at_sale,
            si.quantity,
            si.subtotal,
            (
                SELECT COALESCE(SUM(ri.quantity), 0)
                FROM pos_return_items ri
                INNER JOIN pos_returns pr ON ri.return_id = pr.return_id
                WHERE ri.sale_item_id = si.sale_item_id AND pr.status = 'completed'
            ) as returned_quantity,
            ((si.price_at_sale - si.cost_at_sale) * (si.quantity - (
                SELECT COALESCE(SUM(ri.quantity), 0)
                FROM pos_return_items ri
                INNER JOIN pos_returns pr ON ri.return_id = pr.return_id
                WHERE ri.sale_item_id = si.sale_item_id AND pr.status = 'completed'
            ))) as item_profit
        FROM pos_sale_items si
        LEFT JOIN product_variations pv ON si.variation_id = pv.variation_id
        WHERE si.sale_id = ?
        ORDER BY si.sale_item_id
    ";
    $stmtItems = $conn->prepare($sqlItems);
    $stmtItems->execute([$sale_id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Payment Info
    $sqlPay = "
        SELECT payment_type, amount, due_date, reference_no, payment_status, financing_provider
        FROM pos_sale_payments 
        WHERE sale_id = ?
        ORDER BY payment_id
    ";
    $stmtPay = $conn->prepare($sqlPay);
    $stmtPay->execute([$sale_id]);
    $payments = $stmtPay->fetchAll(PDO::FETCH_ASSOC);

    // 3b. Fetch Wallet Logs for this sale (from buyer_wallet_logs)
    $walletSummary = [
        'supplement_used' => 0,
        'saved_to_wallet'  => 0,
        'paid_by_wallet'   => 0,
        'shortfall_deducted' => 0,
    ];

    if ($sale['buyer_id']) {
        $sqlWallet = "
            SELECT type, amount, remarks
            FROM buyer_wallet_logs
            WHERE reference_type = 'sale' AND reference_id = ?
            ORDER BY id
        ";
        $stmtW = $conn->prepare($sqlWallet);
        $stmtW->execute([$sale['sale_ref']]);
        $walletLogs = $stmtW->fetchAll(PDO::FETCH_ASSOC);

        foreach ($walletLogs as $wl) {
            $remarks = strtolower($wl['remarks'] ?? '');
            $amt = (float)$wl['amount'];
            if (str_contains($remarks, 'supplement')) {
                $walletSummary['supplement_used'] += $amt;
            } elseif (str_contains($remarks, 'saved change')) {
                $walletSummary['saved_to_wallet'] += $amt;
            } elseif (str_contains($remarks, 'wallet payment for sale')) {
                $walletSummary['paid_by_wallet'] += $amt;
            } elseif (str_contains($remarks, 'shortfall')) {
                $walletSummary['shortfall_deducted'] += $amt;
            }
        }
    }

    // 4. Build receipt data for printing
    // Calculate net item total to derive the true global discount (transaction discount)
    // global_discount = (sum of items net subtotal) - (grand_total)
    $netItemsTotal = 0;
    foreach ($items as $item) {
        $netItemsTotal += (float)$item['price_at_sale'] * (int)$item['quantity'];
    }
    
    $grandTotal = (float)$sale['grand_total'];
    $globalDiscount = max(0, $netItemsTotal - $grandTotal);

    $receiptData = [
        'cart' => array_map(function ($item) {
            return [
                'name' => $item['product_name'],
                'brand' => $item['brand_name'],
                'variation' => $item['variation_name'],
                'unit_type' => $item['unit_type'],
                'sku' => $item['sku'] ?? '',
                'qty' => (int) $item['quantity'],
                'returned_qty' => (int) $item['returned_quantity'],
                'price' => (float) $item['price_at_sale'],
                'original_price' => $item['original_price'] ? (float) $item['original_price'] : null,
                'item_discount' => $item['item_discount'] ? (float) $item['item_discount'] : 0
            ];
        }, $items),
        'buyer' => [
            'name' => $sale['customer_name'],
            'shop' => $sale['shop_name'] ?? '',
            'address' => $sale['buyer_address'] ?? '',
            'price_tier' => $sale['price_tier'] ?? 'retail'
        ],
        'payment' => [
            'method'             => $sale['payment_method'] ?? 'cash',
            'amount'             => $sale['amount_tendered'],
            'change'             => $sale['change_due'],
            'reference'          => $sale['sale_ref'],
            'wallet_supplement'  => $walletSummary['supplement_used'],
            'saved_to_wallet'    => $walletSummary['saved_to_wallet'],
            'paid_by_wallet'     => $walletSummary['paid_by_wallet'],
            'shortfall_deducted' => $walletSummary['shortfall_deducted'],
            'financing_provider' => (function () use ($payments) {
                foreach ($payments as $p) {
                    if (!empty($p['financing_provider']))
                        return $p['financing_provider'];
                }
                return null;
            })(),
            'mix_details' => in_array($sale['payment_method'], ['mix', 'financing', 'home_credit']) ? array_map(function ($p) {
                return [
                    'method'   => $p['payment_type'] ?? '',
                    'amount'   => isset($p['amount']) ? (float) $p['amount'] : 0.0,
                    'ref'      => $p['reference_no'] ?? null,
                    'provider' => $p['financing_provider'] ?? null
                ];
            }, $payments) : []
        ],
        'user' => $sale['cashier_name'] ?? 'Staff',
        'globalDiscount' => $globalDiscount,
        'date' => $sale['created_at']
    ];

    // Calculate total cost and profit
    $total_cost = 0;
    $total_profit = 0;
    foreach ($items as $item) {
        $total_cost += (float) $item['cost_at_sale'] * (int) $item['quantity'];
        $total_profit += (float) $item['item_profit'];
    }

    $profit_margin = $sale['grand_total'] > 0 ? ($total_profit / $sale['grand_total']) * 100 : 0;

    echo json_encode([
        'success'       => true,
        'sale'          => $sale,
        'items'         => $items,
        'payments'      => $payments,
        'wallet_summary'=> $walletSummary,
        'receiptData'   => $receiptData,
        'total_cost'    => $total_cost,
        'total_profit'  => $total_profit,
        'profit_margin' => $profit_margin
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
