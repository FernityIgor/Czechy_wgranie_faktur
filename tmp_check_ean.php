<?php
require 'FlexibeeAPI.php';

$api = new FlexibeeAPI();
$p = $api->getProductByCode('NOW004519');

echo "Kod: " . ($p['kod'] ?? 'brak') . "\n";
echo "eanKod: " . ($p['eanKod'] ?? 'brak') . "\n";
