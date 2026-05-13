<?php
// api/system/get_role_permissions.php
header('Content-Type: application/json');
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('manage_settings')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Get all available permissions grouped by module
    $stmtPerms = $conn->query("SELECT slug, name, description, module FROM permissions ORDER BY module ASC, name ASC");
    $allPermissions = $stmtPerms->fetchAll(PDO::FETCH_ASSOC);

    // Group permissions by module for the UI
    $groupedPermissions = [];
    foreach ($allPermissions as $perm) {
        $module = $perm['module'];
        if (!isset($groupedPermissions[$module])) {
            $groupedPermissions[$module] = [];
        }
        $groupedPermissions[$module][] = $perm;
    }

    // 1.5 Get dynamic roles
    $stmtDynamic = $conn->query("SELECT role_slug FROM roles");
    $activeRoles = $stmtDynamic->fetchAll(PDO::FETCH_COLUMN);

    // 2. Get current role_permissions mappings
    $stmtRoles = $conn->query("SELECT role, permission_slug FROM role_permissions");
    $mappings = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);

    // Format mappings into a fast lookup object dynamically
    $rolePermissions = [];
    foreach ($activeRoles as $r) {
        $rolePermissions[$r] = [];
    }

    foreach ($mappings as $row) {
        $r = $row['role'];
        if (isset($rolePermissions[$r])) {
            $rolePermissions[$r][] = $row['permission_slug'];
        }
    }

    echo json_encode([
        'success' => true,
        'grouped_permissions' => $groupedPermissions,
        'role_permissions' => $rolePermissions
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
