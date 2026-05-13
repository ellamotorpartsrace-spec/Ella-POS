<?php
// api/pos/admin_list_draft_creators.php
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Must be admin
requireLogin();
if (!hasPermission('manage_settings') && $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin privileges required']);
    exit;
}

// Release session lock to prevent blocking concurrent API requests from admin dashboard
session_write_close();

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get unique creators from both POS and Restock drafts
    $sql = "SELECT DISTINCT u.id AS user_id, u.username, u.full_name
            FROM users u
            WHERE u.id IN (SELECT DISTINCT user_id FROM pos_drafts)
               OR u.id IN (SELECT DISTINCT user_id FROM restock_drafts)
            ORDER BY u.username ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $creators = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'creators' => $creators
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
