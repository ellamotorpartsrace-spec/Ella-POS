<?php
// includes/header.php

// 1. Load Config if missing
if (!defined('BASE_URL')) {
    $config_path = __DIR__ . '/../config/config.php';
    if (file_exists($config_path))
        require_once $config_path;
}

// Load settings helper for JS injection
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/settings_helper.php';
try {
    $_hdb = new Database();
    $_hconn = $_hdb->getConnection();
    $_jsSettings = getSettingsForJS($_hconn);
} catch (Exception $e) {
    $_jsSettings = [];
}

// 2. Start Session
if (session_status() === PHP_SESSION_NONE)
    session_start();

// 3. Page Title
$current_title = $page_title ?? 'Ella Motor Parts';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($current_title) ?></title>

    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">

    <link href="<?= BASE_URL ?>assets/css/bootstrap-5.3.8-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"
        media="print" onload="this.media='all'">
    <noscript>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    </noscript>

    <link href="<?= BASE_URL ?>assets/css/styles.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/ella-toast.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/ella-hotkeys.css" rel="stylesheet">


    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/img/logo.png">

    <script>
        // Set Global Base URL for API calls
        window.BASE_URL = '<?= BASE_URL ?>';

        // Immediately apply theme to avoid flash
        (function () {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            if (savedTheme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <script>
        window.STORE_SETTINGS = <?= json_encode($_jsSettings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
</head>

<body class="preload">

    <div class="d-flex" id="wrapper">
        <script>
            // Immediately restore sidebar state before render to prevent flash
            const savedState = localStorage.getItem('sidebarState');
            if (window.innerWidth >= 992) {
                if (savedState === 'closed') {
                    document.getElementById('wrapper').classList.add('toggled');
                }
            }
        </script>