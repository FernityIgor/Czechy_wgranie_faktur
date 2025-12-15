<?php
require 'FlexibeeAPI.php';
require 'Database.php';

$db = Database::getInstance();
$api = new FlexibeeAPI();

// Testowy produkt inny niż ATMO00735
$sql = "SELECT TOP 1 NAZWA, INDEKS_KATALOGOWY, KOD_KRESKOWY FROM ARTYKUL WHERE INDEKS_KATALOGOWY = 'NOW004519'";
$row = $db->queryOne($sql);

if (!$row) {
    echo "Produkt nie istnieje w bazie SQL\n";
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
echo "=== Sprawdzenie pola skupZboz ===\n";
$check = $api->getProductByCode($row['INDEKS_KATALOGOWY']);
if ($check) {
    echo "skupZboz: " . ($check['skupZboz'] ?? 'brak') . "\n";
    echo "skupZboz@showAs: " . ($check['skupZboz@showAs'] ?? 'brak') . "\n";
} else {
    echo "Produkt NIE został znaleziony\n";
}
