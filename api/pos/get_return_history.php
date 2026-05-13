<?php
// api/pos/get_return_history.php
// Returns all returns (and their items) processed against a given sale_id
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/database.php';

$sale_id = intval($_GET['sale_id'] ?? 0);
if (!$sale_id) {
    echo json_encode(['success' => false, 'error' => 'Missing sale_id']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Fetch all return headers for this sale
    $sqlReturns = "
        SELECT r.return_id, r.return_ref, r.refund_method, r.refund_amount,
               r.reason, r.status, r.created_at,
               u.full_name AS processed_by
        FROM pos_returns r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.sale_id = ? AND r.status = 'completed'
        ORDER BY r.created_at DESC
    ";
    $stmtR = $conn->prepare($sqlReturns);
    $stmtR->execute([$sale_id]);
    $returns = $stmtR->fetchAll(PDO::FETCH_ASSOC);

    // 2. For each return, fetch its items
    $sqlItems = "
        SELECT ri.sale_item_id, ri.variation_id, ri.product_name,
               ri.brand_name, ri.variation_name, ri.unit_type,
               ri.quantity, ri.price_at_sale, ri.refund_amount
        FROM pos_return_items ri
        WHERE ri.return_id = ?
    ";
    $stmtI = $conn->prepare($sqlItems);

    foreach ($returns as &$ret) {
        $stmtI->execute([$ret['return_id']]);
        $ret['items'] = $stmtI->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($ret);

    // 3. Build a map: sale_item_id => total_returned_qty
    //    so the UI knows how many of each item have already been returned
    $sqlReturned = "
        SELECT ri.sale_item_id, SUM(ri.quantity) AS total_returned
        FROM pos_return_items ri
        INNER JOIN pos_returns r ON ri.return_id = r.return_id
        WHERE r.sale_id = ? AND r.status = 'completed'
        GROUP BY ri.sale_item_id
    ";
    $stmtQ = $conn->prepare($sqlReturned);
    $stmtQ->execute([$sale_id]);
    $returned_qty_map = [];
    foreach ($stmtQ->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $returned_qty_map[$row['sale_item_id']] = (int) $row['total_returned'];
    }

    echo json_encode([
        'success' => true,
        'returns' => $returns,
        'returned_qty_map' => $returned_qty_map
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
