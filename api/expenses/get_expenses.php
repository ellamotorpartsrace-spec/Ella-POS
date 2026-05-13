<?php
// api/expenses/get_expenses.php
// Get expenses list with optional date filters

header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

requireLogin();

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$category = $_GET['category'] ?? '';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $sql = "SELECT e.*, u.full_name as created_by_name 
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

    $sql .= " ORDER BY e.expense_date DESC, e.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    foreach ($expenses as $expense) {
        $total += floatval($expense['amount']);
    }

    echo json_encode([
        'success' => true,
        'expenses' => $expenses,
        'stats' => [
            'total' => $total,
            'count' => count($expenses)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
