<?php
// api/categories/reorder.php
header("Content-Type: application/json");
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['order']) || !is_array($data['order'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order data is required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $conn->beginTransaction();

    $stmt = $conn->prepare("UPDATE categories SET sort_order = ? WHERE category_id = ?");

    foreach ($data['order'] as $item) {
        if (isset($item['id']) && isset($item['sort_order'])) {
            $stmt->execute([$item['sort_order'], $item['id']]);
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Order updated successfully']);
} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
