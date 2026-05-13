<?php
// api/pos/scanner_relay.php
// Relay bridge: Mobile posts barcodes → POS desktop polls to receive them
header("Content-Type: application/json; charset=UTF-8");

require_once '../../config/config.php';
require_once '../../config/database.php';

$action = $_REQUEST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = new Database();
    $conn = $db->getConnection();

    // =====================================================================
    // MOBILE → SERVER: POST a scanned barcode to the relay
    // Called by: mobile_scanner.php when a barcode is scanned
    // =====================================================================
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $hwid        = trim($input['hwid'] ?? '');
        $terminal_id = trim($input['terminal_id'] ?? '');
        $barcode     = trim($input['barcode'] ?? '');

        if (empty($hwid) || empty($terminal_id) || empty($barcode)) {
            echo json_encode(['success' => false, 'error' => 'hwid, terminal_id, and barcode are required']);
            exit;
        }

        // Validate HWID is registered and active
        $stmt = $conn->prepare("
            SELECT id FROM scanner_devices
            WHERE hwid = ? AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$hwid]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized device']);
            exit;
        }

        // Clean up old consumed + expired relay entries (older than 30 seconds)
        $conn->prepare("
            DELETE FROM scanner_relay
            WHERE consumed = 1 OR scanned_at < DATE_SUB(NOW(), INTERVAL 30 SECOND)
        ")->execute();

        // Prevent duplicate scans flooding (same barcode within 2s for same terminal)
        $dupCheck = $conn->prepare("
            SELECT id FROM scanner_relay
            WHERE terminal_id = ?
              AND barcode = ?
              AND consumed = 0
              AND scanned_at > DATE_SUB(NOW(), INTERVAL 2 SECOND)
            LIMIT 1
        ");
        $dupCheck->execute([$terminal_id, $barcode]);
        if ($dupCheck->fetch()) {
            echo json_encode(['success' => true, 'message' => 'Duplicate scan ignored']);
            exit;
        }

        // Insert relay entry
        $insert = $conn->prepare("
            INSERT INTO scanner_relay (hwid, terminal_id, barcode)
            VALUES (?, ?, ?)
        ");
        $insert->execute([$hwid, $terminal_id, $barcode]);

        echo json_encode(['success' => true, 'message' => 'Barcode relayed successfully']);
        exit;
    }

    // =====================================================================
    // POS DESKTOP → SERVER: Poll for pending barcode
    // Called by: scanner-mode.js every second
    // =====================================================================
    if ($method === 'GET' && $action === 'poll') {
        require_once '../../includes/auth.php';
        requireLogin();

        $terminal_id = trim($_GET['terminal_id'] ?? '');

        if (empty($terminal_id)) {
            echo json_encode(['success' => false, 'error' => 'terminal_id is required']);
            exit;
        }

        // Fetch the oldest unconsumed barcode for this terminal
        $stmt = $conn->prepare("
            SELECT id, barcode, hwid, scanned_at
            FROM scanner_relay
            WHERE terminal_id = ? AND consumed = 0
            ORDER BY scanned_at ASC
            LIMIT 1
        ");
        $stmt->execute([$terminal_id]);
        $row = $stmt->fetch();

        if (!$row) {
            // Nothing pending
            echo json_encode(['success' => true, 'barcode' => null]);
            exit;
        }

        // Mark as consumed immediately to prevent double-processing
        $conn->prepare("UPDATE scanner_relay SET consumed = 1 WHERE id = ?")->execute([$row['id']]);

        // Get device name for logging (optional)
        $devStmt = $conn->prepare("SELECT device_name FROM scanner_devices WHERE hwid = ? LIMIT 1");
        $devStmt->execute([$row['hwid']]);
        $dev = $devStmt->fetch();

        echo json_encode([
            'success'     => true,
            'barcode'     => $row['barcode'],
            'device_name' => $dev['device_name'] ?? 'Unknown Device',
            'scanned_at'  => $row['scanned_at']
        ]);
        exit;
    }

    // =====================================================================
    // GET terminals: list of active terminal IDs for the mobile to pick
    // Called by: mobile_scanner.php when user needs to select a POS terminal
    // =====================================================================
    if ($method === 'GET' && $action === 'terminals') {
        // No auth required — mobile needs this
        // Returns recently active terminals (those that polled in the last 5 minutes)
        $stmt = $conn->prepare("
            SELECT DISTINCT terminal_id, MAX(scanned_at) as last_activity
            FROM scanner_relay
            WHERE scanned_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            GROUP BY terminal_id
            ORDER BY last_activity DESC
        ");
        $stmt->execute();
        $terminals = $stmt->fetchAll();

        // Also provide a hard-coded set from config if the relay table is empty
        echo json_encode(['success' => true, 'terminals' => $terminals]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid request']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
