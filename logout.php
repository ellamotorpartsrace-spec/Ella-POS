<?php
// logout.php
declare(strict_types=1);

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/logger.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. Mark Database Session as Inactive (Audit Trail)
if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("UPDATE user_sessions SET is_active = 0 WHERE session_token = :token");
        $stmt->execute([':token' => $_SESSION['session_token']]);
        
        // Log Activity
        logActivity($conn, $_SESSION['user_id'], 'LOGOUT', 'Auth', "User logged out");
        
    } catch (Exception $e) {
        // If DB fails, we continue logging out locally anyway
    }
}

// 2. Destroy Local PHP Session
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// 3. Redirect to Login
header("Location: " . BASE_URL . "views/auth/login.php");
exit;
?>