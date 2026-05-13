<?php
// api/system/delete_role.php
header('Content-Type: application/json');
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/logger.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('manage_settings')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$role_slug = trim($input['role_slug'] ?? '');

if (empty($role_slug)) {
    echo json_encode(['success' => false, 'error' => 'Role slug is required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Verify role exists and is not a system role
    $stmtCheck = $conn->prepare("SELECT is_system, role_name FROM roles WHERE role_slug = ?");
    $stmtCheck->execute([$role_slug]);
    $roleData = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$roleData) {
        echo json_encode(['success' => false, 'error' => 'Role not found']);
        exit;
    }

    if ($roleData['is_system'] == 1) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete a core system role']);
        exit;
    }

    $conn->beginTransaction();

    // Delete the role
    $stmtDel = $conn->prepare("DELETE FROM roles WHERE role_slug = ?");
    $stmtDel->execute([$role_slug]);

    // Delete associated permissions
    $stmtPerms = $conn->prepare("DELETE FROM role_permissions WHERE role = ?");
    $stmtPerms->execute([$role_slug]);

    // Note: We do not delete users. Users with this role will effectively have 0 permissions.
    // They can be reassigned via views/users/edit.php.

    $conn->commit();

    logActivity($conn, $_SESSION['user_id'], 'DELETE_ROLE', 'System', "Deleted custom role: " . $roleData['role_name']);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
