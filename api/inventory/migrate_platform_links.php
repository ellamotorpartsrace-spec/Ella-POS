<?php
/**
 * api/inventory/migrate_platform_links.php
 * Triggers the database migration to create the online_platform_links table.
 */
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die("Permission Denied. Only admins can run migrations.");
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $sql = "
        CREATE TABLE IF NOT EXISTS `online_platform_links` (
          `link_id` int(11) NOT NULL AUTO_INCREMENT,
          `variation_id` int(11) NOT NULL,
          `platform` varchar(50) NOT NULL COMMENT 'Shopee, Lazada, TikTok, Facebook, Other',
          `online_product_id` varchar(100) DEFAULT NULL COMMENT 'Optional parent product ID',
          `online_variation_id` varchar(100) NOT NULL COMMENT 'Platform assigned ID',
          `platform_sku` varchar(100) DEFAULT NULL COMMENT 'SKU alias on the platform',
          `is_active` tinyint(1) NOT NULL DEFAULT 1,
          `linked_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `linked_by` int(11) NOT NULL,
          PRIMARY KEY (`link_id`),
          UNIQUE KEY `idx_platform_online_var` (`platform`,`online_variation_id`),
          KEY `idx_variation_id` (`variation_id`),
          KEY `idx_linked_by` (`linked_by`),
          CONSTRAINT `fk_opl_variation` FOREIGN KEY (`variation_id`) REFERENCES `product_variations` (`variation_id`) ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT `fk_opl_user` FOREIGN KEY (`linked_by`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";

    $conn->exec($sql);
    
    echo "<h1>Migration Successful</h1>";
    echo "<p>The <code>online_platform_links</code> table has been verified and created.</p>";
    echo "<a href='" . BASE_URL . "views/inventory/online_stock.php'>Return to Online Stock Management</a>";

} catch (Exception $e) {
    http_response_code(500);
    echo "<h1>Migration Failed</h1>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
