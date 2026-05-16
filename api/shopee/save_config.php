<?php
/**
 * api/shopee/save_config.php — Save Shopee API credentials
 */
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    echo json_encode(['success' => false, 'error' => 'Admin or manager access required']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $data = json_decode(file_get_contents('php://input'), true);

    $environment = $data['environment'] ?? 'test';
    $partnerId   = trim($data['partner_id'] ?? '');
    $partnerKey  = trim($data['partner_key'] ?? '');
    $shopRegion  = trim($data['shop_region'] ?? 'PH');

    // Fetch existing active config
    $stmt = $conn->prepare("SELECT * FROM shopee_config WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($partnerId)) {
        echo json_encode(['success' => false, 'error' => 'Partner ID is required']);
        exit;
    }

    if (empty($partnerKey)) {
        if ($existing && !empty($existing['partner_key'])) {
            $partnerKey = $existing['partner_key'];
        } else {
            echo json_encode(['success' => false, 'error' => 'Partner Key is required']);
            exit;
        }
    }

    if ($existing) {
        // If environment or partner ID changed, we must wipe the existing token because it belongs to a different app/environment
        $clearToken = ($existing['environment'] !== $environment || $existing['partner_id'] !== $partnerId);
        
        $sql = "UPDATE shopee_config SET 
                    environment = ?, 
                    partner_id = ?, 
                    partner_key = ?, 
                    shop_region = ? ";
        if ($clearToken) {
            $sql .= ", access_token = NULL, refresh_token = NULL, token_expires_at = NULL, shop_id = NULL ";
        }
        $sql .= "WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$environment, $partnerId, $partnerKey, $shopRegion, $existing['id']]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO shopee_config (environment, partner_id, partner_key, shop_region, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([$environment, $partnerId, $partnerKey, $shopRegion]);
    }

    echo json_encode(['success' => true, 'message' => 'Shopee credentials saved (' . strtoupper($environment) . ' mode)']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
