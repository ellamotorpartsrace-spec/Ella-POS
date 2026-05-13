-- 
-- migration: create_online_platform_links.sql
-- Description: Creates the online_platform_links table to map local products to online platform variation IDs.
-- 

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
