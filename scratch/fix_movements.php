<?php
$file = 'c:/xampp/htdocs/ella-pos/views/inventory/movements.php';
$lines = file($file);
$newLines = [];
foreach ($lines as $line) {
    if (trim($line) === '?>') {
        continue;
    }
    $newLines[] = $line;
}
file_put_contents($file, implode('', $newLines));
echo "Fixed";
