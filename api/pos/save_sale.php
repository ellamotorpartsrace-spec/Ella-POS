<?php

/**
 * api/pos/save_sale.php
 * Enhanced POS Sale API - Stores complete sale data with buyer/item details
 */

// CRITICAL: Start session before anything else to ensure session data is available
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/logger.php';

// Auth Check - Enforce standard permissions
requirePermission('make_sales');

$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (empty($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No items in cart']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    $userId = $_SESSION['user_id'];
    $saleRef = 'POS-' . date('YmdHis') . '-' . rand(100, 999);

    // =====================================================
    // 1. EXTRACT DATA
    // =====================================================
    $items = $data['items'];
    $buyer = $data['buyer'] ?? [];
    $payment = $data['payment'] ?? [];

    $buyerId = !empty($buyer['buyer_id']) ? (int) $buyer['buyer_id'] : null;
    $buyerName = $buyer['buyer_name'] ?? 'Walk-in Customer';
    $buyerShop = $buyer['shop_name'] ?? $buyer['shop'] ?? null;
    $buyerAddress = $buyer['address'] ?? null;
    $buyerContact = $buyer['contact_number'] ?? null;
    $priceTier = $buyer['price_tier'] ?? 'retail';

    $subtotal = floatval($data['subtotal'] ?? $data['grand_total'] ?? 0);
    $taxAmount = floatval($data['tax_amount'] ?? 0);
    $discountAmount = floatval($data['discount_amount'] ?? 0);
    $grandTotal = floatval($data['grand_total'] ?? $subtotal);
    $amountTendered = floatval($payment['amount_tendered'] ?? $payment['amount'] ?? 0);
    $changeDue = floatval($payment['change_due'] ?? $payment['change'] ?? 0);
    $paymentMethod = $payment['method'] ?? 'cash';
    $referenceNo = $payment['reference_no'] ?? null;
    $remarks = $data['remarks'] ?? null;
    $saveToWallet = filter_var($payment['save_to_wallet'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $walletSupplAmt = floatval($payment['wallet_supplement_amount'] ?? 0);
    $shortfallAsCredit = filter_var($payment['shortfall_as_credit'] ?? false, FILTER_VALIDATE_BOOLEAN);

    // Determine payment status
    $paymentStatus = 'paid';
    $isUnderpayment = false;
    if ($paymentMethod === 'terms' || $paymentMethod === 'pay_later') {
        $paymentStatus = 'pending';
    } elseif ($amountTendered < $grandTotal && $paymentMethod !== 'financing' && $paymentMethod !== 'mix') {
        if ($buyerId) {
            if ($shortfallAsCredit) {
                $paymentStatus = 'partial'; // Shortfall recorded as receivable credit
                $isUnderpayment = false;    // Don't debit wallet
            } else {
                $paymentStatus = 'paid'; // Will be covered by wallet debit
                $isUnderpayment = true;
            }
        } else {
            $paymentStatus = 'partial'; // Walk-in short (should be blocked by frontend)
        }
    }

    // Determine Sale Status
    $saleStatus = 'completed';
    if ($paymentMethod === 'pay_later') {
        $saleStatus = 'not_completed';
    }
    if ($shortfallAsCredit && $amountTendered < $grandTotal) {
        $saleStatus = 'not_completed'; // Sale stays as not_completed until shortfall is payed
    }

    // Append Pay Later details to remarks
    if ($paymentMethod === 'pay_later' && !empty($payment['pay_later']) && is_array($payment['pay_later'])) {
        $schedules = [];
        foreach ($payment['pay_later'] as $pl) {
            $dueDate = $pl['due_date'] ?? 'N/A';
            $amountDue = number_format($pl['amount_due'] ?? 0, 2);
            $schedules[] = "{$dueDate} (₱{$amountDue})";
        }
        $remarks = ($remarks ? $remarks . " | " : "") . "Schedules: " . implode(', ', $schedules);
    }

    // Append Financing details to remarks (supports home_credit for backward compat)
    if (($paymentMethod === 'financing' || $paymentMethod === 'home_credit') && !empty($payment['financing'])) {
        $finData = $payment['financing'];
        $downPayment = floatval($finData['down_payment'] ?? 0);
        $finRef = !empty($finData['reference_no']) ? $finData['reference_no'] : 'N/A';
        $finProvider = $finData['provider'] ?? 'Home Credit';
        $remarks = ($remarks ? $remarks . " | " : "") . "{$finProvider} Ref: {$finRef}" . ($downPayment > 0 ? ", DP: ₱" . number_format($downPayment, 2) : "");
        // Normalize to 'financing'
        $paymentMethod = 'financing';
    } elseif (($paymentMethod === 'financing' || $paymentMethod === 'home_credit') && !empty($payment['home_credit'])) {
        // Legacy home_credit format
        $hcData = $payment['home_credit'];
        $downPayment = floatval($hcData['down_payment'] ?? 0);
        $hcRef = !empty($hcData['reference_no']) ? $hcData['reference_no'] : 'N/A';
        $remarks = ($remarks ? $remarks . " | " : "") . "HC Ref: {$hcRef}" . ($downPayment > 0 ? ", DP: ₱" . number_format($downPayment, 2) : "");
        $paymentMethod = 'financing';
    }

    // =====================================================
    // 2. INSERT SALE HEADER
    // =====================================================
    $sql = "INSERT INTO pos_sales 
            (sale_ref, user_id, buyer_id, walkin_name, buyer_shop_name, buyer_address, 
             buyer_contact, price_tier, subtotal, tax_amount, discount_amount, grand_total, 
             amount_tendered, change_due, payment_status, payment_method, status, remarks)
            VALUES 
            (:sale_ref, :user_id, :buyer_id, :walkin_name, :shop_name, :address,
             :contact, :price_tier, :subtotal, :tax, :discount, :grand_total,
             :tendered, :change, :pay_status, :pay_method, :status, :remarks)";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':sale_ref' => $saleRef,
        ':user_id' => $userId,
        ':buyer_id' => $buyerId,
        ':walkin_name' => $buyerName,
        ':shop_name' => $buyerShop,
        ':address' => $buyerAddress,
        ':contact' => $buyerContact,
        ':price_tier' => $priceTier,
        ':subtotal' => $subtotal,
        ':tax' => $taxAmount,
        ':discount' => $discountAmount,
        ':grand_total' => $grandTotal,
        ':tendered' => $amountTendered,
        ':change' => $changeDue,
        ':pay_status' => $paymentStatus,
        ':pay_method' => $paymentMethod,
        ':status' => $saleStatus,
        ':remarks' => $remarks
    ]);

    $saleId = $conn->lastInsertId();

    // =====================================================
    // 3. INSERT SALE ITEMS + UPDATE STOCK
    // =====================================================
    $itemSql = "INSERT INTO pos_sale_items 
                (sale_id, variation_id, unit_id, multiplier, product_name, brand_name, variation_name, 
                 unit_type, barcode, price_at_sale, original_price, item_discount, cost_at_sale, quantity, subtotal)
                VALUES 
                (:sale_id, :var_id, :unit_id, :multiplier, :product_name, :brand, :variation,
                 :unit_type, :barcode, :price, :original_price, :item_discount, :cost, :qty, :subtotal)";
    $itemStmt = $conn->prepare($itemSql);

    // Get cost price query — prefer unit capital, fall back to variation capital
    $costUnitSql = "SELECT price_capital FROM product_units WHERE id = ?";
    $costUnitStmt = $conn->prepare($costUnitSql);
    $costVarSql = "SELECT price_capital FROM product_variations WHERE variation_id = ?";
    $costVarStmt = $conn->prepare($costVarSql);

    // Update inventory query - IMPORTANT: Only deduct from Physical Store (store_id = 1)
    $stockSql = "UPDATE inventory SET quantity = quantity - :qty WHERE variation_id = :var_id AND store_id = 1";
    $stockStmt = $conn->prepare($stockSql);

    // Stock movement query
    $moveSql = "INSERT INTO stock_movements 
                (store_id, variation_id, type, quantity, previous_stock, new_stock, reference, remarks, created_by, capital_cost)
                SELECT 1, :var_id, 'sales', :qty, i.quantity, i.quantity - :qty2, :ref, :remarks, :user_id, :cost
                FROM inventory i WHERE i.variation_id = :var_id2 AND i.store_id = 1";
    $moveStmt = $conn->prepare($moveSql);

    foreach ($items as $item) {
        // Get cost price — use custom unit capital if item is a custom unit
        $unitId = isset($item['unit_id']) && intval($item['unit_id']) > 0 ? (int) $item['unit_id'] : null;
        if ($unitId) {
            $costUnitStmt->execute([$unitId]);
            $costPrice = $costUnitStmt->fetchColumn();
            // If no custom capital set, fall back to variation capital
            if (!$costPrice) {
                $costVarStmt->execute([$item['variation_id']]);
                $costPrice = $costVarStmt->fetchColumn() ?: 0;
            }
        } else {
            $costVarStmt->execute([$item['variation_id']]);
            $costPrice = $costVarStmt->fetchColumn() ?: 0;
        }

        $itemQty = (int) $item['quantity'];
        $itemMultiplier = isset($item['multiplier']) ? (int) $item['multiplier'] : 1;
        $totalDeductedQty = $itemQty * $itemMultiplier;

        $itemSubtotal = floatval($item['price']) * $itemQty;

        // Insert sale item
        $itemStmt->execute([
            ':sale_id' => $saleId,
            ':var_id' => (int) $item['variation_id'],
            ':unit_id' => isset($item['unit_id']) ? (int) $item['unit_id'] : null,
            ':multiplier' => $itemMultiplier,
            ':product_name' => $item['product_name'] ?? ($item['name'] ?? ''),
            ':brand' => $item['brand_name'] ?? ($item['brand'] ?? null),
            ':variation' => $item['variation_name'] ?? ($item['variation'] ?? null),
            ':unit_type' => $item['unit_type'] ?? 'pc',
            ':barcode' => $item['barcode'] ?? null,
            ':price' => floatval($item['price']),
            ':original_price' => isset($item['original_price']) ? floatval($item['original_price']) : null,
            ':item_discount' => isset($item['item_discount']) ? floatval($item['item_discount']) : 0.00,
            ':cost' => $costPrice,
            ':qty' => $itemQty,
            ':subtotal' => $itemSubtotal
        ]);

        // Log stock movement BEFORE updating stock
        $moveStmt->execute([
            ':var_id' => (int) $item['variation_id'],
            ':qty' => $totalDeductedQty,
            ':qty2' => $totalDeductedQty,
            ':ref' => $saleRef,
            ':remarks' => 'POS Sale' . ($itemMultiplier > 1 ? " ({$itemQty} {$item['unit_type']} = {$totalDeductedQty} pcs)" : ''),
            ':user_id' => $userId,
            ':var_id2' => (int) $item['variation_id'],
            ':cost' => $costPrice
        ]);

        // Update inventory
        $stockStmt->execute([
            ':qty' => $totalDeductedQty,
            ':var_id' => (int) $item['variation_id']
        ]);
    }

    // =====================================================
    // 4. INSERT PAYMENT RECORD
    // =====================================================
    // =====================================================
    // 4. INSERT PAYMENT RECORD
    // =====================================================

    // Calculate paid_amount for this specific payment record
    // If pay_later, paid_amount is initially 0 (unless there is a downpayment logic, but here we treat it as full credit)
    // If cash/other, paid_amount is the amount tendered
    $paidAmount = ($paymentMethod === 'pay_later') ? 0.00 : $amountTendered;

    // Determine due date (Use first schedule if multiple exists)
    $paymentDueDate = null;
    if ($paymentMethod === 'pay_later' && !empty($payment['pay_later'])) {
        if (is_array($payment['pay_later'])) {
            $firstSchedule = reset($payment['pay_later']);
            $paymentDueDate = $firstSchedule['due_date'] ?? null;
        } else {
            $paymentDueDate = $payment['pay_later']['due_date'] ?? null;
        }
    }

    $paySql = "INSERT INTO pos_sale_payments 
               (sale_id, payment_type, amount, paid_amount, payment_status, due_date, reference_no)
               VALUES (:sale_id, :type, :amount, :paid_amount, :status, :due_date, :ref)";
    $payStmt = $conn->prepare($paySql);

    if ($paymentMethod === 'mix' && !empty($payment['mix_details'])) {
        // Handle mix payment: insert multiple rows
        foreach ($payment['mix_details'] as $mixDetail) {
            $mixMethod = $mixDetail['method']; // cash, gcash, bank, home_credit
            $mixAmount = floatval($mixDetail['amount']);

            // Re-evaluate payment status per part (usually 'paid' for all except pay_later)
            $mixStatus = 'paid';
            $dbType = ($mixMethod === 'bank') ? 'bank_transfer' : $mixMethod;

            $payStmt->execute([
                ':sale_id' => $saleId,
                ':type' => $dbType,
                ':amount' => $mixAmount,
                ':paid_amount' => $mixAmount,
                ':status' => $mixStatus,
                ':due_date' => null,
                ':ref' => $referenceNo ?? $saleRef
            ]);
        }
    } elseif ($paymentMethod === 'pay_later' && !empty($payment['pay_later']) && is_array($payment['pay_later'])) {
        // Handle multiple scheduled pay later dates
        foreach ($payment['pay_later'] as $pl) {
            $plAmount = floatval($pl['amount_due'] ?? 0);
            $plDate = $pl['due_date'] ?? null;

            if ($plAmount > 0) {
                $payStmt->execute([
                    ':sale_id' => $saleId,
                    ':type' => 'pay_later',
                    ':amount' => $plAmount,
                    ':paid_amount' => 0.00,
                    ':status' => 'pending',
                    ':due_date' => $plDate,
                    ':ref' => $referenceNo ?? $saleRef
                ]);
            }
        }
    } elseif (($paymentMethod === 'financing' || $paymentMethod === 'home_credit') && (!empty($payment['financing']) || !empty($payment['home_credit']))) {
        // Handle Financing down payment and financed amount (universal)
        $finData = $payment['financing'] ?? $payment['home_credit'] ?? [];
        $downPayment = floatval($finData['down_payment'] ?? 0);
        $dpMethod = $finData['dp_method'] ?? 'cash';
        $financedAmount = $grandTotal - $downPayment;
        $finRef = !empty($finData['reference_no']) ? $finData['reference_no'] : ($referenceNo ?? $saleRef);
        $finProvider = $finData['provider'] ?? 'Home Credit';

        if ($downPayment > 0) {
            if ($dpMethod === 'mix' && !empty($finData['mix_details'])) {
                foreach ($finData['mix_details'] as $mixDetail) {
                    $mixAmount = floatval($mixDetail['amount']);
                    $mixMethod = $mixDetail['method'];
                    $dbType = ($mixMethod === 'bank') ? 'bank_transfer' : $mixMethod;

                    if ($mixAmount > 0) {
                        $payStmt->execute([
                            ':sale_id' => $saleId,
                            ':type' => $dbType,
                            ':amount' => $mixAmount,
                            ':paid_amount' => $mixAmount,
                            ':status' => 'paid',
                            ':due_date' => null,
                            ':ref' => 'DP-' . $finRef
                        ]);
                    }
                }
            } else {
                $dbType = ($dpMethod === 'bank') ? 'bank_transfer' : $dpMethod;

                // Record down payment
                $payStmt->execute([
                    ':sale_id' => $saleId,
                    ':type' => $dbType,
                    ':amount' => $downPayment,
                    ':paid_amount' => $downPayment,
                    ':status' => 'paid',
                    ':due_date' => null,
                    ':ref' => 'DP-' . $finRef
                ]);
            }
        }

        if ($financedAmount > 0) {
            // Record financed amount
            $payStmt->execute([
                ':sale_id' => $saleId,
                ':type' => 'financing',
                ':amount' => $financedAmount,
                ':paid_amount' => $financedAmount,
                ':status' => 'paid',
                ':due_date' => null,
                ':ref' => $finRef
            ]);

            // Update the financing_provider on the financed payment record
            $updateProvider = $conn->prepare("UPDATE pos_sale_payments SET financing_provider = :provider WHERE sale_id = :sale_id AND payment_type = 'financing' ORDER BY payment_id DESC LIMIT 1");
            $updateProvider->execute([':provider' => $finProvider, ':sale_id' => $saleId]);
        }
    } else {
        // Handle single payment logic (Cash, GCash, Bank, Wallet)
        $dbPayType = ($paymentMethod === 'bank') ? 'bank_transfer' : $paymentMethod;

        $payStmt->execute([
            ':sale_id' => $saleId,
            ':type' => $dbPayType,
            ':amount' => $amountTendered,
            ':paid_amount' => $amountTendered,
            ':status' => strtolower(trim($paymentStatus)),
            ':due_date' => null,
            ':ref' => $referenceNo ?? $saleRef
        ]);

        // If there is an underpayment via wallet, log the shortfall as a wallet payment row
        if ($isUnderpayment && $buyerId) {
            $shortfall = $grandTotal - $amountTendered;
            if ($shortfall > 0) {
                $payStmt->execute([
                    ':sale_id' => $saleId,
                    ':type' => 'wallet',
                    ':amount' => $shortfall,
                    ':paid_amount' => $shortfall,
                    ':status' => 'paid',
                    ':due_date' => null,
                    ':ref' => $saleRef
                ]);
            }
        }

        // If shortfall is recorded as credit (receivable), insert a pending credit row
        if ($shortfallAsCredit && $buyerId && $amountTendered < $grandTotal) {
            $creditShortfall = $grandTotal - $amountTendered;
            if ($creditShortfall > 0) {
                $payStmt->execute([
                    ':sale_id' => $saleId,
                    ':type' => 'credit',
                    ':amount' => $creditShortfall,
                    ':paid_amount' => 0,
                    ':status' => 'pending',
                    ':due_date' => null,
                    ':ref' => $saleRef
                ]);
            }
        }
    }

    // =====================================================
    // 4b. INSERT WALLET SUPPLEMENT PAYMENT ROW (if used)
    // =====================================================
    if ($walletSupplAmt > 0 && $buyerId) {
        $payStmt->execute([
            ':sale_id' => $saleId,
            ':type' => 'wallet',
            ':amount' => $walletSupplAmt,
            ':paid_amount' => $walletSupplAmt,
            ':status' => 'paid',
            ':due_date' => null,
            ':ref' => $saleRef
        ]);
    }

    // =====================================================
    // 5. UPDATE WALLET BALANCE (If Applicable)
    // =====================================================
    if ($buyerId) {
        $stmtBalance = $conn->prepare("SELECT wallet_balance FROM buyers WHERE buyer_id = ?");
        $stmtBalance->execute([$buyerId]);
        $currentBalance = (float) $stmtBalance->fetchColumn();

        if ($paymentMethod === 'wallet') {
            $walletCharge = $amountTendered;
            if ($walletCharge > 0) {
                $newBalance = $currentBalance - $walletCharge;
                $updateWallet = $conn->prepare("UPDATE buyers SET wallet_balance = ? WHERE buyer_id = ?");
                $updateWallet->execute([$newBalance, $buyerId]);

                $logWallet = $conn->prepare("INSERT INTO buyer_wallet_logs (buyer_id, user_id, type, amount, balance_after, reference_type, reference_id, remarks) VALUES (?, ?, 'debit', ?, ?, 'sale', ?, ?)");
                $logWallet->execute([$buyerId, $userId, $walletCharge, $newBalance, $saleRef, 'Wallet payment for sale']);
            }
        } else {
            // Save to Wallet (Overpayment)
            if ($saveToWallet && $changeDue > 0) {
                $newBalance = $currentBalance + $changeDue;
                $updateWallet = $conn->prepare("UPDATE buyers SET wallet_balance = ? WHERE buyer_id = ?");
                $updateWallet->execute([$newBalance, $buyerId]);

                $logWallet = $conn->prepare("INSERT INTO buyer_wallet_logs (buyer_id, user_id, type, amount, balance_after, reference_type, reference_id, remarks) VALUES (?, ?, 'credit', ?, ?, 'sale', ?, ?)");
                $logWallet->execute([$buyerId, $userId, $changeDue, $newBalance, $saleRef, 'Saved change to wallet']);

                // Refresh balance for next potential operation (though usually only one happens)
                $currentBalance = $newBalance;
            }

            // Underpayment Deduction
            if ($isUnderpayment) {
                $shortfall = $grandTotal - $amountTendered;
                if ($shortfall > 0) {
                    $newBalance = $currentBalance - $shortfall;
                    $updateWallet = $conn->prepare("UPDATE buyers SET wallet_balance = ? WHERE buyer_id = ?");
                    $updateWallet->execute([$newBalance, $buyerId]);

                    $logWallet = $conn->prepare("INSERT INTO buyer_wallet_logs (buyer_id, user_id, type, amount, balance_after, reference_type, reference_id, remarks) VALUES (?, ?, 'debit', ?, ?, 'sale', ?, ?)");
                    $logWallet->execute([$buyerId, $userId, $shortfall, $newBalance, $saleRef, 'Deducted shortfall from wallet']);
                    $currentBalance = $newBalance;
                }
            }

            // Wallet Supplement Deduction (buyer chose to use wallet to top up)
            if ($walletSupplAmt > 0) {
                $newBalance = $currentBalance - $walletSupplAmt;
                $updateWallet = $conn->prepare("UPDATE buyers SET wallet_balance = ? WHERE buyer_id = ?");
                $updateWallet->execute([$newBalance, $buyerId]);

                $logWallet = $conn->prepare("INSERT INTO buyer_wallet_logs (buyer_id, user_id, type, amount, balance_after, reference_type, reference_id, remarks) VALUES (?, ?, 'debit', ?, ?, 'sale', ?, ?)");
                $logWallet->execute([$buyerId, $userId, $walletSupplAmt, $newBalance, $saleRef, 'Wallet used to supplement payment']);
            }
        }
    }

    // =====================================================
    // 5. COMMIT & RESPOND
    // =====================================================
    $conn->commit();

    // TRIGGER LIVE SYNC WEBHOOK
    triggerWebsiteWebhook($conn, $items);

    // Log Activity
    logActivity($conn, $userId, 'SALE_COMPLETED', 'POS', "Processed sale $saleRef for " . number_format($grandTotal, 2), $saleId);

    // Fetch the created_at timestamp for the receipt
    $timeStmt = $conn->prepare("SELECT created_at FROM pos_sales WHERE sale_id = ?");
    $timeStmt->execute([$saleId]);
    $createdAt = $timeStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'sale_id' => $saleId,
        'sale_ref' => $saleRef,
        'created_at' => $createdAt,
        'shortfall_deducted' => $isUnderpayment ? ($grandTotal - $amountTendered) : 0,
        'message' => 'Sale completed successfully'
    ]);
} catch (Throwable $e) {
    if (isset($conn))
        $conn->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Sends a live update to the external website
 */
function triggerWebsiteWebhook($conn, $items)
{
    try {
        // 1. Get Webhook Config
        $stmt = $conn->prepare("SELECT webhook_url, partner_key, is_active FROM api_platforms WHERE platform_name = 'website' LIMIT 1");
        $stmt->execute();
        $config = $stmt->fetch();

        if (!$config || !$config['is_active'] || empty($config['webhook_url'])) {
            return;
        }

        // 2. Prepare payload (only send SKUs and updated stock of the items sold)
        $updates = [];
        foreach ($items as $item) {
            $varId = (int) $item['variation_id'];

            // Fetch updated total stock
            $stockStmt = $conn->prepare("
                SELECT (COALESCE(i1.quantity, 0) + COALESCE(i2.quantity, 0)) as total_stock
                FROM product_variations v
                LEFT JOIN inventory i1 ON v.variation_id = i1.variation_id AND i1.store_id = 1
                LEFT JOIN inventory i2 ON v.variation_id = i2.variation_id AND i2.store_id = 2
                WHERE v.variation_id = ?
            ");
            $stockStmt->execute([$varId]);
            $totalStock = $stockStmt->fetchColumn();

            $updates[] = [
                'sku' => $item['sku'] ?? null,
                'stock' => (int) $totalStock,
                'price' => (float) $item['price']
            ];
        }

        // 3. Send Webhook via CURL (non-blocking as much as possible)
        $payload = json_encode([
            'event' => 'stock_update',
            'timestamp' => date('Y-m-d H:i:s'),
            'updates' => $updates
        ]);

        $ch = curl_init($config['webhook_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $config['partner_key']
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Short timeout to not delay POS
        curl_exec($ch);
        curl_close($ch);

    } catch (Exception $e) {
        // Silently fail to not break the POS sale flow
        error_log("Webhook Error: " . $e->getMessage());
    }
}
