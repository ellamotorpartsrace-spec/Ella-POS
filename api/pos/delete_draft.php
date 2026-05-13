<?php
// api/pos/delete_draft.php
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$draftId = isset($data['draft_id']) ? (int)$data['draft_id'] : 0;

if (!$draftId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Draft ID required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $userId = $_SESSION['user_id'];

    // Delete draft (items will cascade)
    $sql = "DELETE FROM pos_drafts WHERE draft_id = :id AND user_id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $draftId, ':user_id' => $userId]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Draft not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Draft deleted successfully'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
