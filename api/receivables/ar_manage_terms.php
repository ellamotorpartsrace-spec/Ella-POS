<?php
// api/receivables/ar_manage_terms.php
// GET  ?sale_id=X  → returns all pay_later terms for a sale
// POST              → adds a new pay_later term to an existing sale
// PATCH             → updates date + amount of an existing term
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/logger.php';

requireLogin();

if ($_SESSION['role'] !== 'admin' && !hasPermission('view_profit') && !in_array($_SESSION['role'], ['manager'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// ─── GET: fetch all terms for a sale ─────────────────────────────────────────
if ($method === 'GET') {
    $saleId = intval($_GET['sale_id'] ?? 0);
    if ($saleId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid sale_id']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT payment_id, amount, paid_amount, due_date, payment_status
        FROM pos_sale_payments
        WHERE sale_id = ? AND payment_type = 'pay_later'
        ORDER BY payment_id ASC
    ");
    $stmt->execute([$saleId]);
    $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($terms as &$t) {
        $t['amount'] = (float) $t['amount'];
        $t['paid_amount'] = (float) $t['paid_amount'];
        $t['balance'] = $t['amount'] - $t['paid_amount'];
    }

    // Also get the sale's grand_total for reference
    $saleStmt = $conn->prepare("SELECT sale_ref, grand_total FROM pos_sales WHERE sale_id = ?");
    $saleStmt->execute([$saleId]);
    $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'terms' => $terms,
        'sale' => $sale,
    ]);
    exit;
}

// ─── POST: add a new term ────────────────────────────────────────────────────
if ($method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $saleId = intval($data['sale_id'] ?? 0);
        $dueDate = $data['due_date'] ?? '';
        $amount = floatval($data['amount'] ?? 0);

        if ($saleId <= 0)
            throw new Exception('Invalid sale_id.');
        if (empty($dueDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            throw new Exception('Invalid date format. Expected YYYY-MM-DD.');
        }
        if ($amount <= 0)
            throw new Exception('Amount must be greater than zero.');

        // Verify sale exists and is pay_later
        $check = $conn->prepare("SELECT sale_ref, remarks, payment_method FROM pos_sales WHERE sale_id = ? AND status != 'voided'");
        $check->execute([$saleId]);
        $sale = $check->fetch(PDO::FETCH_ASSOC);
        if (!$sale)
            throw new Exception('Sale not found.');
        if ($sale['payment_method'] !== 'pay_later')
            throw new Exception('This sale is not a Pay Later transaction.');

        // Insert new term
        $insert = $conn->prepare("
            INSERT INTO pos_sale_payments (sale_id, payment_type, amount, paid_amount, due_date, payment_status)
            VALUES (?, 'pay_later', ?, 0, ?, 'pending')
        ");
        $insert->execute([$saleId, $amount, $dueDate]);
        $newPaymentId = $conn->lastInsertId();

        // Update remarks to include the new schedule
        $newEntry = "{$dueDate} (₱" . number_format($amount, 2) . ")";
        $existingRemarks = $sale['remarks'] ?? '';
        if (str_contains($existingRemarks, 'Schedules:')) {
            $updatedRemarks = rtrim($existingRemarks) . ", {$newEntry}";
        } else {
            $updatedRemarks = ($existingRemarks ? $existingRemarks . ' | ' : '') . "Schedules: {$newEntry}";
        }
        $updRemarks = $conn->prepare("UPDATE pos_sales SET remarks = ? WHERE sale_id = ?");
        $updRemarks->execute([$updatedRemarks, $saleId]);

        logActivity(
            $conn,
            $_SESSION['user_id'],
            'TERM_ADDED',
            'Receivables',
            "Added new pay_later term for sale #{$sale['sale_ref']}: {$dueDate} ₱" . number_format($amount, 2),
            $saleId
        );

        echo json_encode([
            'success' => true,
            'message' => 'New term added successfully.',
            'payment_id' => (int) $newPaymentId,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ─── PATCH: robust bulk sync (update existing + add new) ───────────────────
if ($method === 'PATCH') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $saleId = intval($data['sale_id'] ?? 0);
        $updTerms = $data['terms'] ?? [];     // Array of {payment_id, amount, due_date}
        $newTerms = $data['new_terms'] ?? []; // Array of {amount, due_date}

        if ($saleId <= 0)
            throw new Exception('Invalid sale_id.');

        $conn->beginTransaction();

        // 1. Fetch sale info and LOCK for update
        $getSale = $conn->prepare("SELECT sale_ref, remarks, grand_total FROM pos_sales WHERE sale_id = ? FOR UPDATE");
        $getSale->execute([$saleId]);
        $sale = $getSale->fetch(PDO::FETCH_ASSOC);
        if (!$sale)
            throw new Exception('Sale not found.');

        $grandTotal = (float) $sale['grand_total'];

        // 2. Perform Server-Side Validation: Does the total match?
        // We check: sum(paid) + sum(updTerms) + sum(newTerms) MUST equal grandTotal
        $paidStmt = $conn->prepare("SELECT SUM(amount) as paid_sum FROM pos_sale_payments WHERE sale_id = ? AND payment_status = 'paid' AND payment_type = 'pay_later'");
        $paidStmt->execute([$saleId]);
        $paidSum = (float) ($paidStmt->fetch(PDO::FETCH_ASSOC)['paid_sum'] ?? 0);

        $updSum = 0;
        foreach ($updTerms as $t)
            $updSum += floatval($t['amount']);
        $newSum = 0;
        foreach ($newTerms as $nt)
            $newSum += floatval($nt['amount']);

        $totalComputed = $paidSum + $updSum + $newSum;
        if (abs($totalComputed - $grandTotal) > 0.05) {
            throw new Exception("Validation failed: Total terms (₱" . number_format($totalComputed, 2) . ") must match Grand Total (₱" . number_format($grandTotal, 2) . ")");
        }

        // 3. Update existing unpaid terms
        $updateStmt = $conn->prepare("UPDATE pos_sale_payments SET due_date = ?, amount = ? WHERE payment_id = ? AND sale_id = ? AND payment_status != 'paid'");
        foreach ($updTerms as $t) {
            $pid = intval($t['payment_id']);
            $amt = floatval($t['amount']);
            $dd = $t['due_date'];
            if ($pid <= 0 || $amt < 0 || empty($dd))
                continue;
            $updateStmt->execute([$dd, $amt, $pid, $saleId]);
        }

        // 4. Insert new terms
        $insertStmt = $conn->prepare("INSERT INTO pos_sale_payments (sale_id, payment_type, amount, paid_amount, due_date, payment_status) VALUES (?, 'pay_later', ?, 0, ?, 'pending')");
        foreach ($newTerms as $nt) {
            $amt = floatval($nt['amount']);
            $dd = $nt['due_date'];
            if ($amt <= 0 || empty($dd))
                continue;
            $insertStmt->execute([$saleId, $amt, $dd]);
        }

        // 5. Regenerate Remarks string from ALL current terms
        $fetchTerms = $conn->prepare("SELECT amount, due_date FROM pos_sale_payments WHERE sale_id = ? AND payment_type = 'pay_later' AND payment_status != 'voided' ORDER BY payment_id ASC");
        $fetchTerms->execute([$saleId]);
        $allTerms = $fetchTerms->fetchAll(PDO::FETCH_ASSOC);

        $scheduleStrings = [];
        foreach ($allTerms as $at) {
            $scheduleStrings[] = "{$at['due_date']} (₱" . number_format($at['amount'], 2) . ")";
        }
        $newSchedules = "Schedules: " . implode(", ", $scheduleStrings);

        $oldRemarks = $sale['remarks'] ?? '';
        if (str_contains($oldRemarks, 'Schedules:')) {
            $parts = explode('Schedules:', $oldRemarks);
            $updatedRemarks = trim($parts[0]) . ($parts[0] ? ' | ' : '') . $newSchedules;
        } else {
            $updatedRemarks = ($oldRemarks ? $oldRemarks . ' | ' : '') . $newSchedules;
        }

        $updRemarks = $conn->prepare("UPDATE pos_sales SET remarks = ? WHERE sale_id = ?");
        $updRemarks->execute([$updatedRemarks, $saleId]);

        $conn->commit();

        logActivity(
            $conn,
            $_SESSION['user_id'],
            'TERMS_MNG_SYNC',
            'Receivables',
            "Robust Bulk Sync completed for Sale #{$sale['sale_ref']}. All terms and remarks synchronized.",
            $saleId
        );

        echo json_encode(['success' => true, 'message' => 'Terms synchronized and balanced successfully.']);

    } catch (Throwable $e) {
        if ($conn->inTransaction())
            $conn->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
