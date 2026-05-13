<?php
// views/system/maintenance.php
require_once '../../config/config.php';

// --- SECRET REMOTE TRIGGER ---
// Use this to toggle maintenance mode if you are locked out of the admin panel
// Example: maintenance.php?secret=EllaAdmin2026&state=off
$secret_key = 'EllaAdmin2026'; // You can change this to any secure string
if (isset($_GET['secret']) && $_GET['secret'] === $secret_key && isset($_GET['state'])) {
    try {
        require_once '../../config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        $new_state = ($_GET['state'] === 'on') ? '1' : '0';

        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('maintenance_mode', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$new_state, $new_state]);

        $msg = "Maintenance mode turned " . ($_GET['state'] === 'on' ? "ON" : "OFF");
        echo "<script>alert('$msg'); window.location.href='maintenance.php';</script>";
        exit;
    } catch (Exception $e) {
        die("Trigger failed: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance | Ella POS</title>

    <!-- Meta Tags -->
    <meta name="description" content="Ella POS is currently undergoing scheduled maintenance. We'll be back shortly.">
    <meta name="theme-color" content="#3B82F6">

    <!-- Styles -->
    <link rel="stylesheet" href="../../assets/css/bootstrap-5.3.8-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">

    <!-- Project Styles -->
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="../../assets/css/login.css">
    <link rel="stylesheet" href="../../assets/css/maintenance.css">
</head>

<body class="bg-body">
    <!-- Animated Mesh Background -->
    <div class="mesh-background">
        <div class="mesh-ball mesh-ball-1"></div>
        <div class="mesh-ball mesh-ball-2"></div>
        <div class="mesh-ball mesh-ball-3"></div>
    </div>

    <button class="theme-toggle-btn" id="themeToggle" title="Toggle Theme">
        <i class="fa-solid fa-moon"></i>
    </button>

    <div class="maintenance-container">
        <div class="glass-card">
            <!-- Status Badge -->
            <div class="status-badge">
                <div class="status-dot"></div>
                System Maintenance
            </div>

            <div class="icon-wrapper" id="gearIcon">
                <i class="fa-solid fa-gears"></i>
            </div>

            <h1>Under Maintenance</h1>
            <p>
                We're currently performing scheduled updates to improve your experience with <strong>Enterprise
                    ERP</strong>.
                Our team is working hard to bring you even more power and reliability.
            </p>

            <div class="progress-loader"></div>

            <div class="d-flex flex-column flex-sm-row justify-content-center gap-3 mt-4">
                <a href="mailto:support@ellamotorparts.com" class="btn btn-outline-primary rounded-pill px-4 py-2">
                    <i class="fa-solid fa-envelope me-2"></i>Contact Support
                </a>
                <a href="../auth/login.php" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm">
                    <i class="fa-solid fa-right-to-bracket me-2"></i>Staff Login
                </a>
            </div>
        </div>
    </div>

    <div class="brand-footer">
        &copy; <?php echo date('Y'); ?> Ella Motor Parts &bull; Enterprise ERP Ecosystem
    </div>

    <!-- Scripts -->
    <script>
        // Simple Theme Toggle matching existing POS pattern
        const body = document.body;
        const html = document.documentElement;
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = themeToggle.querySelector('i');

        // Check for saved theme
        const savedTheme = localStorage.getItem('ella-theme') || 'dark';
        html.setAttribute('data-theme', savedTheme);
        updateIcon(savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('ella-theme', newTheme);
            updateIcon(newTheme);
        });

        function updateIcon(theme) {
            themeIcon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        }

        // Add random slight gear speed variations on click just for fun
        const gear = document.querySelector('#gearIcon i');
        document.getElementById('gearIcon').addEventListener('click', () => {
            const currentSpeed = getComputedStyle(gear).animationDuration;
            const newSpeed = currentSpeed === '8s' ? '2s' : '8s';
            gear.style.animationDuration = newSpeed;

            setTimeout(() => {
                gear.style.animationDuration = '8s';
            }, 2000);
        });
    </script>
</body>

</html>