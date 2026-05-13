<?php
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}
requireLogin();

$variation_id = isset($_GET['variation_id']) ? (int)$_GET['variation_id'] : 0;

if ($variation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Variation ID']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM product_units WHERE variation_id = ? ORDER BY created_at ASC");
    $stmt->execute([$variation_id]);
    $units = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $units]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
