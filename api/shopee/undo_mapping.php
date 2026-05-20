<?php
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$mappingIds = $data['mappingIds'] ?? [];

if (empty($mappingIds) || !is_array($mappingIds)) {
    echo json_encode(['success' => false, 'error' => 'No items to undo']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    if ($action === 'link') {
        // Undo a Link = Unlink
        $fetchStmt = $conn->prepare("SELECT shopee_item_id, shopee_product_name, shopee_variation_name, shopee_variation_sku, shopee_parent_sku, matched_pos_sku FROM shopee_product_mappings WHERE id = ?");
        $updateStmt = $conn->prepare("UPDATE shopee_product_mappings SET pos_product_id = NULL, matched_pos_sku = NULL, mapping_status = 'unmapped' WHERE id = ?");
        foreach ($mappingIds as $id) {
            $fetchStmt->execute([$id]);
            $item = $fetchStmt->fetch(PDO::FETCH_ASSOC);
            
            $updateStmt->execute([$id]);
            
            if ($item) {
                $prodName = $item['shopee_product_name'];
                if (!empty($item['shopee_variation_name'])) {
                    $prodName .= ' — ' . $item['shopee_variation_name'];
                }
                
                $logStmt = $conn->prepare("
                    INSERT INTO shopee_sync_logs (event_type, shopee_item_id, product_name, sku, old_value, new_value, source, status, created_by, created_at)
                    VALUES ('mapping', ?, ?, ?, ?, 'Unmapped', 'Undo Link', 'success', ?, NOW())
                ");
                $shopeeSku = $item['shopee_variation_sku'] ?: $item['shopee_parent_sku'] ?: '—';
                $logStmt->execute([
                    $item['shopee_item_id'],
                    $prodName,
                    $shopeeSku,
                    $item['matched_pos_sku'] ?: 'Unmapped',
                    $_SESSION['user_id'] ?? null
                ]);
            }
        }
    } elseif ($action === 'unlink') {
        // Undo an Unlink = Restore previous POS ID
        $fetchStmt = $conn->prepare("SELECT shopee_item_id, shopee_product_name, shopee_variation_name, shopee_variation_sku, shopee_parent_sku FROM shopee_product_mappings WHERE id = ?");
        $auditStmt = $conn->prepare("SELECT old_value FROM shopee_audit_logs WHERE action_type = 'bulk_unlink' AND target_id = ? ORDER BY created_at DESC LIMIT 1");
        $skuStmt = $conn->prepare("SELECT sku FROM product_variations WHERE variation_id = ?");
        $updateStmt = $conn->prepare("UPDATE shopee_product_mappings SET pos_product_id = ?, matched_pos_sku = ?, mapping_status = 'manual' WHERE id = ?");

        foreach ($mappingIds as $id) {
            $fetchStmt->execute([$id]);
            $item = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            $auditStmt->execute([$id]);
            $log = $auditStmt->fetchColumn();
            if ($log) {
                $oldData = json_decode($log, true);
                if (!empty($oldData['pos_id'])) {
                    $posId = $oldData['pos_id'];
                    $skuStmt->execute([$posId]);
                    $posSku = $skuStmt->fetchColumn();
                    if ($posSku) {
                        $updateStmt->execute([$posId, $posSku, $id]);
                        
                        if ($item) {
                            $prodName = $item['shopee_product_name'];
                            if (!empty($item['shopee_variation_name'])) {
                                $prodName .= ' — ' . $item['shopee_variation_name'];
                            }
                            
                            $logStmt = $conn->prepare("
                                INSERT INTO shopee_sync_logs (event_type, shopee_item_id, product_name, sku, old_value, new_value, source, status, created_by, created_at)
                                VALUES ('mapping', ?, ?, ?, 'Unmapped', ?, 'Undo Unlink', 'success', ?, NOW())
                            ");
                            $shopeeSku = $item['shopee_variation_sku'] ?: $item['shopee_parent_sku'] ?: '—';
                            $logStmt->execute([
                                $item['shopee_item_id'],
                                $prodName,
                                $shopeeSku,
                                $posSku,
                                $_SESSION['user_id'] ?? null
                            ]);
                        }
                    }
                }
            }
        }
    } else {
        throw new Exception("Unknown action to undo");
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
