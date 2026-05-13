<?php
// api/users/save_user.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
requirePermission('manage_users');

// Get Data
$id = $_POST['id'] ?? '';
$fullname = trim($_POST['full_name']);
$username = trim($_POST['username']);
$password = $_POST['password'] ?? '';
$role = $_POST['role'];

$db = new Database();
$conn = $db->getConnection();

try {
    if (empty($id)) {
        // --- CREATE NEW USER ---
        if (empty($password)) {
            throw new Exception("Password is required for new users.");
        }

        // ✅ SECURE HASHING
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, password, role, full_name)
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username, $hashed_password, $role, $fullname]);

    } else {
        // --- UPDATE EXISTING USER ---
        if (!empty($password)) {
            // Update WITH password (hashed)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users 
                    SET username=?, password=?, role=?, full_name=? 
                    WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username, $hashed_password, $role, $fullname, $id]);
        } else {
            // Update WITHOUT password
            $sql = "UPDATE users 
                    SET username=?, role=?, full_name=? 
                    WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username, $role, $fullname, $id]);
        }
    }

    header("Location: " . BASE_URL . "views/users/index.php?success=saved");
    exit;

} catch (Exception $e) {
    header("Location: " . BASE_URL . "views/users/index.php?error=" . urlencode($e->getMessage()));
    exit;
}
