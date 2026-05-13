<?php
// api/auth/login_process.php
declare(strict_types=1);

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/logger.php';

// 1. Check Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../views/auth/login.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$ip_address = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 2. CHECK RATE LIMITING
    $limit_stm = $conn->prepare("SELECT COUNT(*) as attempts FROM login_attempts WHERE ip_address = :ip AND attempted_at > (NOW() - INTERVAL 10 MINUTE)");
    $limit_stm->execute([':ip' => $ip_address]);
    $attempt_count = $limit_stm->fetch(PDO::FETCH_ASSOC)['attempts'];

    if ($attempt_count >= 3) {
        header("Location: ../../views/auth/login.php?error=locked_out");
        exit;
    }

    // 3. Find User
    $stmt = $conn->prepare("SELECT id, username, password, role, full_name, status FROM users WHERE username = :user LIMIT 1");
    $stmt->execute([':user' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. Logic: Check if User Exists and is Active
    if (!$user || ($user['status'] ?? 'active') !== 'active') {
        // Log failed attempt (legacy)
        $log_fail = $conn->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (:ip, :user)");
        $log_fail->execute([':ip' => $ip_address, ':user' => $username]);

        // Log to activity_logs
        $reason = !$user ? "User not found" : "Account deactivated";
        logActivity($conn, null, 'LOGIN_FAILED', 'Auth', "Failed login attempt ($reason: $username)");

        $error_type = !$user ? "user_not_found" : "account_deactivated";
        header("Location: ../../views/auth/login.php?error=" . $error_type);
        exit;
    }

    // 4. Logic: Verify Password (Supports Hash + Plain Text Fallback)
    $password_matched = false;
    $needs_rehash = false;

    if (password_verify($password, $user['password'])) {
        $password_matched = true;
        // Optionally check if the hash needs to be updated to a newer algorithm
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $needs_rehash = true;
        }
    } elseif ($password === $user['password']) {
        // Fallback for legacy plain text passwords
        $password_matched = true;
        $needs_rehash = true;
    } elseif (md5($password) === $user['password']) {
        // Fallback for legacy MD5 hashed passwords from older deployments
        $password_matched = true;
        $needs_rehash = true;
    }

    if ($password_matched) {
        // Auto-rehash if needed (Legacy migration or Hash algorithm update)
        if ($needs_rehash) {
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = :hash WHERE id = :id");
            $update_stmt->execute([':hash' => $new_hash, ':id' => $user['id']]);
        }

        // --- NEW SESSION LOGIC STARTS HERE ---

        // A. Start PHP Session
        if (session_status() === PHP_SESSION_NONE)
            session_start();

        // B. Security: Regenerate Session ID to prevent "Session Fixation" attacks
        session_regenerate_id(true);

        // C. Create a Unique Token for this specific login instance
        $session_token = bin2hex(random_bytes(32));

        // D. Insert into Database Audit Trail
        $logStmt = $conn->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, is_active) VALUES (:uid, :token, :ip, :agent, 1)");
        $logStmt->execute([
            ':uid' => $user['id'],
            ':token' => $session_token,
            ':ip' => $ip_address,
            ':agent' => $user_agent
        ]);

        // E. Store Data in Browser Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['session_token'] = $session_token; // We need this for logout later
        $_SESSION['ip_address'] = $ip_address; // Bind session to login IP for security

        // E2. Load Permissions
        $permissions = [];
        if ($user['role'] === 'admin') {
            // Admins get a special wildcard or bypass everything (handled in auth.php)
            $permissions[] = '*';
        } else {
            $permStmt = $conn->prepare("SELECT permission_slug FROM role_permissions WHERE role = ?");
            $permStmt->execute([$user['role']]);
            $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        $_SESSION['permissions'] = $permissions;

        // E3. Load User Preferences (device-specific overrides stored as JSON)
        $prefStmt = $conn->prepare("SELECT preferences FROM users WHERE id = ?");
        $prefStmt->execute([$user['id']]);
        $prefRow = $prefStmt->fetch();
        if ($prefRow && !empty($prefRow['preferences'])) {
            $_SESSION['preferences'] = json_decode($prefRow['preferences'], true) ?: [];
        } else {
            $_SESSION['preferences'] = [];
        }

        // F. Log Activity
        logActivity($conn, $user['id'], 'LOGIN_SUCCESS', 'Auth', "User logged in successfully");

        // --- NEW SESSION LOGIC ENDS HERE ---

        // Success Redirect
        header("Location: ../../views/dashboard/index.php");
        exit;
    } else {
        // Log failed attempt
        $log_fail = $conn->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (:ip, :user)");
        $log_fail->execute([':ip' => $ip_address, ':user' => $username]);

        // Log to activity_logs
        logActivity($conn, $user['id'], 'LOGIN_FAILED', 'Auth', "Failed login attempt (Incorrect password)");

        header("Location: ../../views/auth/login.php?error=wrong_password");
        exit;
    }
} catch (Exception $e) {
    // Log error internally, show generic error to user
    error_log("Login Error: " . $e->getMessage());
    header("Location: ../../views/auth/login.php?error=db_error");
    exit;
}
