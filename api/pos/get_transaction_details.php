<?php
// api/pos/get_transaction_details.php
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

    // 1. Fetch Sale Header Information
    // Uses: sale_ref, grand_total, walkin_name, amount_tendered, change_due
    $sqlSale = "
        SELECT 
            s.sale_id, s.sale_ref, s.created_at, s.walkin_name,
            s.grand_total, s.amount_tendered, s.change_due,
            u.username as cashier
        FROM pos_sales s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.sale_id = ?
    ";
    $stmt = $conn->prepare($sqlSale);
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        throw new Exception("Transaction record not found.");
    }

    // 2. Fetch Items and Connect to Product/Variation for Brand & Unit
    // This JOIN fixes the 'lack of connection' you noticed
    $sqlItems = "
        SELECT 
            p.brand_name as brand,
            si.product_name as name,
            v.variation_name as variation,
            v.unit_type as unit,
            si.quantity as qty,
            si.price_at_sale as price
        FROM pos_sale_items si
        JOIN product_variations v ON si.variation_id = v.variation_id
        JOIN products p ON v.product_id = p.product_id
        WHERE si.sale_id = ?
    ";
    $stmtItems = $conn->prepare($sqlItems);
    $stmtItems->execute([$sale_id]);
    $cart = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // 3. Fetch Payment Details
    $sqlPay = "
        SELECT payment_type as method, amount, reference_no as reference
        FROM pos_sale_payments 
        WHERE sale_id = ? 
    ";
    $stmtPay = $conn->prepare($sqlPay);
    $stmtPay->execute([$sale_id]);
    $payments = $stmtPay->fetchAll(PDO::FETCH_ASSOC);

    $paymentData = $payments[0] ?? ['method' => 'cash', 'amount' => 0, 'reference' => null];
    $mixDetails = [];
    if (($paymentData['method'] ?? 'cash') === 'mix') {
        foreach ($payments as $p) {
            $mixDetails[] = ['method' => $p['method'], 'amount' => (float) $p['amount']];
        }
    }

    // 4. Construct response for receipt-preview.js
    echo json_encode([
        'cart' => $cart,
        'buyer' => [
            'name' => $sale['walkin_name'] ?: 'Walk-in Customer'
        ],
        'payment' => [
            'method' => $paymentData['method'] ?? 'cash',
            'amount' => $sale['amount_tendered'],
            'change' => $sale['change_due'],
            'reference' => $paymentData['reference'] ?? $sale['sale_ref'],
            'mix_details' => $mixDetails
        ],
        'user' => $sale['cashier'] ?: 'Admin'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}