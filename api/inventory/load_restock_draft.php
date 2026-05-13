<?php
// api/inventory/load_restock_draft.php
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$draftId = $_GET['id'] ?? null;

if (!$draftId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No draft ID provided']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT * FROM restock_drafts WHERE draft_id = ? AND user_id = ?");
    $stmt->execute([$draftId, $_SESSION['user_id']]);
    $draft = $stmt->fetch();

    if (!$draft) {
        echo json_encode(['success' => false, 'error' => 'Draft not found or unauthorized']);
        exit;
    }

    // Decode JSON columns back to arrays
    $draft['items'] = json_decode($draft['items'], true) ?: [];
    $draft['credit_terms'] = json_decode($draft['credit_terms'], true) ?: [];

    echo json_encode([
        'success' => true,
        'draft' => $draft
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
