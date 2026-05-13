<?php
// api/expenses/create_expense.php
// Creates a new simple expense record.

header("Content-Type: application/json");
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';
require_once '../../includes/logger.php';

requireLogin();

// Allow multipart/form-data 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$amount = floatval($_POST['amount'] ?? 0);
$category = trim($_POST['category'] ?? '');
$description = trim($_POST['description'] ?? '');
$expense_date = $_POST['expense_date'] ?? date('Y-m-d');
$reference_no = trim($_POST['reference_no'] ?? '');
$payment_source = trim($_POST['payment_source'] ?? '');

$is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
$recurrence_period = trim($_POST['recurrence_period'] ?? '');

if ($amount <= 0 || empty($category) || empty($expense_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

// Handle File Upload
$receipt_image = null;
if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../../assets/uploads/receipts/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileInfo = pathinfo($_FILES['receipt_image']['name']);
    $ext = strtolower($fileInfo['extension']);
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and PDF are allowed.']);
        exit;
    }

    $newFile = uniqid('rec_') . '.' . $ext;
    $targetPath = $uploadDir . $newFile;

    if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $targetPath)) {
        $receipt_image = $newFile;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
        exit;
    }
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $sql = "INSERT INTO expenses (amount, category, description, expense_date, reference_no, payment_source, receipt_image, is_recurring, recurrence_period, created_by) 
            VALUES (:amount, :category, :description, :expense_date, :reference_no, :payment_source, :receipt_image, :is_recurring, :recurrence_period, :user_id)";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        ':amount' => $amount,
        ':category' => $category,
        ':description' => $description,
        ':expense_date' => $expense_date,
        ':reference_no' => $reference_no ?: null,
        ':payment_source' => $payment_source ?: null,
        ':receipt_image' => $receipt_image,
        ':is_recurring' => $is_recurring,
        ':recurrence_period' => $recurrence_period ?: null,
        ':user_id' => $_SESSION['user_id']
    ]);

    if ($result) {
        $expense_id = $conn->lastInsertId();
        logActivity($conn, $_SESSION['user_id'], 'EXPENSE_CREATED', 'EXPENSE', "Recorded new expense: ₱" . number_format($amount, 2) . " ($category)", $expense_id);
        echo json_encode(['success' => true, 'message' => 'Expense recorded successfully', 'id' => $expense_id, 'receipt_image' => $receipt_image]);
    } else {
        throw new Exception("Failed to insert record.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
