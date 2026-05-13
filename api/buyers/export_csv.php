<?php
// api/buyers/export_csv.php - Export Buyers to CSV
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Auth Check
requireLogin();
if ($_SESSION['role'] !== 'admin' && !in_array($_SESSION['role'], ['manager'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Fetch All Buyers
$stmt = $conn->query("SELECT * FROM buyers ORDER BY buyer_name ASC");
$buyers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
$filename = 'buyers_export_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV Headers
fputcsv($output, [
    'Buyer ID',
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

// CSV Data
foreach ($buyers as $b) {
    fputcsv($output, [
        $b['buyer_id'],
        $b['buyer_name'],
        $b['shop_name'] ?? '',
        $b['contact_number'] ?? '',
        $b['email'] ?? '',
        $b['address'] ?? '',
        $b['price_tier'],
        $b['is_walkin'],
        $b['credit_limit'] !== null ? number_format($b['credit_limit'], 2, '.', '') : '',
        $b['credit_notes'] ?? ''
    ]);
}

fclose($output);
exit;
