-- migrations/create_buyer_wallet_system.sql
-- Official migration for the Customer Wallet system

-- 1. Create buyer_wallet_logs table
CREATE TABLE IF NOT EXISTS `buyer_wallet_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `buyer_id` INT(11) NOT NULL,
    `user_id` INT(11) DEFAULT NULL,
    `type` ENUM('credit', 'debit') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `balance_after` DECIMAL(10,2) NOT NULL,
    `reference_type` VARCHAR(50) DEFAULT NULL,
    `reference_id` VARCHAR(50) DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_buyer_wallet` (`buyer_id`),
    KEY `idx_type_wallet` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Add wallet_balance column to buyers if it doesn't exist
DROP PROCEDURE IF EXISTS MigrateBuyerWallet;
DELIMITER //
CREATE PROCEDURE MigrateBuyerWallet()
BEGIN
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_NAME = 'buyers' AND COLUMN_NAME = 'wallet_balance' AND TABLE_SCHEMA = DATABASE()
    ) THEN
        ALTER TABLE `buyers` ADD COLUMN `wallet_balance` DECIMAL(10,2) NOT NULL DEFAULT '0.00' AFTER `credit_limit`;
    END IF;
END//
DELIMITER ;
CALL MigrateBuyerWallet();
DROP PROCEDURE MigrateBuyerWallet;
