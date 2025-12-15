<?php
require 'FlexibeeAPI.php';
require 'Database.php';

$db = Database::getInstance();
$api = new FlexibeeAPI();

// Sprawdź czy produkt Atmo00735 istnieje w bazie
$sql = "SELECT TOP 1 NAZWA, INDEKS_KATALOGOWY, KOD_KRESKOWY FROM ARTYKUL WHERE INDEKS_KATALOGOWY = 'Atmo00735'";
$row = $db->queryOne($sql);

if (!$row) {
    echo "Produkt Atmo00735 nie istnieje w bazie SQL\n";
    exit(1);
}

echo "=== Dane z bazy SQL ===\n";
echo "Kod: " . $row['INDEKS_KATALOGOWY'] . "\n";
echo "Nazwa: " . $row['NAZWA'] . "\n";
echo "EAN: " . ($row['KOD_KRESKOWY'] ?: 'brak') . "\n\n";

echo "=== Tworzenie produktu w Flexibee ===\n";
$result = $api->ensureProduct(
    $row['INDEKS_KATALOGOWY'],
    $row['NAZWA'],
    'ks',
    'code:SKLAD',
    2025,
    $row['KOD_KRESKOWY']
);

echo "Result: ";
if ($result === null) {
    echo "NULL - produkt nie został utworzony\n";
} else {
    print_r($result);
}
echo "\n";

// Sprawdź czy został utworzony
echo "=== Sprawdzenie czy produkt został utworzony ===\n";
$check = $api->getProductByCode($row['INDEKS_KATALOGOWY']);
if ($check) {
    echo "Produkt znaleziony:\n";
    echo "  kod: " . ($check['kod'] ?? 'brak') . "\n";
    echo "  nazev: " . ($check['nazev'] ?? 'brak') . "\n";
    echo "  nazevA: " . ($check['nazevA'] ?? 'brak') . "\n";
    echo "  mj1: " . ($check['mj1'] ?? 'brak') . "\n";
    echo "  skupZboz: " . ($check['skupZboz'] ?? 'brak') . "\n";
    echo "  eanKod: " . ($check['eanKod'] ?? 'brak') . "\n";
    echo "  skladovy: " . ($check['skladovy'] ?? 'brak') . "\n";
    echo "  sklad@showAs: " . ($check['sklad@showAs'] ?? 'brak') . "\n";
} else {
    echo "Produkt NIE został znaleziony\n";
}
