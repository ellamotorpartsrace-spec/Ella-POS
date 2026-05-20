<?php
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$mappingIds = $data['mapping_ids'] ?? [];
$posId = $data['pos_id'] ?? null;

if (empty($mappingIds) || !is_array($mappingIds)) {
    echo json_encode(['success' => false, 'error' => 'No items selected']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    $userId = $_SESSION['user_id'] ?? null;
    $time = date('Y-m-d H:i:s');

    if ($action === 'link') {
        if (!$posId) throw new Exception("POS ID is required for linking");

        // Fetch POS SKU from product_variations
        $stmt = $conn->prepare("SELECT sku FROM product_variations WHERE variation_id = ?");
        $stmt->execute([$posId]);
        $posSku = $stmt->fetchColumn();
        if (!$posSku) throw new Exception("Invalid POS product (sku empty or invalid variation)");

        // Prepare update
        $fetchStmt = $conn->prepare("SELECT shopee_item_id, shopee_product_name, shopee_variation_name, shopee_variation_sku, shopee_parent_sku, matched_pos_sku FROM shopee_product_mappings WHERE id = ?");
        $updateStmt = $conn->prepare("UPDATE shopee_product_mappings SET pos_product_id = ?, matched_pos_sku = ?, mapping_status = 'manual', updated_at = ? WHERE id = ?");
        $auditStmt = $conn->prepare("INSERT INTO shopee_audit_logs (user_id, action_type, target_type, target_id, new_value, created_at) VALUES (?, 'bulk_link', 'mapping', ?, ?, ?)");

        foreach ($mappingIds as $id) {
            $fetchStmt->execute([$id]);
            $oldMap = $fetchStmt->fetch(PDO::FETCH_ASSOC);
            
            $updateStmt->execute([$posId, $posSku, $time, $id]);
            $auditStmt->execute([$userId, $id, json_encode(['pos_id' => $posId]), $time]);

            if ($oldMap) {
                $prodName = $oldMap['shopee_product_name'];
                if (!empty($oldMap['shopee_variation_name'])) {
                    $prodName .= ' — ' . $oldMap['shopee_variation_name'];
                }
                
                $logStmt = $conn->prepare("
                    INSERT INTO shopee_sync_logs (event_type, shopee_item_id, product_name, sku, old_value, new_value, source, status, created_by, created_at)
                    VALUES ('mapping', ?, ?, ?, ?, ?, 'Bulk Link', 'success', ?, NOW())
                ");
                $shopeeSku = $oldMap['shopee_variation_sku'] ?: $oldMap['shopee_parent_sku'] ?: '—';
                $logStmt->execute([
                    $oldMap['shopee_item_id'],
                    $prodName,
                    $shopeeSku,
                    $oldMap['matched_pos_sku'] ?: 'Unmapped',
                    $posSku,
                    $userId
                ]);
            }

        }

    } elseif ($action === 'unlink') {
        // We must fetch the old pos_id before updating so we can Undo it
        $fetchStmt = $conn->prepare("SELECT shopee_item_id, shopee_product_name, shopee_variation_name, shopee_variation_sku, shopee_parent_sku, matched_pos_sku FROM shopee_product_mappings WHERE id = ?");
        $stmtOld = $conn->prepare("SELECT pos_product_id FROM shopee_product_mappings WHERE id = ?");
        
        $updateStmt = $conn->prepare("UPDATE shopee_product_mappings SET pos_product_id = NULL, matched_pos_sku = NULL, mapping_status = 'unmapped', updated_at = ? WHERE id = ?");
        $auditStmt = $conn->prepare("INSERT INTO shopee_audit_logs (user_id, action_type, target_type, target_id, old_value, created_at) VALUES (?, 'bulk_unlink', 'mapping', ?, ?, ?)");

        foreach ($mappingIds as $id) {
            $fetchStmt->execute([$id]);
            $oldMap = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            $stmtOld->execute([$id]);
            $oldPosId = $stmtOld->fetchColumn();
            
            $updateStmt->execute([$time, $id]);
            $auditStmt->execute([$userId, $id, json_encode(['pos_id' => $oldPosId]), $time]);

            if ($oldMap) {
                $prodName = $oldMap['shopee_product_name'];
                if (!empty($oldMap['shopee_variation_name'])) {
                    $prodName .= ' — ' . $oldMap['shopee_variation_name'];
                }
                
                $logStmt = $conn->prepare("
                    INSERT INTO shopee_sync_logs (event_type, shopee_item_id, product_name, sku, old_value, new_value, source, status, created_by, created_at)
                    VALUES ('mapping', ?, ?, ?, ?, 'Unmapped', 'Bulk Unlink', 'success', ?, NOW())
                ");
                $shopeeSku = $oldMap['shopee_variation_sku'] ?: $oldMap['shopee_parent_sku'] ?: '—';
                $logStmt->execute([
                    $oldMap['shopee_item_id'],
                    $prodName,
                    $shopeeSku,
                    $oldMap['matched_pos_sku'] ?: '—',
                    $oldMap['matched_pos_sku'] ?: 'Unmapped',
                    $userId
                ]);
            }
        }
    } else {
        throw new Exception("Invalid action");
    }

    $conn->commit();

    // Re-run conflict detection to dynamically update shopee_error_logs in real-time
    require_once __DIR__ . '/detect_conflicts.php';
    runConflictDetection($conn);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
