<?php
// api/system/download_recovery_guide.php
require_once '../../config/config.php';
require_once '../../includes/auth.php';

// Admin only
requireLogin();
if ($_SESSION['role'] !== 'admin' && !hasPermission('manage_settings')) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Access Denied';
    exit;
}

// Generate an HTML guide that the user can print
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Emergency Recovery Guide - ELLA POS</title>
    <!-- Include Bootstrap for clean styling -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }

        .guide-container {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .header-section {
            border-bottom: 2px solid #dc3545;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }

        .header-section h1 {
            color: #dc3545;
            font-weight: bold;
        }

        .step-card {
            background: #fdfdfe;
            border: 1px solid #e9ecef;
            border-left: 4px solid #0d6efd;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .step-card.warning-step {
            border-left-color: #ffc107;
        }

        .step-card.danger-step {
            border-left-color: #dc3545;
        }

        h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 600;
        }

        code.block {
            display: block;
            background: #212529;
            color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            font-family: 'Consolas', 'Courier New', monospace;
            word-break: break-all;
        }

        code.inline {
            background: #f1f3f5;
            color: #dc3545;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .contact-box {
            background: #e9ecef;
            padding: 20px;
            border-radius: 6px;
            margin-top: 40px;
        }

        .print-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        @media print {
            body {
                background: #fff;
            }

            .guide-container {
                margin: 0;
                padding: 0;
                box-shadow: none;
                max-width: 100%;
            }

            .print-btn {
                display: none !important;
            }
        }
    </style>
</head>

<body>

    <button class="btn btn-primary btn-lg print-btn " onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer me-2"
            viewBox="0 0 16 16">
            <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z" />
            <path
                d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z" />
        </svg>
        Print Guide as PDF
    </button>

    <div class="guide-container">
        <div class="header-section text-center">
            <h1>EMERGENCY RECOVERY GUIDE</h1>
            <p class="lead mb-0">Database Restoration & System Recovery Procedures</p>
            <small class="text-muted">Generated on: <?php echo date('Y-m-d H:i:s'); ?></small>
        </div>

        <div class="alert alert-danger" role="alert">
            <h4 class="alert-heading text-danger">⚠️ CAUTION!</h4>
            <p class="mb-0">Restoring a database will <strong>overwrite current live data</strong>. Do not proceed
                unless you are certain the database is corrupted, or you are recovering from a catastrophic failure.
                Always back up the current corrupted state before restoring an older backup, just in case.</p>
        </div>

        <h3 class="mt-4 mb-3">Method 1: Automated Shell Recovery (Recommended)</h3>
        <p>The easiest way to recover the database is using the automated recovery batch script located on the server.
        </p>

        <div class="step-card">
            <h4>Step 1: Open the Server Terminal</h4>
            <p>Go to the physical Windows server or connect via Remote Desktop.</p>
            <p>Press <code class="inline">Win + R</code>, type <code class="inline">cmd</code>, and hit Enter to open
                Command Prompt as Administrator.</p>

            <h4 class="mt-4">Step 2: Navigate to Scripts Directory</h4>
            <p>Run the following command:</p>
            <code class="block">cd C:\ella-pos-backups\scripts\</code>

            <h4 class="mt-4">Step 3: Run the Recovery Wizard</h4>
            <p>Execute the emergency recovery script:</p>
            <code class="block">emergency_recovery.bat</code>
            <p>Follow the on-screen interactive prompts. It will list available backups and allow you to select which
                one to restore.</p>
        </div>


        <h3 class="mt-5 mb-3">Method 2: Manual Recovery via phpMyAdmin</h3>
        <p>If the batch script fails or you don't have shell access, you can restore via phpMyAdmin.</p>

        <div class="step-card warning-step">
            <h4>Step 1: Download the Backup File</h4>
            <p>From the ELLA POS <strong>Backup & Recovery</strong> panel, download the latest <code
                    class="inline">.zip</code> backup file to your computer. Extract the contents to get the <code
                    class="inline">.sql</code> file.</p>

            <h4 class="mt-4">Step 2: Access phpMyAdmin</h4>
            <p>Open your browser and navigate to: <code class="inline">http://localhost/phpmyadmin/</code> (or your
                server's IP address).</p>

            <h4 class="mt-4">Step 3: Drop Existing Data</h4>
            <p>Select the <code class="inline">ella_parts_db</code> database on the left side.</p>
            <p>Scroll down, click <strong>Check All</strong> to select all tables, then select <strong>Drop</strong>
                from the "With selected:" dropdown. Confirm the action.</p>

            <h4 class="mt-4">Step 4: Import the Backup</h4>
            <p>Click the <strong>Import</strong> tab at the top. Click <strong>Choose File</strong> and select your
                extracted <code class="inline">.sql</code> file. Scroll down and click <strong>Import</strong>.</p>
        </div>


        <h3 class="mt-5 mb-3">Method 3: Manual Command Line Recovery</h3>

        <div class="step-card danger-step">
            <p>If the database is very large, phpMyAdmin might timeout. Use the MySQL command line instead.</p>

            <h4>Step 1: Extract Backup</h4>
            <p>Extract your downloaded backup so you have the <code class="inline">.sql</code> file (e.g., <code
                    class="inline">backup_20260228.sql</code>) loaded in <code class="inline">C:\</code>.</p>

            <h4 class="mt-4">Step 2: Run Import Command</h4>
            <p>Open Command Prompt and enter the following (adjust the username, password, and file path as needed):</p>
            <code class="block">C:\xampp\mysql\bin\mysql -u root -p ella_parts_db < C:\backup_20260228.sql</code>
            <p>Press Enter. You will be prompted for your MySQL root password. Wait for the import to finish (it will
                quietly return to the prompt when done).</p>
        </div>


        <div class="contact-box">
            <h4>Developer Support</h4>
            <p class="mb-1">If you are unable to restore the system, please contact your technical support or system
                administrator immediately.</p>
            <p class="mb-0"><strong>Important: Do not attempt to process new transactions while the database is
                    corrupted or partially restored.</strong></p>
        </div>
    </div>

</body>

</html>