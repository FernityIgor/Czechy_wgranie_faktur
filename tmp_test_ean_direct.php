<?php
require 'FlexibeeAPI.php';
require 'Database.php';

$db = Database::getInstance();
$api = new FlexibeeAPI();

// Usuń poprzedni produkt testowy jeśli istnieje
$testKod = 'TESTEAN001';

$sql = "SELECT TOP 1 NAZWA, KOD_KRESKOWY FROM ARTYKUL WHERE INDEKS_KATALOGOWY = 'NOW004519'";
$row = $db->queryOne($sql);

echo "=== Test EAN dla nowego produktu ===\n";
echo "Kod z bazy: " . $row['KOD_KRESKOWY'] . "\n\n";

echo "=== Tworzenie produktu $testKod ===\n";
$result = $api->ensureProduct(
    $testKod,
    'Test produkt z EAN',
    'ks',
    'code:SKLAD',
    2025,
    $row['KOD_KRESKOWY']  // EAN z NOW004519
);

print_r($result);

echo "\n=== Sprawdzenie czy EAN się zapisał ===\n";
$check = $api->getProductByCode($testKod);
if ($check) {
    echo "eanKod: " . ($check['eanKod'] ?? 'BRAK') . "\n";
}
