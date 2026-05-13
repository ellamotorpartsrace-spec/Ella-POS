<?php
// api/pos/adjust_transaction.php
// Atomically adjusts a completed sale by adding or returning items

header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/logger.php';

requireLogin();

// Permission check - usually admin or manager
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$sale_id = intval($input['sale_id'] ?? 0);
$return_items = $input['return_items'] ?? []; // [{sale_item_id, quantity, variation_id, refund_amount}]
$add_items = $input['add_items'] ?? [];       // [{variation_id, quantity, price, multiplier, unit_id, product_name, brand_name, variation_name, unit_type, barcode}]
$reason = trim($input['reason'] ?? 'Adjustment');
$payment_method = $input['payment_method'] ?? 'cash';
$user_id = $_SESSION['user_id'];

if (!$sale_id || (empty($return_items) && empty($add_items))) {
    echo json_encode(['success' => false, 'error' => 'Missing sale_id or adjustment items']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    // 1. Verify sale exists
    $stmtSale = $conn->prepare("SELECT sale_id, sale_ref, status, subtotal, grand_total, buyer_id FROM pos_sales WHERE sale_id = ?");
    $stmtSale->execute([$sale_id]);
    $sale = $stmtSale->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        throw new Exception("Sale not found.");
    }
    if ($sale['status'] === 'voided') {
        throw new Exception("Cannot adjust a voided sale.");
    }

    $netDifference = 0;
    $adjustmentDetails = [];

    // Prepare all common statements up front
    $stmtGetStock = $conn->prepare("SELECT quantity FROM inventory WHERE variation_id = ? AND store_id = 1");
    $stmtRestoreStock = $conn->prepare("UPDATE inventory SET quantity = quantity + ? WHERE variation_id = ? AND store_id = 1");
    $stmtDeductStock = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE variation_id = ? AND store_id = 1");
    $stmtStockMove = $conn->prepare("INSERT INTO stock_movements (variation_id, type, quantity, previous_stock, new_stock, reference, remarks, created_by, store_id, capital_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
    $stmtGetCapital = $conn->prepare("SELECT price_capital FROM product_variations WHERE variation_id = ?");

    // --- 2. PROCESS RETURNS ---
    if (!empty($return_items)) {
        // Load original sale items
        $stmtOrigItems = $conn->prepare("SELECT sale_item_id, variation_id, quantity, price_at_sale, multiplier, cost_at_sale FROM pos_sale_items WHERE sale_id = ?");
        $stmtOrigItems->execute([$sale_id]);
        $origItemsMap = [];
        foreach ($stmtOrigItems->fetchAll(PDO::FETCH_ASSOC) as $oi) {
            $origItemsMap[$oi['sale_item_id']] = $oi;
        }

        // Load already returned qty
        $stmtAlreadyReturned = $conn->prepare("
            SELECT ri.sale_item_id, SUM(ri.quantity) AS total_returned
            FROM pos_return_items ri
            INNER JOIN pos_returns r ON ri.return_id = r.return_id
            WHERE r.sale_id = ? AND r.status = 'completed'
            GROUP BY ri.sale_item_id
        ");
        $stmtAlreadyReturned->execute([$sale_id]);
        $returnedMap = [];
        foreach ($stmtAlreadyReturned->fetchAll(PDO::FETCH_ASSOC) as $ar) {
            $returnedMap[$ar['sale_item_id']] = (int) $ar['total_returned'];
        }

        $totalRefund = 0;
        $validatedReturns = [];

        foreach ($return_items as $ri) {
            $id = intval($ri['sale_item_id']);
            $qty = intval($ri['quantity']);
            if ($qty <= 0)
                continue;

            if (!isset($origItemsMap[$id]))
                throw new Exception("Item $id not found in original sale.");

            $orig = $origItemsMap[$id];
            $already = $returnedMap[$id] ?? 0;
            $max = $orig['quantity'] - $already;

            if ($qty > $max)
                throw new Exception("Cannot return $qty. Only $max returnable for item $id.");

            $price = floatval($orig['price_at_sale']);
            $cost = floatval($orig['cost_at_sale']);
            $refund = $price * $qty;
            $totalRefund += $refund;

            $validatedReturns[] = [
                'sale_item_id' => $id,
                'variation_id' => $orig['variation_id'],
                'quantity' => $qty,
                'multiplier' => $orig['multiplier'],
                'refund_amount' => $refund,
                'cost' => $cost
            ];
        }

        if (!empty($validatedReturns)) {
            // Insert Return Header
            $returnRef = 'RET-ADJ-' . date('YmdHis') . '-' . rand(100, 999);
            $stmtInsertReturn = $conn->prepare("INSERT INTO pos_returns (return_ref, sale_id, user_id, reason, refund_method, refund_amount, status) VALUES (?, ?, ?, ?, ?, ?, 'completed')");
            $stmtInsertReturn->execute([$returnRef, $sale_id, $user_id, $reason, $payment_method, $totalRefund]);
            $returnId = $conn->lastInsertId();

            // Insert Return Items & Restore Stock
            $stmtInsertRi = $conn->prepare("INSERT INTO pos_return_items (return_id, sale_item_id, variation_id, quantity, price_at_sale, refund_amount) VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($validatedReturns as $vr) {
                $stmtInsertRi->execute([$returnId, $vr['sale_item_id'], $vr['variation_id'], $vr['quantity'], $vr['refund_amount'] / $vr['quantity'], $vr['refund_amount']]);

                $restoreQty = $vr['quantity'] * $vr['multiplier'];

                $stmtGetStock->execute([$vr['variation_id']]);
                $prevStock = (int) ($stmtGetStock->fetchColumn() ?? 0);
                $newStock = $prevStock + $restoreQty;

                $stmtRestoreStock->execute([$restoreQty, $vr['variation_id']]);
                $stmtStockMove->execute([$vr['variation_id'], 'return', $restoreQty, $prevStock, $newStock, $returnRef, "Adj Return from {$sale['sale_ref']}", $user_id, $vr['cost']]);

                $netDifference -= $vr['refund_amount'];
            }
            $adjustmentDetails[] = "Returned items (Ref: $returnRef, Refund: $totalRefund)";
        }
    }
    // --- 3. PROCESS ADDITIONS ---
    if (!empty($add_items)) {
        $stmtInsertItem = $conn->prepare("
            INSERT INTO pos_sale_items 
            (sale_id, variation_id, unit_id, multiplier, product_name, brand_name, variation_name, 
             unit_type, barcode, price_at_sale, original_price, item_discount, cost_at_sale, quantity, subtotal)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($add_items as $ai) {
            $vId = intval($ai['variation_id']);
            $qty = intval($ai['quantity']);
            if ($qty <= 0)
                continue;

            $stmtGetCapital->execute([$vId]);
            $cost = floatval($stmtGetCapital->fetchColumn() ?? 0);

            $price = floatval($ai['price']);
            $sub = $price * $qty;
            $multiplier = intval($ai['multiplier'] ?? 1);
            $totalDeduct = $qty * $multiplier;

            $stmtInsertItem->execute([
                $sale_id,
                $vId,
                $ai['unit_id'] ?? null,
                $multiplier,
                $ai['product_name'],
                $ai['brand_name'] ?? null,
                $ai['variation_name'] ?? null,
                $ai['unit_type'] ?? 'pc',
                $ai['barcode'] ?? null,
                $price,
                $ai['original_price'] ?? $price,
                $ai['item_discount'] ?? 0,
                $cost,
                $qty,
                $sub
            ]);

            // Deduction & Movement
            $stmtGetStock->execute([$vId]);
            $prevStock = (int) ($stmtGetStock->fetchColumn() ?? 0);
            $newStock = $prevStock - $totalDeduct;

            $stmtDeductStock->execute([$totalDeduct, $vId]);
            $stmtStockMove->execute([$vId, 'sales', $totalDeduct, $prevStock, $newStock, $sale['sale_ref'], "Adj Addition", $user_id, $cost]);

            $netDifference += $sub;
        }
        $adjustmentDetails[] = "Added items";
    }

    // --- 4. UPDATE SALE HEADER ---
    $newSubtotal = floatval($sale['subtotal']) + $netDifference;
    $newGrandTotal = floatval($sale['grand_total']) + $netDifference;

    $stmtUpdateHeader = $conn->prepare("UPDATE pos_sales SET subtotal = ?, grand_total = ? WHERE sale_id = ?");
    $stmtUpdateHeader->execute([$newSubtotal, $newGrandTotal, $sale_id]);

    // --- 5. RECORD PAYMENT DIFFERENCE ---
    if ($netDifference != 0) {
        if ($payment_method === 'pay_later') {
            if (!$sale['buyer_id']) {
                throw new Exception("Cannot adjust to Pay Later: No buyer associated with this sale.");
            }

            // Look for existing debt record (pay_later or credit)
            $stmtFindPl = $conn->prepare("SELECT payment_id, amount, paid_amount FROM pos_sale_payments WHERE sale_id = ? AND payment_type IN ('pay_later', 'credit') ORDER BY payment_id ASC LIMIT 1");
            $stmtFindPl->execute([$sale_id]);
            $existingPl = $stmtFindPl->fetch(PDO::FETCH_ASSOC);

            if ($existingPl) {
                $paidAmt = floatval($existingPl['paid_amount']);
                $newPlAmount = floatval($existingPl['amount']) + $netDifference;
                $newStatus = ($newPlAmount <= $paidAmt + 0.01) ? 'paid' : (($paidAmt > 0) ? 'partial' : 'pending');

                $stmtUpdatePl = $conn->prepare("UPDATE pos_sale_payments SET amount = ?, payment_status = ? WHERE payment_id = ?");
                $stmtUpdatePl->execute([$newPlAmount, $newStatus, $existingPl['payment_id']]);
                $adjustmentDetails[] = "Updated existing debt balance to " . number_format($newPlAmount, 2);
            } else {
                $payRef = 'ADJ-' . $sale['sale_ref'] . '-' . date('His');
                $stmtPay = $conn->prepare("INSERT INTO pos_sale_payments (sale_id, payment_type, amount, paid_amount, payment_status, reference_no) VALUES (?, 'pay_later', ?, 0, 'pending', ?)");
                $stmtPay->execute([$sale_id, $netDifference, $payRef]);
                $adjustmentDetails[] = "Created new Pay Later record for " . number_format($netDifference, 2);
            }
        } else {
            $payRef = 'ADJ-' . $sale['sale_ref'] . '-' . date('His');
            $stmtPay = $conn->prepare("INSERT INTO pos_sale_payments (sale_id, payment_type, amount, paid_amount, payment_status, reference_no) VALUES (?, ?, ?, ?, 'paid', ?)");
            $stmtPay->execute([$sale_id, $payment_method, $netDifference, $netDifference, $payRef]);
            $adjustmentDetails[] = "Recorded " . $payment_method . " adjustment of " . number_format($netDifference, 2);
        }
    }

    // --- 6. FINALIZE SALE STATUS ---
    // Recalculate overall payment status from ALL payments to ensure header is in sync
    $stmtTotal = $conn->prepare("SELECT SUM(amount) as total_amount, SUM(paid_amount) as total_paid FROM pos_sale_payments WHERE sale_id = ? AND payment_status != 'voided'");
    $stmtTotal->execute([$sale_id]);
    $totals = $stmtTotal->fetch(PDO::FETCH_ASSOC);

    $totalAmount = floatval($totals['total_amount'] ?? 0);
    $totalPaid = floatval($totals['total_paid'] ?? 0);

    $finalPaymentStatus = ($totalAmount <= $totalPaid + 0.01) ? 'paid' : (($totalPaid > 0) ? 'partial' : 'pending');
    $finalSaleStatus = ($finalPaymentStatus === 'paid') ? 'completed' : 'not_completed';

    $stmtUpdateHeaderFinal = $conn->prepare("UPDATE pos_sales SET subtotal = ?, grand_total = ?, payment_status = ?, status = ? WHERE sale_id = ?");
    $stmtUpdateHeaderFinal->execute([$newSubtotal, $newGrandTotal, $finalPaymentStatus, $finalSaleStatus, $sale_id]);

    $conn->commit();

    logActivity($conn, $user_id, 'TRANSACTION_ADJUSTED', 'POS', "Adjusted sale {$sale['sale_ref']}. Net: ₱" . number_format($netDifference, 2) . ". " . implode('; ', $adjustmentDetails), $sale_id);

    echo json_encode([
        'success' => true,
        'message' => 'Transaction adjusted successfully',
        'net_difference' => $netDifference,
        'new_total' => $newGrandTotal
    ]);

} catch (Throwable $e) {
    if (isset($conn))
        $conn->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
