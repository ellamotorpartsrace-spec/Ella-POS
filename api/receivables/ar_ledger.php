<?php
// api/receivables/ar_ledger.php
// Returns all pay_later invoices for a specific buyer OR handles credit-limit update
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/logger.php';

requireLogin();

$method = $_SERVER['REQUEST_METHOD'];

// ── PATCH: update credit limit ──────────────────────────────────────────────
if ($method === 'PATCH') {
    $input = json_decode(file_get_contents('php://input'), true);
    $buyer_id = intval($input['buyer_id'] ?? 0);
    $limit = isset($input['credit_limit']) && $input['credit_limit'] !== ''
        ? floatval($input['credit_limit']) : null;
    $notes = trim($input['credit_notes'] ?? '');

    if (!$buyer_id) {
        echo json_encode(['success' => false, 'error' => 'Missing buyer_id']);
        exit;
    }

    try {
        $db = new Database();
        $conn = $db->getConnection();
        $conn->prepare("UPDATE buyers SET credit_limit = ?, credit_notes = ? WHERE buyer_id = ?")
            ->execute([$limit, $notes ?: null, $buyer_id]);

        logActivity(
            $conn,
            $_SESSION['user_id'],
            'UPDATE_CREDIT_LIMIT',
            'AR',
            "Updated credit limit for buyer #{$buyer_id} to " . ($limit ?? 'none'),
            $buyer_id
        );

        echo json_encode(['success' => true, 'message' => 'Credit limit updated']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── GET: buyer ledger ───────────────────────────────────────────────────────
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$buyer_id = intval($_GET['buyer_id'] ?? 0);
$walkin_name = trim($_GET['walkin_name'] ?? '');
$export = isset($_GET['export']) && $_GET['export'] == '1';

if (!$buyer_id && $walkin_name === '') {
    echo json_encode(['success' => false, 'error' => 'Missing buyer identifier']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Buyer profile
    if ($buyer_id) {
        $stmtB = $conn->prepare("SELECT buyer_id, buyer_name, shop_name, price_tier, contact_number, credit_limit, credit_notes FROM buyers WHERE buyer_id = ?");
        $stmtB->execute([$buyer_id]);
        $buyer = $stmtB->fetch(PDO::FETCH_ASSOC);
    } else {
        // Walk-in mock profile
        $buyer = [
            'buyer_id' => null,
            'buyer_name' => $walkin_name,
            'shop_name' => null,
            'price_tier' => 'retail',
            'contact_number' => null,
            'credit_limit' => null,
            'credit_notes' => null
        ];
    }

    if (!$buyer) {
        echo json_encode(['success' => false, 'error' => 'Buyer not found']);
        exit;
    }

    // All pay_later invoices for this buyer/walk-in
    $sqlInvoices = "
        SELECT
            s.sale_id,
            s.sale_ref,
            s.remarks,
            s.created_at        AS sale_date,
            s.grand_total,
            psp.payment_id,
            psp.amount          AS amount_due,
            psp.paid_amount,
            (psp.amount - psp.paid_amount) AS balance,
            psp.payment_status,
            psp.due_date,
            psp.notes,
            (SELECT notes FROM payment_history WHERE payment_id = psp.payment_id AND notes IS NOT NULL AND notes != '' ORDER BY paid_at DESC LIMIT 1) AS latest_settlement_note,
            CASE
                WHEN psp.payment_status = 'paid' THEN 0
                WHEN psp.due_date IS NULL         THEN NULL
                ELSE GREATEST(0, DATEDIFF(CURDATE(), psp.due_date))
            END                 AS days_overdue,
            psp.reference_no
        FROM pos_sale_payments psp
        INNER JOIN pos_sales s ON psp.sale_id = s.sale_id
        WHERE psp.payment_type    IN ('pay_later', 'credit')
          AND s.status           != 'voided'
          AND " . ($buyer_id ? "s.buyer_id = ?" : "s.buyer_id IS NULL AND s.walkin_name = ?") . "
        ORDER BY s.created_at DESC
    ";
    $stmtI = $conn->prepare($sqlInvoices);
    $stmtI->execute([$buyer_id ?: $walkin_name]);
    $invoices = $stmtI->fetchAll(PDO::FETCH_ASSOC);

    // Cast numerics
    foreach ($invoices as &$inv) {
        $inv['amount_due'] = (float) $inv['amount_due'];
        $inv['paid_amount'] = (float) $inv['paid_amount'];
        $inv['balance'] = (float) $inv['balance'];
        $inv['grand_total'] = (float) $inv['grand_total'];
        $inv['days_overdue'] = $inv['days_overdue'] !== null ? (int) $inv['days_overdue'] : null;
    }
    unset($inv);

    // Aggregate totals
    $totalBalance = array_sum(array_column($invoices, 'balance'));
    $totalPaid = array_sum(array_column($invoices, 'paid_amount'));
    $totalDue = array_sum(array_column($invoices, 'amount_due'));
    $buyer['credit_limit'] = $buyer['credit_limit'] !== null ? (float) $buyer['credit_limit'] : null;
    $buyer['available_credit'] = $buyer['credit_limit'] !== null
        ? max(0, $buyer['credit_limit'] - $totalBalance) : null;
    $buyer['over_limit'] = $buyer['credit_limit'] !== null && $totalBalance > $buyer['credit_limit'];

    // ── CSV Export ──────────────────────────────────────────────────────────
    if ($export) {
        header('Content-Type: text/csv; charset=UTF-8');
        $fname = 'AR_' . preg_replace('/\W+/', '_', $buyer['buyer_name']) . '_' . date('Ymd') . '.csv';
        header("Content-Disposition: attachment; filename=\"{$fname}\"");
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
        fputcsv($out, ['Invoice Ref', 'Sale Date', 'Due Date', 'Days Overdue', 'Amount Due', 'Paid', 'Balance', 'Status']);
        foreach ($invoices as $inv) {
            fputcsv($out, [
                $inv['sale_ref'],
                $inv['sale_date'],
                $inv['due_date'] ?? '',
                $inv['days_overdue'] ?? 'N/A',
                number_format($inv['amount_due'], 2),
                number_format($inv['paid_amount'], 2),
                number_format($inv['balance'], 2),
                strtoupper($inv['payment_status']),
            ]);
        }
        fputcsv($out, []);
        fputcsv($out, ['', '', '', 'TOTAL', number_format($totalDue, 2), number_format($totalPaid, 2), number_format($totalBalance, 2), '']);
        fclose($out);
        exit;
    }

    echo json_encode([
        'success' => true,
        'buyer' => $buyer,
        'invoices' => $invoices,
        'totals' => [
            'amount_due' => $totalDue,
            'paid' => $totalPaid,
            'balance' => $totalBalance,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
