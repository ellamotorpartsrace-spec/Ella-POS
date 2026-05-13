<?php
// api/users/update_profile.php
session_start();
header('Content-Type: application/json');
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

try {
    $db = new Database();
    $conn = $db->getConnection();

    $updates = [];
    $params = [];

    // 1. Update Username/Display Name
    if (!empty($data['username'])) {
        $updates[] = 'username = ?';
        $params[] = trim($data['username']);
        $_SESSION['username'] = trim($data['username']); // Update active session
    }

    // 2. Update Password (if provided)
    if (!empty($data['new_password'])) {
        $updates[] = 'password = ?';
        $params[] = password_hash($data['new_password'], PASSWORD_DEFAULT);
    }

    // 3. Update Preferences (JSON)
    if (isset($data['preferences']) && is_array($data['preferences'])) {
        // Fetch existing preferences first to merge, so we don't overwrite unrelated keys
        $stmt = $conn->prepare("SELECT preferences FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();

        $currentPrefs = [];
        if ($row && !empty($row['preferences'])) {
            $currentPrefs = json_decode($row['preferences'], true) ?: [];
        }

        // Merge new preferences over old ones
        $mergedPrefs = array_merge($currentPrefs, $data['preferences']);

        $updates[] = 'preferences = ?';
        $params[] = json_encode($mergedPrefs);

        // Update session so frontend sees it immediately without relogging
        $_SESSION['preferences'] = $mergedPrefs;
    }

    if (empty($updates)) {
        echo json_encode(['success' => true, 'message' => 'No changes made']);
        exit;
    }

    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    $params[] = $user_id; // Append id for the WHERE clause
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'session_preferences' => $_SESSION['preferences'] ?? []
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>