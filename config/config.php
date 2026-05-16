<?php
// config/config.php
declare(strict_types=1);

/* ==============================================
   1. SESSION SETTINGS
   ============================================== */
// Configure session cookie parameters BEFORE starting session
if (session_status() === PHP_SESSION_NONE) {
    $h = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isLocal = in_array($h, ['localhost', '127.0.0.1', '::1']) || str_ends_with($h, '.test');

    // Set session cookie parameters to ensure cookies work across all pages and API endpoints
    session_set_cookie_params([
        'lifetime' => 0,           // Session cookie (expires when browser closes)
        'path' => '/ella-pos/',    // Make cookie available across entire application
        'domain' => '',            // Current domain
        'secure' => !$isLocal,      // HTTPS only on production (false for local XAMPP)
        'httponly' => true,        // Prevent JavaScript access
        'samesite' => 'Lax'        // CSRF protection while allowing navigation
    ]);
    session_start();
}

/* ==============================================
   2. ENVIRONMENT SETTINGS
   ============================================== */
// Set Timezone to Philippines (Crucial for Sales/Reports)
date_default_timezone_set('Asia/Manila');

// Error Reporting: Show everything during development
$h = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = in_array($h, ['localhost', '127.0.0.1', '::1']) || str_ends_with($h, '.test');

if ($isLocal) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}
error_reporting(E_ALL);

/* ==============================================
   3. PATH CONSTANTS
   ============================================== */
// Auto-detect the Root URL from the server's host
// This allows the app to work from localhost, IP address, or any hostname
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $protocol . '://' . $host . '/ella-pos/');

// Define Root Directory (Useful for file includes)
define('ROOT_PATH', dirname(__DIR__) . '/');

/* ==============================================
   4. MAINTENANCE MODE
   ============================================== */
/**
 * Checks if maintenance mode is active in the database.
 * Allows administrators to bypass maintenance mode.
 */
function isMaintenanceMode(): bool
{
    static $is_active = null;
    if ($is_active !== null)
        return $is_active;

    // 1. Bypass check: If user is logged in as admin, they can see the site
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return $is_active = false;
    }

    // 2. Database check
    try {
        require_once ROOT_PATH . 'config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        if (!$conn)
            return $is_active = false;

        $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
        $stmt->execute();
        $val = $stmt->fetchColumn();

        return $is_active = ($val === '1');
    } catch (Exception $e) {
        // Fallback to false if database is unreachable so we don't lock everyone out
        return $is_active = false;
    }
}

if (isMaintenanceMode()) {
    $current_page = $_SERVER['SCRIPT_NAME'];

    // Define exclusion patterns
    $is_maintenance_page = str_contains($current_page, 'maintenance.php');
    $is_asset = str_contains($current_page, '/assets/');
    $is_api = str_contains($current_page, '/api/');
    $is_login = str_contains($current_page, 'login.php'); // Allow login so admins can get in

    if (!$is_maintenance_page && !$is_asset && !$is_api && !$is_login) {
        header("Location: " . BASE_URL . "views/system/maintenance.php");
        exit;
    }
}
