<?php
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    echo json_encode(['success' => false, 'error' => 'Admin or manager access required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$mode = $data['mode'] ?? 'full';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("INSERT INTO shopee_sync_queues (sync_mode, status, created_at) VALUES (?, 'processing', NOW())");
    $stmt->execute([$mode]);
    $queueId = $conn->lastInsertId();

    echo json_encode(['success' => true, 'queue_id' => $queueId]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
