<?php
// api/inventory/get_price_history.php - Fetch product price history with filters and pagination
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Get parameters
    $search = trim($_GET['search'] ?? '');
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 50;
    $offset = ($page - 1) * $per_page;

    // 2. Build Query
    $where = ["1=1"];
    $params = [];

    if (!empty($search)) {
        $where[] = "(p.product_name LIKE ? OR p.brand_name LIKE ? OR v.variation_name LIKE ? OR v.sku LIKE ? OR v.barcode LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }

    if (!empty($date_from)) {
        $where[] = "DATE(h.changed_at) >= ?";
        $params[] = $date_from;
    }

    if (!empty($date_to)) {
        $where[] = "DATE(h.changed_at) <= ?";
        $params[] = $date_to;
    }

    $whereClause = implode(" AND ", $where);

    // 3. Get Total Count for Pagination
    $sqlCount = "
        SELECT COUNT(*) 
        FROM product_price_history h
        JOIN product_variations v ON h.variation_id = v.variation_id
        JOIN products p ON v.product_id = p.product_id
        WHERE $whereClause
    ";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();
    $total_pages = ceil($total / $per_page);

    // 4. Get Records
    $sqlRecords = "
        SELECT 
            h.*,
            v.variation_name, v.sku, v.barcode, v.unit_type,
            p.product_name, p.brand_name, p.image_path,
            u.username as changed_by_name
        FROM product_price_history h
        JOIN product_variations v ON h.variation_id = v.variation_id
        JOIN products p ON v.product_id = p.product_id
        LEFT JOIN users u ON h.user_id = u.id
        WHERE $whereClause
        ORDER BY h.changed_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmtRecords = $conn->prepare($sqlRecords);
    $stmtRecords->execute($params);
    $records = $stmtRecords->fetchAll(PDO::FETCH_ASSOC);

    // 5. Get Summary Stats (Last 30 days)
    $sqlStats = "
        SELECT 
            COUNT(*) as total_changes,
            COUNT(DISTINCT variation_id) as products_affected,
            MAX(changed_at) as last_change
        FROM product_price_history
        WHERE changed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ";
    $stats = $conn->query($sqlStats)->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'records' => $records,
        'stats' => $stats,
        'pagination' => [
            'total' => intval($total),
            'current_page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
