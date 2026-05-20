<?php
header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();

/**
 * Reusable core conflict detection engine
 */
function runConflictDetection($conn) {
    // Ensure SKU indexes exist for high-performance conflict scans (self-healing)
    try {
        $indexes = $conn->query("SHOW INDEX FROM shopee_product_mappings WHERE Key_name = 'idx_parent_sku'")->fetchAll();
        if (empty($indexes)) {
            $conn->exec("ALTER TABLE shopee_product_mappings ADD INDEX idx_parent_sku (shopee_parent_sku)");
        }
        $indexes2 = $conn->query("SHOW INDEX FROM shopee_product_mappings WHERE Key_name = 'idx_variation_sku'")->fetchAll();
        if (empty($indexes2)) {
            $conn->exec("ALTER TABLE shopee_product_mappings ADD INDEX idx_variation_sku (shopee_variation_sku)");
        }
    } catch (Exception $idxEx) {
        // Silently log or ignore index errors to prevent blocking the scan
    }

    // 1. Clear old open conflicts
    $conn->exec("DELETE FROM shopee_error_logs WHERE status = 'open'");

    $errors = 0;

    // 2. Detect Missing SKUs
    // Missing parent SKU if no variations
    $stmt = $conn->query("
        SELECT id, shopee_item_id, shopee_model_id, shopee_product_name, shopee_variation_name
        FROM shopee_product_mappings 
        WHERE has_variation = 0 AND (shopee_parent_sku IS NULL OR shopee_parent_sku = '')
    ");
    $missingParents = $stmt->fetchAll();
    
    // Missing variation SKU if has variations
    $stmt = $conn->query("
        SELECT id, shopee_item_id, shopee_model_id, shopee_product_name, shopee_variation_name
        FROM shopee_product_mappings 
        WHERE has_variation = 1 AND (shopee_variation_sku IS NULL OR shopee_variation_sku = '')
    ");
    $missingVars = $stmt->fetchAll();

    $insertStmt = $conn->prepare("
        INSERT INTO shopee_error_logs (error_type, shopee_item_id, shopee_model_id, error_message)
        VALUES ('missing_sku', ?, ?, ?)
    ");

    foreach ($missingParents as $row) {
        $insertStmt->execute([$row['shopee_item_id'], $row['shopee_model_id'], "Product '{$row['shopee_product_name']}' is missing a SKU."]);
        $errors++;
    }
    foreach ($missingVars as $row) {
        $insertStmt->execute([$row['shopee_item_id'], $row['shopee_model_id'], "Variation '{$row['shopee_variation_name']}' under '{$row['shopee_product_name']}' is missing a SKU."]);
        $errors++;
    }

    // Reset all non-mapped items to 'unmapped' before applying new dynamic issues
    $conn->exec("
        UPDATE shopee_product_mappings 
        SET mapping_status = 'unmapped' 
        WHERE mapping_status NOT IN ('manual', 'auto')
    ");

    // 3. Detect Duplicate SKUs within Shopee (two shopee items with the same SKU) using UNION ALL (optimized for indexes!)
    $stmt = $conn->query("
        SELECT match_sku, COUNT(*) as cnt FROM (
            SELECT shopee_parent_sku AS match_sku FROM shopee_product_mappings 
            WHERE has_variation = 0 AND shopee_parent_sku != '' AND shopee_parent_sku IS NOT NULL AND mapping_status != 'missing_sku'
            UNION ALL
            SELECT shopee_variation_sku AS match_sku FROM shopee_product_mappings 
            WHERE has_variation = 1 AND shopee_variation_sku != '' AND shopee_variation_sku IS NOT NULL AND mapping_status != 'missing_sku'
        ) as combined
        GROUP BY match_sku
        HAVING cnt > 1
    ");
    $duplicateSkus = $stmt->fetchAll();

    $dupInsertStmt = $conn->prepare("
        INSERT INTO shopee_error_logs (error_type, sku, error_message)
        VALUES ('duplicate_sku', ?, ?)
    ");

    foreach ($duplicateSkus as $row) {
        $dupInsertStmt->execute([$row['match_sku'], "SKU '{$row['match_sku']}' is used by multiple Shopee products. This will cause stock sync conflicts."]);
        $errors++;
    }

    // Single highly optimized JOIN query to mark all unmapped duplicate SKUs in one go (uses indexes!)
    if (!empty($duplicateSkus)) {
        $conn->exec("
            UPDATE shopee_product_mappings spm
            JOIN (
                SELECT match_sku FROM (
                    SELECT shopee_parent_sku AS match_sku FROM shopee_product_mappings 
                    WHERE has_variation = 0 AND shopee_parent_sku != '' AND shopee_parent_sku IS NOT NULL AND mapping_status != 'missing_sku'
                    UNION ALL
                    SELECT shopee_variation_sku AS match_sku FROM shopee_product_mappings 
                    WHERE has_variation = 1 AND shopee_variation_sku != '' AND shopee_variation_sku IS NOT NULL AND mapping_status != 'missing_sku'
                ) as combined
                GROUP BY match_sku
                HAVING COUNT(*) > 1
            ) dup ON (spm.has_variation = 0 AND spm.shopee_parent_sku = dup.match_sku) 
                  OR (spm.has_variation = 1 AND spm.shopee_variation_sku = dup.match_sku)
            SET spm.mapping_status = 'duplicate'
            WHERE spm.mapping_status NOT IN ('manual', 'auto')
        ");
    }

    // Update missing_sku status as well (only for unmapped ones!)
    $conn->exec("
        UPDATE shopee_product_mappings 
        SET mapping_status = 'missing_sku' 
        WHERE mapping_status NOT IN ('manual', 'auto')
          AND (
            (has_variation = 0 AND (shopee_parent_sku IS NULL OR shopee_parent_sku = ''))
            OR (has_variation = 1 AND (shopee_variation_sku IS NULL OR shopee_variation_sku = ''))
          )
    ");

    return $errors;
}

// Only execute inline if this script is executed directly
if (basename($_SERVER['SCRIPT_FILENAME']) === 'detect_conflicts.php') {
    try {
        $db = new Database();
        $conn = $db->getConnection();

        $errors = runConflictDetection($conn);

        echo json_encode([
            'success' => true,
            'conflicts_detected' => $errors,
            'message' => "Detected {$errors} SKU conflicts."
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
