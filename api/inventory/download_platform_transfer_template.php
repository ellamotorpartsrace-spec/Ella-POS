<?php
/**
 * api/inventory/download_platform_transfer_template.php
 * Generates a simple 2-column CSV template for bulk stock transfers.
 */
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../includes/auth.php';

requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('adjust_prices') && !in_array($_SESSION['role'], ['manager', 'stockman'])) {
    http_response_code(403);
    die("Permission Denied.");
}

$filename = "platform_transfer_template_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Column headers
fputcsv($output, ['Platform Product ID', 'Platform Variation ID', 'Quantity']);

// Sample data
fputcsv($output, ['244077212565', '54200831219', '10']);

fclose($output);
exit;
