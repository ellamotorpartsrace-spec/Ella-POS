<?php
// api/buyers/get_wallet_logs.php
// Fetches paginated wallet transaction logs with buyer filter & date range
header('Content-Type: application/json');
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

requireLogin();

if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager']) && !hasPermission('view_profit')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $buyerId = intval($_GET['buyer_id'] ?? 0);
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    $type = $_GET['type'] ?? '';   // 'credit', 'debit', or ''
    $search = trim($_GET['search'] ?? '');
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = 50;
    $offset = ($page - 1) * $perPage;

    // Build WHERE clauses
    $where = ['DATE(wl.created_at) BETWEEN ? AND ?'];
    $params = [$dateFrom, $dateTo];

    if ($buyerId > 0) {
        $where[] = 'wl.buyer_id = ?';
        $params[] = $buyerId;
    }
    if ($type === 'credit' || $type === 'debit') {
        $where[] = 'wl.type = ?';
        $params[] = $type;
    }
    if ($search !== '') {
        $where[] = '(b.buyer_name LIKE ? OR wl.reference_id LIKE ? OR wl.remarks LIKE ?)';
        $like = "%{$search}%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $whereSQL = 'WHERE ' . implode(' AND ', $where);

    // Summary stats
    $sqlStats = "
        SELECT
            COUNT(*)                                          AS total_logs,
            SUM(CASE WHEN wl.type='credit' THEN wl.amount ELSE 0 END) AS total_credits,
            SUM(CASE WHEN wl.type='debit'  THEN wl.amount ELSE 0 END) AS total_debits,
            COUNT(DISTINCT wl.buyer_id)                       AS unique_buyers
        FROM buyer_wallet_logs wl
        LEFT JOIN buyers b ON wl.buyer_id = b.buyer_id
        {$whereSQL}
    ";
    $stmtStats = $conn->prepare($sqlStats);
    $stmtStats->execute($params);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // Paginated logs
    $sqlLogs = "
        SELECT
            wl.id,
            wl.buyer_id,
            b.buyer_name,
            b.shop_name,
            wl.type,
            wl.amount,
            wl.balance_after,
            wl.reference_type,
            wl.reference_id,
            wl.remarks,
            wl.created_at,
            u.full_name AS cashier_name
        FROM buyer_wallet_logs wl
        LEFT JOIN buyers b ON wl.buyer_id = b.buyer_id
        LEFT JOIN users  u ON wl.user_id  = u.id
        {$whereSQL}
        ORDER BY wl.id DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $stmtLogs = $conn->prepare($sqlLogs);
    $stmtLogs->execute($params);
    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    // Count for pagination
    $sqlCount = "
        SELECT COUNT(*) FROM buyer_wallet_logs wl
        LEFT JOIN buyers b ON wl.buyer_id = b.buyer_id
        {$whereSQL}
    ";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalCount = (int) $stmtCount->fetchColumn();

    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'stats' => $stats,
        'total' => $totalCount,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($totalCount / $perPage),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
