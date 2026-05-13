<?php
// api/pos/save_simple_sale.php
header("Content-Type: application/json");
require_once '../../config/database.php';
require_once '../../includes/logger.php';
session_start();

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['cart'])) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    $cart = $data['cart'];
    $payment = $data['payment'];
    $buyer = $data['buyer'];
    $saleRef = 'POS-' . date('YmdHis') . '-' . rand(100, 999);
    $userId = $_SESSION['user_id'] ?? 1; // Default to admin if no session

    $status = ($payment['method'] === 'pay_later') ? 'not_completed' : 'completed';

    // 1. Insert Header (pos_sales)
    $stmt = $conn->prepare("INSERT INTO pos_sales 
        (sale_ref, user_id, buyer_id, walkin_name, grand_total, amount_tendered, change_due, payment_status, status) 
        VALUES (:ref, :uid, :bid, :wname, :total, :tendered, :change, 'paid', :status)");

    $stmt->execute([
        ':ref' => $saleRef,
        ':uid' => $userId,
        ':bid' => $buyer['buyer_id'] ?? null,
        ':wname' => $buyer['buyer_name'],
        ':total' => $data['total'],
        ':tendered' => $payment['amount'],
        ':change' => $payment['change'],
        ':status' => $status
    ]);
    $saleId = $conn->lastInsertId();

    // 2. Prepare Item Statements
    $stmtItem = $conn->prepare("INSERT INTO pos_sale_items 
        (sale_id, variation_id, unit_id, multiplier, product_name, price_at_sale, cost_at_sale, quantity, subtotal) 
        VALUES (:sid, :vid, :uid, :mult, :pname, :price, :cost, :qty, :sub)");

    $stmtStock = $conn->prepare("UPDATE inventory SET quantity = quantity - :qty WHERE variation_id = :vid AND store_id = 1");

    // Stock movement query for audit trail
    $stmtMove = $conn->prepare("INSERT INTO stock_movements 
                (store_id, variation_id, type, quantity, previous_stock, new_stock, reference, remarks, created_by, capital_cost)
                SELECT 1, :var_id, 'sales', :qty, i.quantity, i.quantity - :qty2, :ref, :remarks, :user_id, :cost
                FROM inventory i WHERE i.variation_id = :var_id2 AND i.store_id = 1");

    // Fetch cost prices — prefer unit capital, fall back to variation capital
    $stmtCostUnit = $conn->prepare("SELECT price_capital FROM product_units WHERE id = ?");
    $stmtCostVar = $conn->prepare("SELECT price_capital FROM product_variations WHERE variation_id = ?");

    foreach ($cart as $item) {
        // Get Capital Price — use unit's capital if item is a custom unit
        $unitId = isset($item['unit_id']) && intval($item['unit_id']) > 0 ? (int) $item['unit_id'] : null;
        if ($unitId) {
            $stmtCostUnit->execute([$unitId]);
            $cost = $stmtCostUnit->fetchColumn();
            if (!$cost) {
                $stmtCostVar->execute([$item['variation_id']]);
                $cost = $stmtCostVar->fetchColumn() ?: 0;
            }
        } else {
            $stmtCostVar->execute([$item['variation_id']]);
            $cost = $stmtCostVar->fetchColumn() ?: 0;
        }

        $qty = (int) $item['qty'];
        $multiplier = isset($item['multiplier']) ? (int) $item['multiplier'] : 1;
        $totalDeducted = $qty * $multiplier;

        $stmtItem->execute([
            ':sid' => $saleId,
            ':vid' => $item['variation_id'],
            ':uid' => $item['unit_id'] ?? null,
            ':mult' => $multiplier,
            ':pname' => $item['name'] . ' (' . $item['variation'] . ')',
            ':price' => $item['price'],
            ':cost' => $cost,
            ':qty' => $qty,
            ':sub' => $item['price'] * $qty
        ]);

        // 3. Log stock movement for audit
        $stmtMove->execute([
            ':var_id' => $item['variation_id'],
            ':qty' => $totalDeducted,
            ':qty2' => $totalDeducted,
            ':ref' => $saleRef,
            ':remarks' => 'Quick Sale: ' . $item['name'],
            ':user_id' => $userId,
            ':var_id2' => $item['variation_id'],
            ':cost' => $cost
        ]);

        $stmtStock->execute([
            ':qty' => $totalDeducted,
            ':vid' => $item['variation_id']
        ]);
    }

    // 3. Insert Payment (pos_sale_payments)

    // Explicitly set status to prevent default 'pending' for cash sales
    $payStatus = ($payment['method'] === 'pay_later') ? 'pending' : 'paid';
    $paidAmount = ($payment['method'] === 'pay_later') ? 0.00 : $payment['amount'];

    $stmtPay = $conn->prepare("INSERT INTO pos_sale_payments 
        (sale_id, payment_type, amount, paid_amount, payment_status, reference_no) 
        VALUES (:sid, :type, :amt, :pamt, :pstat, :ref)");

    $stmtPay->execute([
        ':sid' => $saleId,
        ':type' => $payment['method'],
        ':amt' => $payment['amount'],
        ':pamt' => $paidAmount,
        ':pstat' => strtolower(trim($payStatus)),
        ':ref' => $saleRef
    ]);

    $conn->commit();

    // Log Activity
    logActivity($conn, $userId, 'SALE_COMPLETED', 'POS', "Processed quick sale $saleRef for " . number_format($data['total'], 2), $saleId);

    echo json_encode(['success' => true, 'sale_id' => $saleId]);
} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
