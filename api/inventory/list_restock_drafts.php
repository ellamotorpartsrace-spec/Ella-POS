<?php
// api/inventory/list_restock_drafts.php
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        SELECT draft_id, draft_label, total_amount, created_at, supplier_name, items
        FROM restock_drafts 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $drafts = $stmt->fetchAll();

    // Parse items to get count for summary
    foreach ($drafts as &$draft) {
        $items = json_decode($draft['items'], true);
        $draft['item_count'] = is_array($items) ? count($items) : 0;
        unset($draft['items']); // Don't send full payload in list
    }

    echo json_encode([
        'success' => true,
        'drafts' => $drafts
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
