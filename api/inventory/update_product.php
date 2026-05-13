<?php
// api/inventory/update_product.php
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

// 2. Get Data from Form
$prod_id = $_POST['product_id'];
$var_id = $_POST['variation_id'];

// Product Info
$prod_name = trim($_POST['product_name']);
$brand = trim($_POST['brand_name'] ?? '');
$cat_id = $_POST['category_id'];
$desc = $_POST['description'] ?? '';
$status = $_POST['status'];

// Variation Info
$var_name = trim($_POST['variation_name']);
$unit_type = $_POST['unit_type'] ?? 'pc';
$barcode = trim($_POST['barcode'] ?? '');
$sku = trim($_POST['sku'] ?? '');
$capital = (float) $_POST['price_capital'];
$retail = (float) $_POST['price_retail'];
$wholesale = (float) $_POST['price_wholesale'];
$dealer = (float) $_POST['price_dealer'];
$threshold = $_POST['low_stock_threshold'] ?? 5;

try {
    $db = new Database();
    $conn = $db->getConnection();
    $conn->beginTransaction();

    // ---------------------------------------------------------
    // A. PRICE HISTORY CHECK (AUDIT TRAIL)
    // ---------------------------------------------------------
    // 1. Fetch Existing (Old) Data for History & Permission Enforcement
    $stmtOld = $conn->prepare("SELECT price_capital, price_retail, price_wholesale, price_dealer FROM product_variations WHERE variation_id = ?");
    $stmtOld->execute([$var_id]);
    $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);

    if ($oldData) {
        $old_capital = (float) $oldData['price_capital'];
        $old_retail = (float) $oldData['price_retail'];
        $old_wholesale = (float) $oldData['price_wholesale'];
        $old_dealer = (float) $oldData['price_dealer'];

        // Enforce Permissions: Override submitted prices with old prices if not admin or manager
        $canSetCapital = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager']);
        if (!$canSetCapital) {
            $capital = $old_capital;
        } elseif ($_POST['price_capital'] === '') {
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
        if (!hasPermission('adjust_prices')) {
            $retail = $old_retail;
            $wholesale = $old_wholesale;
            $dealer = $old_dealer;
        }

        // 2. Compare Old vs New
        if (
            $old_capital != $capital ||
            $old_retail != $retail ||
            $old_wholesale != $wholesale ||
            $old_dealer != $dealer
        ) {
            // 3. Insert into History Table
            $sqlHistory = "INSERT INTO product_price_history 
                (variation_id, user_id, 
                 old_capital, new_capital, 
                 old_retail, new_retail, 
                 old_wholesale, new_wholesale, 
                 old_dealer, new_dealer) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmtHist = $conn->prepare($sqlHistory);
            $stmtHist->execute([
                $var_id,
                $_SESSION['user_id'] ?? 1, // Fallback ID if session lost
                $old_capital,
                $capital,
                $old_retail,
                $retail,
                $old_wholesale,
                $wholesale,
                $old_dealer,
                $dealer
            ]);
        }
    }

    // ---------------------------------------------------------
    // B. Handle Image Upload (Only if new file is selected)
    // ---------------------------------------------------------
    $image_sql_part = "";
    $params_prod = [$prod_name, $brand, $cat_id, $desc];

    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../assets/uploads/products/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);

        $fileName = $_FILES['product_image']['name'];
        $fileTmpPath = $_FILES['product_image']['tmp_name'];

        // Unique Name
        $newFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName);
        $dest_path = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            $image_sql_part = ", image_path=?";
            $params_prod[] = 'assets/uploads/products/' . $newFileName;
        }
    }

    // Add Product ID to params at the end
    $params_prod[] = $prod_id;

    // C. Update Parent Product
    $sqlProd = "UPDATE products SET product_name=?, brand_name=?, category_id=?, description=? $image_sql_part WHERE product_id=?";
    $stmt = $conn->prepare($sqlProd);
    $stmt->execute($params_prod);


    // ---------------------------------------------------------
    // D. Update Variation (Including Unit Type)
    // ---------------------------------------------------------
    if (empty($barcode))
        $barcode = null;
    if (empty($sku))
        $sku = null;

    $sqlVar = "UPDATE product_variations SET 
               variation_name=?, 
               unit_type=?, 
               barcode=?, 
               sku=?, 
               price_capital=?, 
               price_retail=?, 
               price_wholesale=?, 
               price_dealer=?, 
               low_stock_threshold=?,
               status=?
               WHERE variation_id=?";

    $stmtVar = $conn->prepare($sqlVar);
    $stmtVar->execute([$var_name, $unit_type, $barcode, $sku, $capital, $retail, $wholesale, $dealer, $threshold, $status, $var_id]);

    // ---------------------------------------------------------
    // E. QUICK STOCK ADJUSTMENT
    // ---------------------------------------------------------
    $adj_type = $_POST['stock_adj_type'] ?? null;
    $adj_qty = (float) ($_POST['stock_adj_qty'] ?? 0);
    $adj_remarks = trim($_POST['stock_adj_remarks'] ?? '');
    if (empty($adj_remarks)) {
        $adj_remarks = "Quick adjustment from edit page";
    }

    if ($adj_type && $adj_qty > 0) {
        // 1. Fetch current stock
        $stmtStock = $conn->prepare("SELECT quantity FROM inventory WHERE variation_id = ? AND store_id = 1");
        $stmtStock->execute([$var_id]);
        $prev_stock = (float) ($stmtStock->fetchColumn() ?: 0);

        $new_stock = ($adj_type === 'add') ? ($prev_stock + $adj_qty) : ($prev_stock - $adj_qty);

        // 2. Update inventory
        $stmtInv = $conn->prepare("INSERT INTO inventory (variation_id, store_id, quantity) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE quantity = ?");
        $stmtInv->execute([$var_id, $new_stock, $new_stock]);

        // 3. Record movement
        $type_code = ($adj_type === 'add') ? 'stock_in' : 'stock_out';
        $stmtMov = $conn->prepare("INSERT INTO stock_movements (variation_id, type, quantity, previous_stock, new_stock, remarks, created_by, store_id, capital_cost) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)");
        $stmtMov->execute([$var_id, $type_code, $adj_qty, $prev_stock, $new_stock, $adj_remarks, $_SESSION['user_id'], $capital]);

        logActivity($conn, $_SESSION['user_id'], strtoupper($type_code), 'Inventory', "Quick Stock $adj_type: $adj_qty units. Remarks: $adj_remarks", (int) $var_id);
    }

    $conn->commit();

    // Log Activity
    logActivity($conn, $_SESSION['user_id'], 'UPDATE_PRODUCT', 'Inventory', "Updated product details: $prod_name ($var_name)", $var_id);

    // ---------------------------------------------------------
    // H. Final Redirect (Preserving Search State)
    // ---------------------------------------------------------
    $return_url = $_POST['return_url'] ?? '../../views/inventory/index.php';
    if (strpos($return_url, '?') !== false) {
        $return_url .= "&success=updated";
    } else {
        $return_url .= "?success=updated";
    }

    header("Location: " . $return_url);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    header("Location: " . BASE_URL . "views/inventory/edit.php?id=$var_id&error=" . urlencode($e->getMessage()));
}
?>