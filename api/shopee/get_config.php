<?php
/**
 * api/shopee/get_config.php — Get current Shopee configuration status
 */
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT id, environment, partner_id, partner_key, shop_id, shop_region, token_expires_at, access_token, shop_name, is_active, 
        CASE WHEN access_token IS NOT NULL AND access_token != '' THEN 1 ELSE 0 END as has_token,
        CASE WHEN refresh_token IS NOT NULL AND refresh_token != '' THEN 1 ELSE 0 END as has_refresh,
        CASE WHEN partner_key IS NOT NULL AND partner_key != '' THEN 1 ELSE 0 END as has_key,
        created_at, updated_at
        FROM shopee_config WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        echo json_encode(['success' => true, 'configured' => false]);
        exit;
    }

    // Check token validity
    $tokenStatus = 'none';
    $tokenDaysLeft = 0;
    if ($config['has_token'] && $config['token_expires_at']) {
        $expiresAt = strtotime($config['token_expires_at']);
        $now = time();
        if ($expiresAt > $now) {
            $tokenStatus = 'valid';
            $tokenDaysLeft = round(($expiresAt - $now) / 86400, 1);
        } else {
            $tokenStatus = 'expired';
        }
    }

    // Count products
    $countStmt = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN mapping_status IN ('auto','manual') THEN 1 ELSE 0 END) as mapped,
        SUM(CASE WHEN mapping_status = 'unmapped' THEN 1 ELSE 0 END) as unmapped
        FROM shopee_product_mappings");
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'        => true,
        'configured'     => true,
        'environment'    => $config['environment'],
        'partner_id'     => $config['partner_id'],
        'partner_key'    => $config['partner_key'],
        'shop_id'        => $config['shop_id'],
        'shop_region'    => $config['shop_region'],
        'access_token'   => $config['access_token'] ?? '',
        'has_key'        => (bool) $config['has_key'],
        'authorized'     => (bool) $config['has_token'],
        'token_status'   => $tokenStatus,
        'token_days_left' => $tokenDaysLeft,
        'token_expires'  => $config['token_expires_at'],
        'products_total' => (int) ($counts['total'] ?? 0),
        'products_mapped' => (int) ($counts['mapped'] ?? 0),
        'products_unmapped' => (int) ($counts['unmapped'] ?? 0),
        'updated_at'     => $config['updated_at'],
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
