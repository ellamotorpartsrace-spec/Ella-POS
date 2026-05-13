<?php
// api/buyers/download_template.php - Buyers CSV Template
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Auth Check
requireLogin();

// Set headers for CSV download
$filename = 'buyers_template.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV Headers (Matches export but removes ID as it's for new buyers)
// Actually, I can keep the ID header so they know they can use it for updates too.
fputcsv($output, [
    'Buyer Name',
    'Shop Name',
    'Contact Number',
    'Email',
    'Address',
    'Price Tier',
    'Is Walk-in (0/1)',
    'Credit Limit',
    'Credit Notes'
]);

// Add an example row
fputcsv($output, [
    'Juan Dela Cruz',
    'Juan Auto Parts',
    '09123456789',
    'juan@example.com',
    'Manila, Philippines',
    'retail',
    '0',
    '5000',
    'Good payer'
]);

fclose($output);
exit;
