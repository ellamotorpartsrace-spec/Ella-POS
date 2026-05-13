<?php
// api/inventory/save_product.php
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/logger.php';

// 1. Security Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../views/inventory/index.php");
    exit;
}
requireLogin();

// 2. Get Data
$prod_name = trim($_POST['product_name']);
$brand = trim($_POST['brand_name'] ?? '');
$cat_id = $_POST['category_id'];
$desc = $_POST['description'] ?? '';

// Variation Data
$var_name = trim($_POST['variation_name']);
$unit_type = $_POST['unit_type'] ?? 'pc'; // <--- NEW UNIT TYPE
$barcode = trim($_POST['barcode'] ?? '');
$sku = trim($_POST['sku'] ?? '');
// Get price inputs first for dynamic calculation
$retail = $_POST['price_retail'];
$wholesale = $_POST['price_wholesale'];
$dealer = $_POST['price_dealer'];

// Validation: Wholesale/Dealer against Retail
if ($wholesale !== '' && $retail !== '' && (float) $wholesale > (float) $retail) {
    header("Location: ../../views/inventory/create.php?error=" . urlencode("Wholesale Price cannot be higher than Retail (SRP)."));
    exit;
}
if ($dealer !== '' && $retail !== '' && (float) $dealer > (float) $retail) {
    header("Location: ../../views/inventory/create.php?error=" . urlencode("Dealer Price cannot be higher than Retail (SRP)."));
    exit;
}

// Validation: Dealer Price cannot be > Wholesale Price
if ($wholesale !== '' && $dealer !== '') {
    if ((float) $dealer > (float) $wholesale) {
        header("Location: ../../views/inventory/create.php?error=" . urlencode("Dealer Price cannot be higher than Wholesale Price."));
        exit;
    }
} else if ($wholesale === '' && $dealer !== '') {
    header("Location: ../../views/inventory/create.php?error=" . urlencode("Wholesale Price is required if setting a Dealer Price."));
    exit;
}

// Security: Admins and managers can set capital directly
$canSetCapital = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager']);
if ($canSetCapital && $_POST['price_capital'] !== '') {
    $capital = $_POST['price_capital'];
} else {
    // Non-admins, or if capital is left blank: Default capital to the lower of Retail, Wholesale or Dealer price
    $prices = [];
    if (isset($_POST['price_retail']) && $_POST['price_retail'] !== '')
        $prices[] = (float) $_POST['price_retail'];
    if (isset($_POST['price_wholesale']) && $_POST['price_wholesale'] !== '')
        $prices[] = (float) $_POST['price_wholesale'];
    if (isset($_POST['price_dealer']) && $_POST['price_dealer'] !== '')
        $prices[] = (float) $_POST['price_dealer'];

    if (!empty($prices)) {
        $capital = min($prices);
    } else {
        $capital = 0.00;
    }
}
$stock = $_POST['initial_stock'];
$threshold = $_POST['low_stock_threshold'] ?? 5;

// 3. Handle Image Upload
$image_path = null;
if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../../assets/uploads/products/';

    // Create folder if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileTmpPath = $_FILES['product_image']['tmp_name'];
    $fileName = $_FILES['product_image']['name'];
    $fileSize = $_FILES['product_image']['size'];
    $fileType = $_FILES['product_image']['type'];

    // Sanitize filename and add unique timestamp
    $newFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName);
    $dest_path = $uploadDir . $newFileName;

    if (move_uploaded_file($fileTmpPath, $dest_path)) {
        // We store the path relative to BASE_URL in the DB
        $image_path = 'assets/uploads/products/' . $newFileName;
    }
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // START TRANSACTION
    $conn->beginTransaction();

    // A. Insert Parent Product (Now with image_path)
    $stmt = $conn->prepare("INSERT INTO products (product_name, brand_name, category_id, description, image_path) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$prod_name, $brand, $cat_id, $desc, $image_path]);
    $product_id = $conn->lastInsertId();

    // B. Insert Variation (Now with unit_type)
    // if (empty($var_name)) $var_name = "Standard"; // Removed default Standard
    if (empty($barcode))
        $barcode = null;
    if (empty($sku))
        $sku = null;

    $sqlVar = "INSERT INTO product_variations 
               (product_id, variation_name, unit_type, barcode, sku, price_capital, price_retail, price_wholesale, price_dealer, low_stock_threshold) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmtVar = $conn->prepare($sqlVar);
    $stmtVar->execute([$product_id, $var_name, $unit_type, $barcode, $sku, $capital, $retail, $wholesale, $dealer, $threshold]);
    $variation_id = $conn->lastInsertId();

    // C. Insert Initial Inventory
    if ($stock > 0) {
        $stmtInv = $conn->prepare("INSERT INTO inventory (variation_id, store_id, quantity) VALUES (?, 1, ?)");
        $stmtInv->execute([$variation_id, $stock]);

        // D. Record Stock Movement
        $stmtMov = $conn->prepare("INSERT INTO stock_movements (variation_id, type, quantity, previous_stock, new_stock, remarks, created_by, store_id, capital_cost) 
                                   VALUES (?, 'stock_in', ?, 0, ?, 'Initial Stock', ?, 1, ?)");
        $stmtMov->execute([$variation_id, $stock, $stock, $_SESSION['user_id'], $capital]);
    }

    // COMMIT
    $conn->commit();

    // Log Activity
    logActivity($conn, $_SESSION['user_id'], 'CREATE_PRODUCT', 'Inventory', "Created new product: $prod_name ($var_name)", (int) $variation_id);

    header("Location: " . BASE_URL . "views/inventory/index.php?success=created");

} catch (Exception $e) {
    $conn->rollBack();
    header("Location: " . BASE_URL . "views/inventory/create.php?error=" . urlencode($e->getMessage()));
}
?>