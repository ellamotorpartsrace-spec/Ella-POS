<?php
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/logger.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}
requireLogin();

$variation_id = isset($_POST['variation_id']) ? (int) $_POST['variation_id'] : 0;
$unit_name = trim($_POST['unit_name'] ?? '');
$multiplier = isset($_POST['multiplier']) ? (int) $_POST['multiplier'] : 0;
$barcode = trim($_POST['barcode'] ?? '');
$description = trim($_POST['description'] ?? '');
$price_capital = isset($_POST['price_capital']) ? (float) $_POST['price_capital'] : 0.00;
$price_retail = isset($_POST['price_retail']) ? (float) $_POST['price_retail'] : 0.00;
$price_wholesale = isset($_POST['price_wholesale']) ? (float) $_POST['price_wholesale'] : 0.00;
$price_dealer = isset($_POST['price_dealer']) ? (float) $_POST['price_dealer'] : 0.00;

// Security check: Ignore frontend capital if user is not admin
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
if (!$isAdmin) {
    $price_capital = 0.00; // Will be calculated after fetching base capital
}

if ($variation_id <= 0 || empty($unit_name) || $multiplier < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data. Multiplier must be at least 1.']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check if barcode already exists in parent table or units table
    if (!empty($barcode)) {
        $stmtChk = $conn->prepare("SELECT barcode FROM product_variations WHERE barcode = ? UNION SELECT barcode FROM product_units WHERE barcode = ?");
        $stmtChk->execute([$barcode, $barcode]);
        if ($stmtChk->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Barcode already exists.']);
            exit;
        }
    }

    // If user is not admin, automatically calculate capital based on multiplier
    if (!$isAdmin) {
        $stmtBaseCap = $conn->prepare("SELECT price_capital FROM product_variations WHERE variation_id = ?");
        $stmtBaseCap->execute([$variation_id]);
        $baseData = $stmtBaseCap->fetch(PDO::FETCH_ASSOC);
        if ($baseData) {
            $price_capital = (float) $baseData['price_capital'] * $multiplier;
        }
    }

    $sql = "INSERT INTO product_units 
            (variation_id, unit_name, multiplier, barcode, description, price_capital, price_retail, price_wholesale, price_dealer) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$variation_id, $unit_name, $multiplier, empty($barcode) ? null : $barcode, empty($description) ? null : $description, $price_capital, $price_retail, $price_wholesale, $price_dealer]);

    $new_id = $conn->lastInsertId();

    if (function_exists('logActivity')) {
        logActivity($conn, $_SESSION['user_id'], 'CREATE_UNIT', 'Inventory', "Created unit $unit_name for variation $variation_id");
    }

    echo json_encode(['success' => true, 'message' => 'Unit created successfully.', 'id' => $new_id]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
