<?php
// api/pos/list_transactions.php
header("Content-Type: application/json");
require_once '../../config/database.php';

$date = $_GET['date'] ?? date('Y-m-d');

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Updated to match your pos_sales table columns
    $sql = "
        SELECT 
            s.sale_id,
            s.sale_ref,
            s.grand_total,
            s.status,
            s.created_at,
            s.walkin_name
        FROM pos_sales s
        WHERE DATE(s.created_at) = ?
        ORDER BY s.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$date]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results ?: []);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}