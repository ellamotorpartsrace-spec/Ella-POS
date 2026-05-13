<?php
// api/receivables/get_ledger_data.php
// Returns a combined chronological ledger of all debits (sales, service fees) 
// and credits (payments) for a specific buyer.

header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

$buyer_id = intval($_GET['buyer_id'] ?? 0);
$date_from = $_GET['date_from'] ?? null;
$date_to = $_GET['date_to'] ?? null;

if (!$buyer_id) {
    echo json_encode(['success' => false, 'error' => 'Missing buyer_id']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Fetch Buyer Details
    $stmtB = $conn->prepare("SELECT buyer_id, buyer_name, shop_name, contact_number, wallet_balance, credit_limit FROM buyers WHERE buyer_id = ?");
    $stmtB->execute([$buyer_id]);
    $buyer = $stmtB->fetch(PDO::FETCH_ASSOC);

    if (!$buyer) {
        echo json_encode(['success' => false, 'error' => 'Buyer not found']);
        exit;
    }

    $ledger = [];

    // 2. Fetch Debits: POS Sales (Pay Later Invoices)
    $sqlSales = "
        SELECT 
            'sale' as entry_type,
            s.sale_id as id,
            s.sale_ref as reference,
            'POS Sale Invoice' as description,
            s.created_at as entry_date,
            s.grand_total as debit,
            0 as credit
        FROM pos_sales s
        INNER JOIN pos_sale_payments psp ON s.sale_id = psp.sale_id
        WHERE s.buyer_id = ? 
          AND psp.payment_type = 'pay_later'
          AND s.status != 'voided'
    ";
    if ($date_from)
        $sqlSales .= " AND s.created_at >= " . $conn->quote($date_from . ' 00:00:00');
    if ($date_to)
        $sqlSales .= " AND s.created_at <= " . $conn->quote($date_to . ' 23:59:59');

    $stmtS = $conn->prepare($sqlSales);
    $stmtS->execute([$buyer_id]);
    $salesEntries = $stmtS->fetchAll(PDO::FETCH_ASSOC);
    $ledger = array_merge($ledger, $salesEntries);

    // 3. Fetch Debits: Service Fees
    $sqlFees = "
        SELECT 
            'fee' as entry_type,
            fee_id as id,
            fee_ref as reference,
            CONCAT(UPPER(SUBSTR(fee_type, 1, 1)), LOWER(SUBSTR(fee_type, 2)), ' Fee') as description,
            created_at as entry_date,
            amount as debit,
            0 as credit
        FROM service_fees
        WHERE buyer_id = ? AND payment_status != 'voided'
    ";
    if ($date_from)
        $sqlFees .= " AND created_at >= " . $conn->quote($date_from . ' 00:00:00');
    if ($date_to)
        $sqlFees .= " AND created_at <= " . $conn->quote($date_to . ' 23:59:59');

    $stmtF = $conn->prepare($sqlFees);
    $stmtF->execute([$buyer_id]);
    $feesEntries = $stmtF->fetchAll(PDO::FETCH_ASSOC);
    $ledger = array_merge($ledger, $feesEntries);

    // 4. Fetch Credits: Sale Payments (from payment_history)
    $sqlSalePayments = "
        SELECT 
            'payment' as entry_type,
            ph.history_id as id,
            s.sale_ref as reference,
            CONCAT('Payment (', ph.payment_method, ')') as description,
            ph.paid_at as entry_date,
            0 as debit,
            ph.amount as credit
        FROM payment_history ph
        INNER JOIN pos_sale_payments psp ON ph.payment_id = psp.payment_id
        INNER JOIN pos_sales s ON psp.sale_id = s.sale_id
        WHERE s.buyer_id = ? AND s.status != 'voided'
    ";
    if ($date_from)
        $sqlSalePayments .= " AND ph.paid_at >= " . $conn->quote($date_from . ' 00:00:00');
    if ($date_to)
        $sqlSalePayments .= " AND ph.paid_at <= " . $conn->quote($date_to . ' 23:59:59');

    $stmtSP = $conn->prepare($sqlSalePayments);
    $stmtSP->execute([$buyer_id]);
    $salePaymentsEntries = $stmtSP->fetchAll(PDO::FETCH_ASSOC);
    $ledger = array_merge($ledger, $salePaymentsEntries);

    // 5. Fetch Credits: Service Fee Payments
    $sqlFeePayments = "
        SELECT 
            'fee_payment' as entry_type,
            sfp.history_id as id,
            sf.fee_ref as reference,
            CONCAT('Fee Payment (', sfp.payment_method, ')') as description,
            sfp.paid_at as entry_date,
            0 as debit,
            sfp.amount as credit
        FROM service_fee_payments sfp
        INNER JOIN service_fees sf ON sfp.fee_id = sf.fee_id
        WHERE sf.buyer_id = ? AND sf.payment_status != 'voided'
    ";
    if ($date_from)
        $sqlFeePayments .= " AND sfp.paid_at >= " . $conn->quote($date_from . ' 00:00:00');
    if ($date_to)
        $sqlFeePayments .= " AND sfp.paid_at <= " . $conn->quote($date_to . ' 23:59:59');

    $stmtFP = $conn->prepare($sqlFeePayments);
    $stmtFP->execute([$buyer_id]);
    $feePaymentsEntries = $stmtFP->fetchAll(PDO::FETCH_ASSOC);
    $ledger = array_merge($ledger, $feePaymentsEntries);

    // 6. Sort by date
    usort($ledger, function ($a, $b) {
        return strtotime($a['entry_date']) - strtotime($b['entry_date']);
    });

    // 7. Calculate Running Balance
    $runningBalance = 0;
    foreach ($ledger as &$entry) {
        $debit = (float) ($entry['debit'] ?? 0);
        $credit = (float) ($entry['credit'] ?? 0);
        $runningBalance += ($debit - $credit);
        $entry['running_balance'] = $runningBalance;

        $entry['debit'] = $debit;
        $entry['credit'] = $credit;
    }
    unset($entry);

    echo json_encode([
        'success' => true,
        'buyer' => $buyer,
        'ledger' => $ledger,
        'summary' => [
            'total_debit' => array_sum(array_column($ledger, 'debit')),
            'total_credit' => array_sum(array_column($ledger, 'credit')),
            'current_balance' => $runningBalance
        ]
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
