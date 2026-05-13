<?php
// api/categories/update.php
header("Content-Type: application/json");
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['category_id'], $data['category_name']) || empty(trim($data['category_name']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Category ID and name are required']);
    exit;
}

$id = $data['category_id'];
$name = trim($data['category_name']);
$description = isset($data['description']) ? trim($data['description']) : null;
$color = isset($data['color']) ? trim($data['color']) : '#0d6efd';
$icon = isset($data['icon']) ? trim($data['icon']) : 'fa-tag';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check duplicate name excluding self
    $stmt = $conn->prepare("SELECT COUNT(*) FROM categories WHERE category_name = ? AND category_id != ?");
    $stmt->execute([$name, $id]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception("Another category with name '$name' already exists.");
    }

    $stmt = $conn->prepare("UPDATE categories SET category_name = ?, description = ?, color = ?, icon = ? WHERE category_id = ?");
    if ($stmt->execute([$name, $description, $color, $icon, $id])) {
        echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
    } else {
        throw new Exception("Failed to update category or no changes made");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
