<?php
require 'FlexibeeAPI.php';

$api = new FlexibeeAPI();
$kod = 'Atm00805';

echo "Sprawdzanie produktu: $kod\n";
$product = $api->getProductByCode($kod);

if ($product) {
    echo "✓ Produkt istnieje\n";
    echo "Nazwa: " . ($product['nazev'] ?? 'brak') . "\n";
} else {
    echo "✗ Produkt NIE istnieje - zostanie utworzony\n";
}
