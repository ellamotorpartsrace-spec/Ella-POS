<?php
// api/receivables/ar_transactions.php
// Returns all transactions that have at least one 'pay_later' payment record.
// Multi-term sales are grouped in PHP to ensure compatibility with older SQL versions (missing JSON_ARRAYAGG).
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

try {
    $db = new Database();
    $conn = $db->getConnection();

    $filter = $_GET['filter'] ?? 'all';
    $now = date('Y-m-d');

    // Fetch all relevant terms for non-voided sales
    // We fetch one row per term and group in PHP to avoid JSON_ARRAYAGG compatibility issues
    $sql = "
        SELECT 
            s.sale_id,
            s.sale_ref,
            s.created_at AS sale_date,
            s.buyer_id,
            s.walkin_name,
            s.remarks,
            COALESCE(b.buyer_name, s.walkin_name, 'Walk-in') AS customer_name,
            s.grand_total,
            psp.payment_id,
            psp.amount,
            psp.paid_amount,
            psp.due_date,
            psp.payment_status,
            psp.notes,
            (SELECT COUNT(*) FROM pos_sale_items WHERE sale_id = s.sale_id) AS items_count
        FROM pos_sales s
        INNER JOIN pos_sale_payments psp ON s.sale_id = psp.sale_id
        LEFT JOIN buyers b ON s.buyer_id = b.buyer_id
        WHERE s.status != 'voided' 
          AND psp.payment_type IN ('pay_later', 'credit')
          AND psp.payment_status != 'voided'
        ORDER BY s.created_at DESC, psp.payment_id ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Grouping by sale_id in PHP
    $groupedData = [];
    foreach ($rows as $r) {
        $sid = $r['sale_id'];

        if (!isset($groupedData[$sid])) {
            $groupedData[$sid] = [
                'sale_id' => (int) $sid,
                'sale_ref' => $r['sale_ref'],
                'sale_date' => $r['sale_date'],
                'buyer_id' => $r['buyer_id'] ? (int) $r['buyer_id'] : null,
                'walkin_name' => $r['walkin_name'],
                'remarks' => $r['remarks'],
                'customer_name' => $r['customer_name'],
                'grand_total' => (float) $r['grand_total'],
                'items_count' => (int) $r['items_count'],
                'total_term_amount' => 0,
                'total_paid' => 0,
                'total_balance' => 0,
                'term_count' => 0,
                'earliest_due_date' => null,
                'overall_status' => 'paid',
                'payment_id' => (int) $r['payment_id'], // For backward compatibility
                'tx_note' => $r['notes'],           // From first term
                'terms' => []
            ];
        }

        $term = [
            'payment_id' => (int) $r['payment_id'],
            'amount' => (float) $r['amount'],
            'paid_amount' => (float) $r['paid_amount'],
            'balance' => (float) ($r['amount'] - $r['paid_amount']),
            'due_date' => $r['due_date'],
            'payment_status' => $r['payment_status'],
            'notes' => $r['notes']
        ];

        $groupedData[$sid]['terms'][] = $term;
        $groupedData[$sid]['term_count']++;
        $groupedData[$sid]['total_term_amount'] += $term['amount'];
        $groupedData[$sid]['total_paid'] += $term['paid_amount'];
        $groupedData[$sid]['total_balance'] += $term['balance'];

        // Determine earliest due date for unpaid terms
        if ($term['payment_status'] !== 'paid') {
            if (!$groupedData[$sid]['earliest_due_date'] || $term['due_date'] < $groupedData[$sid]['earliest_due_date']) {
                $groupedData[$sid]['earliest_due_date'] = $term['due_date'];
            }
        }

        // Determine overall status
        // Logic: if any term is overdue -> overdue; if any pending/partial -> pending; else all paid
        $isTermPaid = $term['payment_status'] === 'paid';
        $isTermOverdue = !$isTermPaid && $term['due_date'] && $term['due_date'] < $now;

        if ($isTermOverdue) {
            $groupedData[$sid]['overall_status'] = 'overdue';
        } elseif (!$isTermPaid && $groupedData[$sid]['overall_status'] !== 'overdue') {
            $groupedData[$sid]['overall_status'] = 'pending';
        }
    }

    // Apply Filter & Convert to indexed array
    $finalTransactions = [];
    foreach ($groupedData as $sid => $t) {
        $matches = false;

        if ($filter === 'all') {
            $matches = true;
        } elseif ($filter === 'completed') {
            $matches = ($t['overall_status'] === 'paid');
        } elseif ($filter === 'pending') {
            $matches = ($t['overall_status'] === 'pending' || $t['overall_status'] === 'overdue');
        } elseif ($filter === 'partial') {
            // A sale is partial if at least one term is specifically marked partial
            foreach ($t['terms'] as $term) {
                if ($term['payment_status'] === 'partial') {
                    $matches = true;
                    break;
                }
            }
        } elseif ($filter === 'overdue') {
            $matches = ($t['overall_status'] === 'overdue');
        }

        if ($matches) {
            $finalTransactions[] = $t;
        }
    }

    echo json_encode(['success' => true, 'data' => $finalTransactions]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
