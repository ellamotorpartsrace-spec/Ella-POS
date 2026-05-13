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

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$unit_name = trim($_POST['unit_name'] ?? '');
$multiplier = isset($_POST['multiplier']) ? (int) $_POST['multiplier'] : 0;
$barcode = trim($_POST['barcode'] ?? '');
$description = trim($_POST['description'] ?? '');
$price_capital = isset($_POST['price_capital']) ? (float) $_POST['price_capital'] : 0.00;
$price_retail = isset($_POST['price_retail']) ? (float) $_POST['price_retail'] : 0.00;
$price_wholesale = isset($_POST['price_wholesale']) ? (float) $_POST['price_wholesale'] : 0.00;
$price_dealer = isset($_POST['price_dealer']) ? (float) $_POST['price_dealer'] : 0.00;

// Security check: We will override this later if user lacks permission
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if ($id <= 0 || empty($unit_name) || $multiplier < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data.']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check barcode
    if (!empty($barcode)) {
        $stmtChk = $conn->prepare("SELECT id FROM product_units WHERE barcode = ? AND id != ?");
        $stmtChk->execute([$barcode, $id]);
        if ($stmtChk->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Barcode already exists.']);
            exit;
        }
    }

    // If user is not admin, use the existing capital
    if (!$isAdmin) {
        $stmtBaseCap = $conn->prepare("SELECT price_capital FROM product_units WHERE id = ?");
        $stmtBaseCap->execute([$id]);
        $baseData = $stmtBaseCap->fetch(PDO::FETCH_ASSOC);
        if ($baseData) {
            $price_capital = (float) $baseData['price_capital'];
        }
    }

    $sql = "UPDATE product_units 
            SET unit_name = ?, multiplier = ?, barcode = ?, description = ?, price_capital = ?, price_retail = ?, price_wholesale = ?, price_dealer = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$unit_name, $multiplier, empty($barcode) ? null : $barcode, empty($description) ? null : $description, $price_capital, $price_retail, $price_wholesale, $price_dealer, $id]);

    if (function_exists('logActivity')) {
        logActivity($conn, $_SESSION['user_id'], 'UPDATE_UNIT', 'Inventory', "Updated unit ID $id ($unit_name)");
    }

    echo json_encode(['success' => true, 'message' => 'Unit updated successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
