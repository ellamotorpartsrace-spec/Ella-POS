<?php
session_start();
$_SESSION['role'] = 'admin';
$_SESSION['user_id'] = 1;
$_SERVER['HTTP_HOST'] = 'unwell-viewpoint-spotting.ngrok-free.dev';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
$_SERVER['REQUEST_URI'] = '/ella-pos/api/shopee/auth.php';
chdir('api/shopee');
require 'auth.php';
