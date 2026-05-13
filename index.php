<?php
// index.php
declare(strict_types=1);

// Load configuration (which starts the session)
require_once 'config/config.php';

/* ------------------------------------------
   ROUTING LOGIC
   ------------------------------------------ */

if (isset($_SESSION['user_id'])) {
    // 1. If Logged In -> Go to Dashboard
    // The dashboard will handle routing them to the POS or Inventory 
    // based on their role later.
    header("Location: " . BASE_URL . "views/dashboard/index.php");
    exit;

} else {
    // 2. If Guest -> Go to Login
    header("Location: " . BASE_URL . "views/auth/login.php");
    exit;
}
?>