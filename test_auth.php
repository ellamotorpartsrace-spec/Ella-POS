<?php
require 'classes/ShopeeAPI.php';
$api = new ShopeeAPI('1231584', 'shpk6346504c645255495a43774e51764b635a4a51706b5367546e7844516261', true);
echo $api->getAuthUrl('https://unwell-viewpoint-spotting.ngrok-free.dev/api/shopee/callback.php');
