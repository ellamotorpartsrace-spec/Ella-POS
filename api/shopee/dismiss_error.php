<?php
// api/shopee/dismiss_error.php
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid error ID']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Resolve the error by updating its status
    $stmt = $conn->prepare("UPDATE shopee_error_logs SET status = 'resolved' WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Error conflict dismissed successfully.'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
