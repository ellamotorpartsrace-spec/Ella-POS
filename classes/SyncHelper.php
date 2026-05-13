<?php
/**
 * classes/SyncHelper.php
 * Helper class to manage background synchronization of stock and sales between Ella POS and external platforms.
 */
declare(strict_types=1);

class SyncHelper {

    /**
     * Queues a stock update for all active platforms linked to a specific variation.
     * 
     * @param PDO $conn The database connection
     * @param int $variationId The internal variation_id
     */
    public static function queueStockUpdate(PDO $conn, int $variationId): bool {
        try {
            // 1. Find all active platform links for this variation
            $stmtLinks = $conn->prepare("
                SELECT platform, online_variation_id 
                FROM online_platform_links 
                WHERE variation_id = ? AND is_active = 1
            ");
            $stmtLinks->execute([$variationId]);
            $links = $stmtLinks->fetchAll(PDO::FETCH_ASSOC);

            if (empty($links)) {
                return false; // No platforms to sync
            }

            // 2. Get the current online stock count (store_id = 2)
            $stmtStock = $conn->prepare("SELECT quantity FROM inventory WHERE variation_id = ? AND store_id = 2");
            $stmtStock->execute([$variationId]);
            $currentStock = (int) ($stmtStock->fetchColumn() ?: 0);

            // 3. Insert into sync queue for each platform
            $stmtQueue = $conn->prepare("
                INSERT INTO api_sync_queue (variation_id, platform, new_quantity, status)
                VALUES (?, ?, ?, 'pending')
                ON DUPLICATE KEY UPDATE 
                    new_quantity = VALUES(new_quantity),
                    status = 'pending',
                    attempts = 0,
                    updated_at = NOW()
            ");

            foreach ($links as $link) {
                // Currently only Shopee is supported, but prepared for others
                $stmtQueue->execute([$variationId, $link['platform'], $currentStock]);
            }

            return true;
        } catch (Exception $e) {
            error_log("SyncHelper Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Queues all linked products for a full stock sync.
     * Useful for initial setup or after bulk changes.
     */
    public static function queueAllLinkedProducts(PDO $conn): int {
        try {
            $stmt = $conn->prepare("
                INSERT INTO api_sync_queue (variation_id, platform, new_quantity, status)
                SELECT l.variation_id, l.platform, IFNULL(i.quantity, 0), 'pending'
                FROM online_platform_links l
                LEFT JOIN inventory i ON l.variation_id = i.variation_id AND i.store_id = 2
                WHERE l.is_active = 1
                ON DUPLICATE KEY UPDATE 
                    new_quantity = VALUES(new_quantity),
                    status = 'pending',
                    attempts = 0,
                    updated_at = NOW()
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("SyncHelper Error: " . $e->getMessage());
            return 0;
        }
    }
}
