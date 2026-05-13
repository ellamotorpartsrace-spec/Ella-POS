<?php
// api/system/get_activity_logs.php
header('Content-Type: application/json');
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('manage_settings')) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Filters
    $module = $_GET['module'] ?? '';
    $action_type = $_GET['action_type'] ?? '';
    $user_id = $_GET['user_id'] ?? '';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    $search = $_GET['search'] ?? '';

    // Pagination
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $offset = ($page - 1) * $limit;

    // Build Query
    $whereClauses = [];
    $params = [];

    if ($module) {
        $whereClauses[] = "l.module = ?";
        $params[] = $module;
    }
    if ($action_type) {
        $whereClauses[] = "l.action_type = ?";
        $params[] = $action_type;
    }
    if ($user_id) {
        $whereClauses[] = "l.user_id = ?";
        $params[] = $user_id;
    }
    if ($start_date) {
        $whereClauses[] = "DATE(l.created_at) >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $whereClauses[] = "DATE(l.created_at) <= ?";
        $params[] = $end_date;
    }
    if ($search) {
        $whereClauses[] = "(l.description LIKE ? OR l.action_type LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereSql = '';
    if (!empty($whereClauses)) {
        $whereSql = "WHERE " . implode(' AND ', $whereClauses);
    }

    // Get Total Count
    $countSql = "SELECT COUNT(*) FROM activity_logs l $whereSql";
    $stmtCount = $conn->prepare($countSql);

    // Bind filters for count
    $paramIndex = 1;
    foreach ($params as $param) {
        $stmtCount->bindValue($paramIndex++, $param);
    }
    $stmtCount->execute();
    $totalRecords = $stmtCount->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    // Get Data
    $sql = "
        SELECT 
            l.log_id, 
            l.action_type, 
            l.module, 
            l.description, 
            l.item_id, 
            l.ip_address, 
            l.created_at,
            u.full_name,
            u.username,
            u.role
        FROM activity_logs l
        LEFT JOIN users u ON l.user_id = u.id
        $whereSql
        ORDER BY l.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);

    // Bind all params dynamically
    $paramIndex = 1;
    foreach ($params as $param) {
        $stmt->bindValue($paramIndex++, $param);
    }
    // Bind LIMIT and OFFSET (must be integers)
    $stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);

    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'pagination' => [
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'limit' => $limit
        ]
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
