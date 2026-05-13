<?php
// api/system/save_role_permissions.php
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

if (!isset($input['role_permissions']) || !is_array($input['role_permissions'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data format']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    // The data comes in as: { 'manager': ['slug1', 'slug2'], 'cashier': ['slug1'] }
    $role_permissions = $input['role_permissions'];

    // 0. Fetch valid dynamic roles
    $stmtValidRoles = $conn->query("SELECT role_slug FROM roles");
    $validRoles = $stmtValidRoles->fetchAll(PDO::FETCH_COLUMN);

    if (empty($validRoles)) {
        throw new Exception("No configurable roles found in database.");
    }

    // Create placeholders dynamically, e.g., (?, ?, ?)
    $placeholders = str_repeat('?,', count($validRoles) - 1) . '?';

    // 1. Clear existing permissions for all configurable roles
    $delStmt = $conn->prepare("DELETE FROM role_permissions WHERE role IN ($placeholders)");
    $delStmt->execute($validRoles);

    // 2. Insert new permissions safely
    $stmt = $conn->prepare("INSERT INTO role_permissions (role, permission_slug) VALUES (?, ?)");

    $inserted = 0;
    foreach ($role_permissions as $role => $slugs) {
        // Only allow configurable roles to be modified
        if (!in_array($role, $validRoles))
            continue;

        foreach ($slugs as $slug) {
            $stmt->execute([$role, $slug]);
            $inserted++;
        }
    }

    // 3. Commit and Log
    $conn->commit();

    logActivity($conn, $_SESSION['user_id'], 'UPDATE_PERMISSIONS', 'System', "Updated Role Permissions ($inserted granted)");

    echo json_encode(['success' => true, 'message' => 'Role permissions saved successfully']);

} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
