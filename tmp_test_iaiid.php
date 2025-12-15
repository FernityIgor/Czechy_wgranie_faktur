<?php
require 'FlexibeeAPI.php';
require 'Database.php';

$db = Database::getInstance();
$api = new FlexibeeAPI();

// Sprawdź artykuł z IAI_ID
$sql = "
SELECT TOP 1 
    ar.INDEKS_KATALOGOWY,
    ar.NAZWA,
    ar.KOD_KRESKOWY,
    ar.RODZAJ,
    iai.iai_id
FROM ARTYKUL ar
LEFT JOIN MAGGEN_IAI iai ON ar.ID_ARTYKULU = iai.ID_NADRZEDNEGO
WHERE iai.iai_id IS NOT NULL
";

$row = $db->queryOne($sql);

if (!$row) {
    echo "Brak artykułów z IAI_ID\n";
    exit(1);
}

echo "=== Dane z bazy SQL ===\n";
echo "Kod: " . $row['INDEKS_KATALOGOWY'] . "\n";
echo "Nazwa: " . $row['NAZWA'] . "\n";
echo "IAI_ID: " . $row['iai_id'] . "\n";
echo "Rodzaj: " . $row['RODZAJ'] . "\n\n";

echo "=== Tworzenie produktu z IAI_ID ===\n";
$testKod = 'TEST_IAI_' . rand(1000, 9999);
$result = $api->ensureProduct(
    $testKod,
    $row['NAZWA'],
    'ks',
    'code:SKLAD',
    2025,
    $row['KOD_KRESKOWY'],
    $row['RODZAJ'],
    $row['iai_id']
);

print_r($result);

echo "\n=== Sprawdzenie pola kodPlu ===\n";
$check = $api->getProductByCode($testKod);
if ($check) {
    echo "kodPlu: " . ($check['kodPlu'] ?? 'BRAK') . "\n";
    echo "eanKod: " . ($check['eanKod'] ?? 'BRAK') . "\n";
    echo "skupZboz: " . ($check['skupZboz@showAs'] ?? 'brak') . "\n";
}
