<?php
require 'FlexibeeAPI.php';
require 'Database.php';

$db = Database::getInstance();
$api = new FlexibeeAPI();

// Test dla towaru
echo "=== TEST 1: TOWAR ===\n";
$result1 = $api->ensureProduct(
    'TEST_TOWAR_001',
    'Test produkt - towar',
    'ks',
    'code:SKLAD',
    2025,
    '1234567890123',
    'Towar'
);
print_r($result1);

$check1 = $api->getProductByCode('TEST_TOWAR_001');
echo "skupZboz: " . ($check1['skupZboz@showAs'] ?? 'brak') . "\n";
echo "skladovy: " . ($check1['skladovy'] ?? 'brak') . "\n\n";

// Test dla usługi
echo "=== TEST 2: USŁUGA ===\n";
$result2 = $api->ensureProduct(
    'TEST_USLUGA_001',
    'Test produkt - usługa',
    'ks',
    null,  // Usługi bez magazynu
    2025,
    null,  // Usługi bez EAN
    'Usługa'
);
print_r($result2);

$check2 = $api->getProductByCode('TEST_USLUGA_001');
echo "skupZboz: " . ($check2['skupZboz@showAs'] ?? 'brak') . "\n";
echo "skladovy: " . ($check2['skladovy'] ?? 'brak') . "\n";
