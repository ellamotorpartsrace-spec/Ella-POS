-- Migration: Add Granular Sales & Finance Permissions
-- Description: Adds individual permissions for Sales and Finance sub-modules and grants them to the 'manager' role.
-- Based on migrate_permissions.php

-- 1. Insert New Permissions into `permissions` table
INSERT IGNORE INTO `permissions` (`slug`, `name`, `description`, `module`) VALUES
('make_sales', 'Process POS Checkouts', 'Can process sales and checkouts', 'Sales'),
('view_sales', 'View Sales History', 'Can view previous receipts and sales records', 'Sales'),
('view_buyers', 'View Buyers', 'Can manage the buyers and customers database', 'Sales'),
('view_product_history', 'View Product History', 'Can view individual item transaction logs', 'Sales'),
('view_finance', 'View Financing', 'Can monitor Home Credit & installment data', 'Finance'),
('view_expenses', 'View Expenses', 'Can view business expenses module', 'Finance'),
('view_wallet_ledger', 'View Wallet Ledger', 'Can monitor all wallet credits and debits', 'Finance'),
('view_receivables', 'View Receivables', 'Can view debt collections and receivables', 'Finance'),
('view_payables', 'View Payables', 'Can view store payables', 'Finance'),
('manage_finance', 'Manage Finances', 'Can edit or void financial records', 'Finance');

-- 2. Grant these permissions to the 'manager' role in `role_permissions` table
INSERT IGNORE INTO `role_permissions` (`role`, `permission_slug`) VALUES
('manager', 'make_sales'),
('manager', 'view_sales'),
('manager', 'view_buyers'),
('manager', 'view_product_history'),
('manager', 'view_finance'),
('manager', 'view_expenses'),
('manager', 'view_wallet_ledger'),
('manager', 'view_receivables'),
('manager', 'view_payables'),
('manager', 'manage_finance');
