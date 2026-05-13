<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Force login and validate session integrity.
 */
function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "views/auth/login.php");
        exit;
    }

    // IP-binding check to prevent session hijacking
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        header("Location: " . BASE_URL . "views/auth/login.php?error=ip_changed");
        exit;
    }

    updateSessionActivity();
}

/**
 * Update activity timestamp and cleanup stale sessions (1-hour inactivity limit).
 */
function updateSessionActivity(): void
{
    if (!isset($_SESSION['session_token'])) {
        return;
    }

    require_once dirname(__DIR__) . '/config/database.php';

    try {
        $db = new Database();
        $conn = $db->getConnection();

        // Check if session is still active in database
        $checkStmt = $conn->prepare("SELECT is_active FROM user_sessions WHERE session_token = :token LIMIT 1");
        $checkStmt->execute(['token' => $_SESSION['session_token']]);
        $sessionData = $checkStmt->fetch();

        if (!$sessionData || $sessionData['is_active'] == 0) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
            }
            session_destroy();
            header("Location: " . BASE_URL . "views/auth/login.php?error=session_expired");
            exit;
        }

        // Update activity timestamp
        $stmt = $conn->prepare("UPDATE user_sessions SET last_activity = CURRENT_TIMESTAMP WHERE session_token = :token AND is_active = 1");
        $stmt->execute(['token' => $_SESSION['session_token']]);

        // Cleanup sessions inactive for > 1 hour
        $cleanupStmt = $conn->prepare("UPDATE user_sessions SET is_active = 0 WHERE is_active = 1 AND last_activity < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $cleanupStmt->execute();

    } catch (Exception $e) {
        error_log("Session Activity Update Error: " . $e->getMessage());
    }
}

/**
 * Styled Access Denied page with automatic redirect.
 */
function denyAccess(string $message = "You do not have permission to view this page."): void
{
    http_response_code(403);
    $dashboardUrl = BASE_URL . "views/dashboard/index.php";

    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="refresh" content="5;url={$dashboardUrl}">
        <title>Access Denied</title>
        <style>
            body {
                font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
                background-color: #f8f9fa;
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
                margin: 0;
            }
            .denied-container {
                background: white;
                padding: 40px;
                border-radius: 12px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 500px;
                width: 90%;
            }
            .icon { font-size: 4rem; color: #dc3545; margin-bottom: 20px; }
            h2 { color: #212529; margin-bottom: 10px; }
            p { color: #6c757d; margin-bottom: 25px; line-height: 1.5; }
            .btn {
                display: inline-block;
                background: #0d6efd;
                color: white;
                text-decoration: none;
                padding: 10px 20px;
                border-radius: 6px;
                font-weight: 500;
                transition: background 0.2s;
            }
            .btn:hover { background: #0b5ed7; }
            .countdown { margin-top: 20px; font-size: 0.9rem; color: #adb5bd; }
        </style>
    </head>
    <body>
        <div class="denied-container">
            <div class="icon">🛑</div>
            <h2>Access Denied</h2>
            <p>{$message}</p>
            <a href="{$dashboardUrl}" class="btn">Return to Dashboard Now</a>
            <div class="countdown">Redirecting in <span id="timer">5</span> seconds...</div>
        </div>
        <script>
            let timeLeft = 5;
            const timerEl = document.getElementById('timer');
            setInterval(() => {
                timeLeft--;
                if (timeLeft > 0) timerEl.innerText = timeLeft;
            }, 1000);
        </script>
    </body>
    </html>
    HTML;
    exit;
}

function requireRole(array $allowed_roles): void
{
    requireLogin();
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        denyAccess("You do not have the required role to view this page.");
    }
}

function hasPermission(string $slug): bool
{
    if (!isset($_SESSION['user_id']))
        return false;
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')
        return true;

    if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        return in_array($slug, $_SESSION['permissions']) || in_array('*', $_SESSION['permissions']);
    }
    return false;
}

function requirePermission(string $slug): void
{
    requireLogin();
    if (!hasPermission($slug)) {
        denyAccess("You do not have permission to perform this action. (Requires: " . htmlspecialchars($slug) . ")");
    }
}
?>