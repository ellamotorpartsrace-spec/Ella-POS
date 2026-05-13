<?php
// api/pos/process_return.php
// Processes a partial return against an existing completed sale
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

$input = json_decode(file_get_contents('php://input'), true);
$sale_id = intval($input['sale_id'] ?? 0);
$items = $input['items'] ?? [];   // [{sale_item_id, variation_id, quantity, refund_amount}]
$reason = trim($input['reason'] ?? 'Return');
$refund_method = $input['refund_method'] ?? 'cash';
$user_id = $_SESSION['user_id'];

// --- Basic Validation ---
if (!$sale_id || empty($items)) {
    echo json_encode(['success' => false, 'error' => 'Missing sale_id or items']);
    exit;
}

$allowed_methods = ['cash', 'gcash', 'bank', 'store_credit', 'financing'];
if (!in_array($refund_method, $allowed_methods)) {
    echo json_encode(['success' => false, 'error' => 'Invalid refund method']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    // 1. Verify sale exists and is not voided
    $stmtSale = $conn->prepare("SELECT sale_id, sale_ref, status, subtotal, grand_total, buyer_id FROM pos_sales WHERE sale_id = ?");
    $stmtSale->execute([$sale_id]);
    $sale = $stmtSale->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        throw new Exception("Sale not found.");
    }
    if ($sale['status'] === 'voided') {
        throw new Exception("Cannot return items from a voided sale.");
    }

    // 2. Load original sale items (to validate quantities)
    $stmtOrigItems = $conn->prepare("
        SELECT sale_item_id, variation_id, quantity, price_at_sale, multiplier, cost_at_sale
        FROM pos_sale_items WHERE sale_id = ?
    ");
    $stmtOrigItems->execute([$sale_id]);
    $origItemsMap = [];
    foreach ($stmtOrigItems->fetchAll(PDO::FETCH_ASSOC) as $oi) {
        $origItemsMap[$oi['sale_item_id']] = $oi;
    }

    // 3. Load already-returned quantities per sale_item_id
    $stmtAlreadyReturned = $conn->prepare("
        SELECT ri.sale_item_id, SUM(ri.quantity) AS total_returned
        FROM pos_return_items ri
        INNER JOIN pos_returns r ON ri.return_id = r.return_id
        WHERE r.sale_id = ? AND r.status = 'completed'
        GROUP BY ri.sale_item_id
    ");
    $stmtAlreadyReturned->execute([$sale_id]);
    $alreadyReturnedMap = [];
    foreach ($stmtAlreadyReturned->fetchAll(PDO::FETCH_ASSOC) as $ar) {
        $alreadyReturnedMap[$ar['sale_item_id']] = (int) $ar['total_returned'];
    }

    // 4. Validate each requested return item
    $totalRefund = 0;
    $validatedItems = [];

    foreach ($items as $item) {
        $saleItemId = intval($item['sale_item_id'] ?? 0);
        $variationId = intval($item['variation_id'] ?? 0);
        $returnQty = intval($item['quantity'] ?? 0);

        if ($returnQty <= 0)
            continue;

        if (!isset($origItemsMap[$saleItemId])) {
            throw new Exception("Item (ID: $saleItemId) does not belong to this sale.");
        }

        $origItem = $origItemsMap[$saleItemId];
        $alreadyReturned = $alreadyReturnedMap[$saleItemId] ?? 0;
        $maxReturnable = (int) $origItem['quantity'] - $alreadyReturned;

        if ($returnQty > $maxReturnable) {
            throw new Exception("Cannot return {$returnQty} of item ID {$saleItemId}. Maximum returnable: {$maxReturnable}.");
        }

        $itemRefund = floatval($item['refund_amount'] ?? (floatval($origItem['price_at_sale']) * $returnQty));
        $totalRefund += $itemRefund;

        $validatedItems[] = [
            'sale_item_id' => $saleItemId,
            'variation_id' => $variationId ?: (int) $origItem['variation_id'],
            'product_name' => $item['product_name'] ?? '',
            'brand_name' => $item['brand_name'] ?? null,
            'variation_name' => $item['variation_name'] ?? null,
            'unit_type' => $item['unit_type'] ?? 'pc',
            'quantity' => $returnQty,
            'price_at_sale' => floatval($origItem['price_at_sale']),
            'refund_amount' => $itemRefund,
            'multiplier' => (int) ($origItem['multiplier'] ?? 1),
            'cost_at_sale' => floatval($origItem['cost_at_sale'] ?? 0),
        ];
    }

    if (empty($validatedItems)) {
        throw new Exception("No valid items to return.");
    }

    // 5. Insert pos_returns header
    $returnRef = 'RET-' . date('YmdHis') . '-' . rand(100, 999);
    $stmtInsertReturn = $conn->prepare("
        INSERT INTO pos_returns (return_ref, sale_id, user_id, reason, refund_method, refund_amount, status)
        VALUES (?, ?, ?, ?, ?, ?, 'completed')
    ");
    $stmtInsertReturn->execute([$returnRef, $sale_id, $user_id, $reason, $refund_method, $totalRefund]);
    $returnId = $conn->lastInsertId();

    // 6. Insert return items + restore inventory
    $stmtInsertItem = $conn->prepare("
        INSERT INTO pos_return_items
            (return_id, sale_item_id, variation_id, product_name, brand_name, variation_name,
             unit_type, quantity, price_at_sale, refund_amount)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtGetStock = $conn->prepare("SELECT quantity FROM inventory WHERE variation_id = ? AND store_id = 1");
    $stmtRestoreStock = $conn->prepare("
        INSERT INTO inventory (variation_id, quantity, store_id) VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE quantity = quantity + ?
    ");
    $stmtStockMove = $conn->prepare("
        INSERT INTO stock_movements
            (variation_id, type, quantity, previous_stock, new_stock, reference, remarks, created_by, store_id, capital_cost)
        VALUES (?, 'return', ?, ?, ?, ?, ?, ?, 1, ?)
    ");

    foreach ($validatedItems as $vi) {
        // 6a. Insert return item row
        $stmtInsertItem->execute([
            $returnId,
            $vi['sale_item_id'],
            $vi['variation_id'],
            $vi['product_name'],
            $vi['brand_name'],
            $vi['variation_name'],
            $vi['unit_type'],
            $vi['quantity'],
            $vi['price_at_sale'],
            $vi['refund_amount'],
        ]);

        // 6b. Restore inventory (multiplier-aware)
        $restoreQty = $vi['quantity'] * $vi['multiplier'];

        $stmtGetStock->execute([$vi['variation_id']]);
        $prevStock = (int) ($stmtGetStock->fetchColumn() ?? 0);
        $newStock = $prevStock + $restoreQty;

        $stmtRestoreStock->execute([$vi['variation_id'], $restoreQty, $restoreQty]);

        // 6c. Log stock movement
        $stmtStockMove->execute([
            $vi['variation_id'],
            $restoreQty,
            $prevStock,
            $newStock,
            $returnRef,
            "Return from {$sale['sale_ref']}: {$reason}",
            $user_id,
            $vi['cost_at_sale']
        ]);
    }

    // 7. Update sale header totals
    $newSubtotal = floatval($sale['subtotal']) - $totalRefund;
    $newGrandTotal = floatval($sale['grand_total']) - $totalRefund;
    $stmtUpdateSale = $conn->prepare("UPDATE pos_sales SET subtotal = ?, grand_total = ? WHERE sale_id = ?");
    $stmtUpdateSale->execute([$newSubtotal, $newGrandTotal, $sale_id]);

    // 8. Record refund in payments (negative entry)
    $stmtPay = $conn->prepare("INSERT INTO pos_sale_payments (sale_id, payment_type, amount, paid_amount, payment_status, reference_no) VALUES (?, ?, ?, ?, 'paid', ?)");
    $stmtPay->execute([$sale_id, $refund_method, -$totalRefund, -$totalRefund, $returnRef]);

    // 9. Update Buyer Wallet if Store Credit
    if ($refund_method === 'store_credit' && $sale['buyer_id']) {
        $buyer_id = $sale['buyer_id'];

        $stmtBalance = $conn->prepare("SELECT wallet_balance FROM buyers WHERE buyer_id = ? FOR UPDATE");
        $stmtBalance->execute([$buyer_id]);
        $currentBalance = (float) $stmtBalance->fetchColumn();

        $newBalance = $currentBalance + $totalRefund;

        $updateWallet = $conn->prepare("UPDATE buyers SET wallet_balance = ? WHERE buyer_id = ?");
        $updateWallet->execute([$newBalance, $buyer_id]);

        $logWallet = $conn->prepare("INSERT INTO buyer_wallet_logs (buyer_id, user_id, type, amount, balance_after, reference_type, reference_id, remarks) VALUES (?, ?, 'credit', ?, ?, 'return', ?, ?)");
        $logWallet->execute([$buyer_id, $user_id, $totalRefund, $newBalance, $sale_id, "Refund via store credit for return $returnRef"]);
    }

    $conn->commit();

    logActivity(
        $conn,
        $user_id,
        'PROCESS_RETURN',
        'POS',
        "Processed return {$returnRef} for sale {$sale['sale_ref']}. Refund: ₱" . number_format($totalRefund, 2),
        $returnId
    );

    echo json_encode([
        'success' => true,
        'return_ref' => $returnRef,
        'refund_amount' => $totalRefund,
        'message' => "Return processed successfully. Ref: {$returnRef}",
    ]);

} catch (Throwable $e) {
    if (isset($conn))
        $conn->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
