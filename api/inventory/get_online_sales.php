<?php
/**
 * api/inventory/get_online_sales.php
 * Returns paginated list of online_sale entries from stock_movements.
 */
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE)
    session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$query = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

try {
    $db = new Database();
    $conn = $db->getConnection();

    $params = [];
    $where = "WHERE sm.type IN ('online_sale', 'online_adjustment')";

    if (!empty($query)) {
        $term = "%{$query}%";
        $where .= " AND (p.product_name LIKE :q OR v.sku LIKE :q OR sm.reference LIKE :q OR sm.remarks LIKE :q)";
        $params[':q'] = $term;
    }

    $baseSql = "
        FROM stock_movements sm
        JOIN product_variations v ON sm.variation_id = v.variation_id
        JOIN products p           ON v.product_id = p.product_id
        LEFT JOIN users u         ON sm.created_by = u.id
        {$where}
    ";

    // Count
    $countStmt = $conn->prepare("SELECT COUNT(*) as total {$baseSql}");
    foreach ($params as $k => $v)
        $countStmt->bindValue($k, $v);
    $countStmt->execute();
    $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = (int) ceil($total / $limit);

    // Data
    $sql = "
        SELECT
            sm.movement_id,
            sm.type,
            sm.quantity,
            sm.previous_stock,
            sm.new_stock,
            sm.reference,
            sm.remarks,
            sm.created_at,
            v.variation_id,
            v.variation_name,
            v.sku,
            v.unit_type,
            p.product_name,
            p.brand_name,
            p.image_path,
            u.full_name as processed_by
        {$baseSql}
        ORDER BY sm.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($sql);
    foreach ($params as $k => $v)
        $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'records' => $records,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $total,
            'limit' => $limit,
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
