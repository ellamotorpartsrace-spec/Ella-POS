<?php
// api/categories/get_products.php
header("Content-Type: application/json");
require_once '../../config/database.php';

if (!isset($_GET['category_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Category ID is required']);
    exit;
}

$categoryId = $_GET['category_id'];

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Fetch products belonging to the category, along with their variations to calculate total stock
    $sql = "SELECT p.product_id, p.product_name, p.brand_name as brand, 
                   GROUP_CONCAT(DISTINCT v.sku SEPARATOR ', ') as sku,
                   IFNULL(SUM(i.quantity), 0) as total_stock
            FROM products p
            LEFT JOIN product_variations v ON p.product_id = v.product_id
            LEFT JOIN inventory i ON v.variation_id = i.variation_id AND i.store_id = 1
            WHERE p.category_id = ?
            GROUP BY p.product_id
            ORDER BY p.product_name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$categoryId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $products]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
