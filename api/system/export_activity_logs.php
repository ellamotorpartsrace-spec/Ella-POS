<?php
// api/system/export_activity_logs.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('manage_settings')) {
    http_response_code(403);
    echo "Permission denied";
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

    // Get Data
    $sql = "
        SELECT 
            l.log_id, 
            l.created_at,
            u.full_name,
            u.username,
            l.module, 
            l.action_type, 
            l.description, 
            l.item_id, 
            l.ip_address
        FROM activity_logs l
        LEFT JOIN users u ON l.user_id = u.id
        $whereSql
        ORDER BY l.created_at DESC
    ";

    $stmt = $conn->prepare($sql);

    // Bind all params dynamically
    $paramIndex = 1;
    foreach ($params as $param) {
        $stmt->bindValue($paramIndex++, $param);
    }

    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate CSV
    $filename = "activity_logs_" . date('Ymd_His') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 display
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

    fputcsv($output, ['Timestamp', 'User Full Name', 'Username', 'Module', 'Action', 'Description', 'Ref ID', 'IP Address']);

    foreach ($logs as $log) {
        fputcsv($output, [
            $log['created_at'],
            $log['full_name'] ?? 'System/Unknown',
            $log['username'] ?? '',
            $log['module'],
            $log['action_type'],
            $log['description'],
            $log['item_id'],
            $log['ip_address']
        ]);
    }

    fclose($output);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo "Error exporting logs: " . $e->getMessage();
}
