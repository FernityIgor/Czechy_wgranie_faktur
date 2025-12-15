<?php
require 'FlexibeeAPI.php';

$api = new FlexibeeAPI();

echo "=== TEST 1: Produkt bez IAI_ID (powinien zostać z polską nazwą) ===\n";
$testKod1 = 'TEST_NO_IAI_' . rand(1000, 9999);
$result1 = $api->ensureProduct(
    $testKod1,
    'Polska nazwa bez IAI',
    'ks',
    'code:SKLAD',
    2025,
    null,
    'Towar',
    null  // Brak IAI_ID
);
print_r($result1);

sleep(1);

echo "\n=== Sprawdzenie produktu TEST 1 ===\n";
$check1 = $api->getProductByCode($testKod1);
if ($check1) {
    echo "nazev: " . ($check1['nazev'] ?? 'BRAK') . "\n";
    echo "nazevA: " . ($check1['nazevA'] ?? 'BRAK') . "\n";
    echo "kodPlu: " . ($check1['kodPlu'] ?? 'BRAK') . "\n";
}

echo "\n=== TEST 2: Produkt z nieistniejącym IAI_ID (powinien fallback do polskiej) ===\n";
$testKod2 = 'TEST_FAKE_IAI_' . rand(1000, 9999);
$result2 = $api->ensureProduct(
    $testKod2,
    'Polska nazwa z fake IAI',
    'ks',
    'code:SKLAD',
    2025,
    null,
    'Towar',
    999999999  // Nieistniejący IAI_ID
);
print_r($result2);

sleep(1);

echo "\n=== Sprawdzenie produktu TEST 2 ===\n";
$check2 = $api->getProductByCode($testKod2);
if ($check2) {
    echo "nazev: " . ($check2['nazev'] ?? 'BRAK') . "\n";
    echo "nazevA: " . ($check2['nazevA'] ?? 'BRAK') . "\n";
    echo "kodPlu: " . ($check2['kodPlu'] ?? 'BRAK') . "\n";
}

echo "\n=== TEST 3: Produkt z prawidłowym IAI_ID (powinien mieć czeską nazwę) ===\n";
$testKod3 = 'TEST_VALID_IAI_' . rand(1000, 9999);
$result3 = $api->ensureProduct(
    $testKod3,
    'Polska nazwa z valid IAI',
    'ks',
    'code:SKLAD',
    2025,
    null,
    'Towar',
    354  // Prawidłowy IAI_ID
);
print_r($result3);

sleep(1);

echo "\n=== Sprawdzenie produktu TEST 3 ===\n";
$check3 = $api->getProductByCode($testKod3);
if ($check3) {
    echo "nazev: " . ($check3['nazev'] ?? 'BRAK') . "\n";
    echo "nazevA: " . ($check3['nazevA'] ?? 'BRAK') . "\n";
    echo "kodPlu: " . ($check3['kodPlu'] ?? 'BRAK') . "\n";
}
