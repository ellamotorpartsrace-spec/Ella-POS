<?php
// api/system/get_settings.php - Get all system settings
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->query("SELECT setting_key, setting_value, updated_at FROM system_settings ORDER BY setting_key");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = [
            'value' => $row['setting_value'],
            'updated_at' => $row['updated_at']
        ];
    }

    echo json_encode(['success' => true, 'settings' => $settings]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
