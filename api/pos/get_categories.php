<?php
// api/pos/get_categories.php
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get categories that have at least one active product
    $sql = "
        SELECT DISTINCT c.category_id, c.category_name 
        FROM categories c
        INNER JOIN products p ON c.category_id = p.category_id
        WHERE c.status = 'active' AND p.status = 'active'
        ORDER BY c.category_name ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($categories ?: []);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
