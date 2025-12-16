<?php
require 'FlexibeeAPI.php';

$api = new FlexibeeAPI();

echo "=== Tworzenie produktu Atm00805 ===\n";
$result = $api->ensureProduct(
    'Atm00805',
    'Gazetnik Life czarny',
    'ks',
    'code:SKLAD',
    2025,
    '3560238913406',
    'Towar',
    31757
);

print_r($result);
