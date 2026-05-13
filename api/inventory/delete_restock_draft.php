<?php
// api/inventory/delete_restock_draft.php
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$draftId = $data['draft_id'] ?? null;

if (!$draftId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No draft ID provided']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("DELETE FROM restock_drafts WHERE draft_id = ? AND user_id = ?");
    $stmt->execute([$draftId, $_SESSION['user_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Draft deleted successfully'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
