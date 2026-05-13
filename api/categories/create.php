<?php
// api/categories/create.php
header("Content-Type: application/json");
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['category_name']) || empty(trim($data['category_name']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Category name is required']);
    exit;
}

$categoryName = trim($data['category_name']);
$description = isset($data['description']) ? trim($data['description']) : null;
$color = isset($data['color']) ? trim($data['color']) : '#0d6efd';
$icon = isset($data['icon']) ? trim($data['icon']) : 'fa-tag';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check if duplicate
    $stmt = $conn->prepare("SELECT COUNT(*) FROM categories WHERE category_name = ?");
    $stmt->execute([$categoryName]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("Category '$categoryName' already exists.");
    }

    // Get max sort_order
    $stmtSort = $conn->query("SELECT IFNULL(MAX(sort_order), 0) FROM categories");
    $nextSortOrder = $stmtSort->fetchColumn() + 1;

    $stmt = $conn->prepare("INSERT INTO categories (category_name, description, color, icon, sort_order) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$categoryName, $description, $color, $icon, $nextSortOrder])) {
        echo json_encode(['success' => true, 'message' => 'Category created successfully']);
    } else {
        throw new Exception("Failed to create category");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
