<?php
// api/receivables/ar_summary.php
// Returns one row per buyer with total AR balance, aging buckets, credit limit status
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

try {
    $db = new Database();
    $conn = $db->getConnection();

    $today = date('Y-m-d');

    $filter = $_GET['filter'] ?? 'active';
    $whereStatus = "AND psp.payment_status NOT IN ('paid', 'voided')";
    $having = "total_balance > 0";

    if ($filter === 'completed') {
        $whereStatus = "AND psp.payment_status NOT IN ('voided')";
        $having = "total_balance = 0";
    } elseif ($filter === 'partial') {
        $whereStatus = "AND psp.payment_status NOT IN ('voided')";
        $having = "total_balance > 0 AND total_paid > 0";
    } elseif ($filter === 'all') {
        $whereStatus = "AND psp.payment_status NOT IN ('voided')";
        $having = "1=1";
    }

    // One row per buyer (or walk-ins as a group) aggregating all open pay_later payments
    $sql = "
        SELECT
            b.buyer_id,
            s.walkin_name,
            COALESCE(b.buyer_name, s.walkin_name, 'Walk-in Customer') AS buyer_name,
            COALESCE(b.shop_name,  s.buyer_shop_name)                 AS shop_name,
            COALESCE(b.price_tier, s.price_tier, 'retail')            AS price_tier,
            b.contact_number,
            b.credit_limit,
            b.credit_notes,

            COUNT(DISTINCT s.sale_id)                                  AS open_invoices,

            SUM(psp.amount)                                            AS total_amount,
            SUM(psp.paid_amount)                                       AS total_paid,
            SUM(psp.amount - psp.paid_amount)                          AS total_balance,
            MIN(psp.due_date)                                          AS oldest_due_date,

            -- Aging: current = not yet due
            SUM(CASE WHEN psp.due_date IS NULL OR psp.due_date >= CURDATE()
                     THEN (psp.amount - psp.paid_amount) ELSE 0 END)  AS aging_current,

            -- 1–30 days overdue
            SUM(CASE WHEN psp.due_date < CURDATE()
                      AND DATEDIFF(CURDATE(), psp.due_date) BETWEEN 1 AND 30
                     THEN (psp.amount - psp.paid_amount) ELSE 0 END)  AS aging_30,

            -- 31–60 days overdue
            SUM(CASE WHEN psp.due_date < CURDATE()
                      AND DATEDIFF(CURDATE(), psp.due_date) BETWEEN 31 AND 60
                     THEN (psp.amount - psp.paid_amount) ELSE 0 END)  AS aging_60,

            -- 61–90 days overdue
            SUM(CASE WHEN psp.due_date < CURDATE()
                      AND DATEDIFF(CURDATE(), psp.due_date) BETWEEN 61 AND 90
                     THEN (psp.amount - psp.paid_amount) ELSE 0 END)  AS aging_90,

            -- 90+ days overdue
            SUM(CASE WHEN psp.due_date < CURDATE()
                      AND DATEDIFF(CURDATE(), psp.due_date) > 90
                     THEN (psp.amount - psp.paid_amount) ELSE 0 END)  AS aging_over90

        FROM pos_sale_payments psp
        INNER JOIN pos_sales s   ON psp.sale_id = s.sale_id
        LEFT  JOIN buyers   b   ON s.buyer_id   = b.buyer_id
        WHERE psp.payment_type   IN ('pay_later')
          $whereStatus
          AND s.status           != 'voided'
        GROUP BY b.buyer_id, s.walkin_name, buyer_name, shop_name, price_tier,
                 b.contact_number, b.credit_limit, b.credit_notes
        HAVING $having
        ORDER BY total_balance DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Compute global stats
    $totalBalance = 0;
    $totalOverdue = 0;
    $buyerCount = 0;
    $overLimitCount = 0;

    foreach ($rows as &$row) {
        $row['total_balance'] = (float) $row['total_balance'];
        $row['total_amount'] = (float) $row['total_amount'];
        $row['total_paid'] = (float) $row['total_paid'];
        $row['aging_current'] = (float) $row['aging_current'];
        $row['aging_30'] = (float) $row['aging_30'];
        $row['aging_60'] = (float) $row['aging_60'];
        $row['aging_90'] = (float) $row['aging_90'];
        $row['aging_over90'] = (float) $row['aging_over90'];
        $row['credit_limit'] = $row['credit_limit'] !== null ? (float) $row['credit_limit'] : null;
        $row['open_invoices'] = (int) $row['open_invoices'];

        $overdue = $row['aging_30'] + $row['aging_60'] + $row['aging_90'] + $row['aging_over90'];
        $row['total_overdue'] = $overdue;
        $row['available_credit'] = $row['credit_limit'] !== null
            ? max(0, $row['credit_limit'] - $row['total_balance'])
            : null;
        $row['over_limit'] = $row['credit_limit'] !== null && $row['total_balance'] > $row['credit_limit'];

        $totalBalance += $row['total_balance'];
        $totalOverdue += $overdue;
        $buyerCount++;
        if ($row['over_limit'])
            $overLimitCount++;
    }
    unset($row);

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_balance' => $totalBalance,
            'total_overdue' => $totalOverdue,
            'buyer_count' => $buyerCount,
            'over_limit_count' => $overLimitCount,
        ],
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
