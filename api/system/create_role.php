<?php
// api/system/create_role.php
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
$role_name = trim($input['role_name'] ?? '');

if (empty($role_name)) {
    echo json_encode(['success' => false, 'error' => 'Role name is required']);
    exit;
}

// Generate a slug: lowercase, replace non-alphanumeric with underscores
$role_slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $role_name));

// Prevent overriding core words
if (in_array($role_slug, ['admin', 'manager', 'cashier', 'stockman'])) {
    echo json_encode(['success' => false, 'error' => 'This role name is reserved by the system']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check if slug exists
    $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM roles WHERE role_slug = ?");
    $stmtCheck->execute([$role_slug]);
    if ($stmtCheck->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'A role with a similar name already exists']);
        exit;
    }

    // Insert new role
    $stmt = $conn->prepare("INSERT INTO roles (role_slug, role_name, is_system) VALUES (?, ?, 0)");
    $stmt->execute([$role_slug, $role_name]);

    logActivity($conn, $_SESSION['user_id'], 'CREATE_ROLE', 'System', "Created new custom role: $role_name");

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
