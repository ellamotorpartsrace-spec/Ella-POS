<?php
// api/categories/read.php
header("Content-Type: application/json");
require_once '../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $sql = "SELECT c.*, COUNT(DISTINCT p.product_id) as product_count 
            FROM categories c 
            LEFT JOIN products p ON c.category_id = p.category_id 
            GROUP BY c.category_id 
            ORDER BY c.sort_order ASC, c.category_name ASC";
    $stmt = $conn->query($sql);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $categories]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
