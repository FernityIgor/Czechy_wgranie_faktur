<?php
require 'Database.php';

$db = Database::getInstance();

$sql = "
SELECT TOP 5
    NUMER_FAKTURY,
    STATUS,
    DATA_PRZETWORZENIA,
    PRODUKTY_INFO
FROM Igor_faktury_wgrane_furnizone
ORDER BY DATA_PRZETWORZENIA DESC
";

$results = $db->query($sql);

echo "=== Ostatnie 5 przetworzonych faktur ===\n\n";
foreach ($results as $row) {
    echo "Faktura: " . $row['NUMER_FAKTURY'] . "\n";
    echo "Status: " . $row['STATUS'] . "\n";
    echo "Data: " . ($row['DATA_PRZETWORZENIA'] ?? 'brak') . "\n";
    echo "Produkty:\n";
    if ($row['PRODUKTY_INFO']) {
        echo $row['PRODUKTY_INFO'] . "\n";
    } else {
        echo "  (brak nowych produktów)\n";
    }
    echo "\n" . str_repeat("-", 60) . "\n\n";
}
