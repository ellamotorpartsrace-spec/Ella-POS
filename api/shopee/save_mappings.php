<?php
/**
 * api/shopee/save_mappings.php — Save Shopee product mappings to DB
 */
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$mappings = $input['mappings'] ?? [];
$trigger = $input['trigger'] ?? 'bulk_save';

if (empty($mappings)) {
    echo json_encode(['success' => false, 'error' => 'No mappings provided']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    $oldStmt = $conn->prepare("SELECT shopee_item_id, shopee_product_name, shopee_variation_name, shopee_variation_sku, shopee_parent_sku, matched_pos_sku, pos_product_id, mapping_status FROM shopee_product_mappings WHERE id = ?");

    $stmt = $conn->prepare("UPDATE shopee_product_mappings SET 
        matched_pos_sku = ?, 
        pos_product_id = ?, 
        mapping_status = ?, 
        updated_at = NOW() 
        WHERE id = ?");

    foreach ($mappings as $map) {
        $oldStmt->execute([$map['id']]);
        $oldMap = $oldStmt->fetch(PDO::FETCH_ASSOC);

        $hasChanged = false;
        if ($oldMap) {
            $oldSku = $oldMap['matched_pos_sku'];
            $oldPosId = $oldMap['pos_product_id'];
            $oldStatus = $oldMap['mapping_status'];

            $newSku = $map['posSku'] ?? null;
            $newPosId = $map['posId'] ?? null;
            $newStatus = $map['status'] ?? null;

            $oldSkuNorm = $oldSku !== null ? trim((string)$oldSku) : '';
            $newSkuNorm = $newSku !== null ? trim((string)$newSku) : '';
            $oldPosIdNorm = $oldPosId ? (int)$oldPosId : 0;
            $newPosIdNorm = $newPosId ? (int)$newPosId : 0;

            if ($oldSkuNorm !== $newSkuNorm || $oldPosIdNorm !== $newPosIdNorm || $oldStatus !== $newStatus) {
                $hasChanged = true;
            }
        }

        $stmt->execute([
            $map['posSku'],
            $map['posId'],
            $map['status'],
            $map['id']
        ]);

        if ($hasChanged && $oldMap) {
            $source = 'Bulk Save';
            if ($trigger === 'auto_match') {
                $source = 'Auto-Match';
            } elseif ($trigger === 're_run_auto_match') {
                $source = 'Re-run Auto-Match';
            } elseif ($trigger === 'manual_link') {
                $source = 'Manual Link';
            } elseif ($trigger === 'unlink') {
                $source = 'Manual Unlink';
            } else {
                if ($map['status'] === 'unmapped') {
                    $source = 'Manual Unlink';
                } elseif ($map['status'] === 'manual') {
                    $source = 'Manual Link';
                } elseif ($map['status'] === 'auto') {
                    $source = 'Auto-Match';
                }
            }

            $prodName = $oldMap['shopee_product_name'];
            if (!empty($oldMap['shopee_variation_name'])) {
                $prodName .= ' — ' . $oldMap['shopee_variation_name'];
            }

            $skuVal = $oldMap['shopee_variation_sku'] ?: $oldMap['shopee_parent_sku'] ?: '—';
            $oldValLog = $oldMap['matched_pos_sku'] ?: 'Unmapped';
            $newValLog = $map['posSku'] ?: 'Unmapped';

            $logStmt = $conn->prepare("
                INSERT INTO shopee_sync_logs (event_type, shopee_item_id, product_name, sku, old_value, new_value, source, status, created_by, created_at)
                VALUES ('mapping', ?, ?, ?, ?, ?, ?, 'success', ?, NOW())
            ");
            $logStmt->execute([
                $oldMap['shopee_item_id'],
                $prodName,
                $skuVal,
                $oldValLog,
                $newValLog,
                $source,
                $_SESSION['user_id'] ?? null
            ]);
        }

    }

    $conn->commit();

    // Re-run conflict detection to dynamically update shopee_error_logs in real-time
    require_once __DIR__ . '/detect_conflicts.php';
    runConflictDetection($conn);

    echo json_encode(['success' => true, 'message' => count($mappings) . ' mappings saved successfully']);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
