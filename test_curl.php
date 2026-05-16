<?php
require 'classes/ShopeeApi.php';
$api = new ShopeeAPI('1231584', 'shpk6346504c645255495a43774e51764b635a4a51706b5367546e7844516261', true);
$url = $api->getAuthUrl('https://unwell-viewpoint-spotting.ngrok-free.dev');
echo "URL: $url\n";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
echo "RESPONSE:\n$response\n";
