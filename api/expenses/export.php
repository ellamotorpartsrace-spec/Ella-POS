<?php
// api/expenses/export.php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="expenses_export_' . date('Ymd_His') . '.csv"');

require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/logger.php';

requireLogin();

if ($_SESSION['role'] !== 'admin' && !hasPermission('view_profit') && $_SESSION['role'] !== 'manager') {
    die("Unauthorized access");
}

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$category = $_GET['category'] ?? '';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $sql = "SELECT e.expense_date, e.category, e.amount, e.payment_source, e.reference_no, e.description, u.full_name as created_by_name, e.created_at
            FROM expenses e
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.expense_date BETWEEN :date_from AND :date_to";

    $params = [
        ':date_from' => $date_from,
        ':date_to' => $date_to
    ];

    if (!empty($category)) {
        $sql .= " AND e.category = :category";
        $params[':category'] = $category;
    }

    $sql .= " ORDER BY e.expense_date ASC, e.id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Category', 'Amount', 'Payment Source', 'Ref/OR Number', 'Description', 'Recorded By', 'Timestamp']);

    foreach ($expenses as $row) {
        fputcsv($output, [
            $row['expense_date'],
            $row['category'],
            $row['amount'],
            $row['payment_source'] ?: 'N/A',
            $row['reference_no'] ?: 'N/A',
            $row['description'],
            $row['created_by_name'],
            $row['created_at']
        ]);
    }
    
    fclose($output);
    logActivity($conn, $_SESSION['user_id'], 'EXPORT', 'EXPENSE', 'Exported expenses to CSV');

} catch (Exception $e) {
    die('Export failed: ' . $e->getMessage());
}
