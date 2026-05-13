<?php
/**
 * api/inventory/download_bulk_link_template.php
 * Generates a clean CSV template for the Bulk Platform Link Importer.
 */
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../includes/auth.php';

requireLogin();
if (!hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['admin', 'manager', 'stockman'])) {
    http_response_code(403);
    die("Permission Denied");
}

$filename = "EllaPOS_Bulk_Link_Template_" . date('Ymd') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel visibility
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Define headers
$headers = [
    'Local SKU',
    'Platform (Shopee/Lazada/TikTok/Facebook)',
    'Online Variation ID',
    'Online Product ID (Optional)',
    'Platform SKU (Optional)'
];

fputcsv($output, $headers);

// Add a sample row to guide the user
$sample = [
    'ELLA-GR-001',
    'Shopee',
    '54200831219',
    '15200831219',
    'SHP-GR-001'
];
fputcsv($output, $sample);

fclose($output);
exit;
