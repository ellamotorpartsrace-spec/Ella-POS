<?php
// api/pos/scanner_register.php
// Manages registered mobile scanner devices (HWID-based auth)
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

// All management actions require login; verify action is open (mobile device self-check)
$action = $_REQUEST['action'] ?? 'list';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // === AUTO-CREATE TABLES IF NOT EXISTS ===
    $conn->exec("
        CREATE TABLE IF NOT EXISTS scanner_devices (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            device_name VARCHAR(100) NOT NULL,
            hwid        VARCHAR(128) NOT NULL UNIQUE,
            created_by  INT NOT NULL DEFAULT 0,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            status      ENUM('active','inactive') DEFAULT 'active'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $conn->exec("
        CREATE TABLE IF NOT EXISTS scanner_relay (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            hwid        VARCHAR(128) NOT NULL,
            terminal_id VARCHAR(64) NOT NULL,
            barcode     VARCHAR(255) NOT NULL,
            scanned_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            consumed    TINYINT(1) DEFAULT 0,
            INDEX idx_terminal_consumed (terminal_id, consumed),
            INDEX idx_scanned_at (scanned_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ========== VERIFY (mobile self-check — no login required) ==========
    if ($action === 'verify') {
        $hwid = trim($_REQUEST['hwid'] ?? '');
        if (empty($hwid)) {
            echo json_encode(['success' => false, 'error' => 'HWID is required']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, device_name, status FROM scanner_devices WHERE hwid = ? LIMIT 1");
        $stmt->execute([$hwid]);
        $device = $stmt->fetch();

        if (!$device) {
            echo json_encode(['success' => false, 'error' => 'Device not recognized. Please contact your admin to register this device.']);
            exit;
        }

        if ($device['status'] !== 'active') {
            echo json_encode(['success' => false, 'error' => 'This device has been deactivated. Contact your admin.']);
            exit;
        }

        echo json_encode(['success' => true, 'device_name' => $device['device_name']]);
        exit;
    }

    // All other actions require login
    requireLogin();

    // ========== LIST ==========
    if ($action === 'list') {
        $stmt = $conn->prepare("
            SELECT id, device_name, hwid, status, created_at
            FROM scanner_devices
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $devices = $stmt->fetchAll();

        // Mask hwid for display (show first 8 + last 4 chars)
        foreach ($devices as &$d) {
            $len = strlen($d['hwid']);
            $d['hwid_masked'] = substr($d['hwid'], 0, 8) . '...' . substr($d['hwid'], -4);
            $d['hwid_full'] = $d['hwid']; // Admin gets the full token
        }

        echo json_encode(['success' => true, 'data' => $devices]);
        exit;
    }

    // ========== REGISTER (add new device) ==========
    if ($action === 'register') {
        if (!hasPermission('make_sales') && !hasPermission('admin_settings') && !hasPermission('manage_settings')) {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $device_name = trim($input['device_name'] ?? '');

        if (empty($device_name)) {
            echo json_encode(['success' => false, 'error' => 'Device name is required']);
            exit;
        }

        // Generate a secure 64-char HWID token
        $hwid = bin2hex(random_bytes(32)); // 64 hex chars

        $stmt = $conn->prepare("
            INSERT INTO scanner_devices (device_name, hwid, created_by)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$device_name, $hwid, $_SESSION['user_id']]);
        $newId = $conn->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Device registered successfully',
            'id' => $newId,
            'hwid' => $hwid,
            'device_name' => $device_name
        ]);
        exit;
    }

    // ========== TOGGLE STATUS ==========
    if ($action === 'toggle') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int) ($input['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Invalid device ID']);
            exit;
        }

        $stmt = $conn->prepare("
            UPDATE scanner_devices
            SET status = IF(status = 'active', 'inactive', 'active')
            WHERE id = ?
        ");
        $stmt->execute([$id]);

        $stmt2 = $conn->prepare("SELECT status FROM scanner_devices WHERE id = ?");
        $stmt2->execute([$id]);
        $row = $stmt2->fetch();

        echo json_encode(['success' => true, 'new_status' => $row['status']]);
        exit;
    }

    // ========== DELETE ==========
    if ($action === 'delete') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $id = (int) ($input['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'Invalid device ID']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM scanner_devices WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Device removed']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
