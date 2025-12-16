<?php
require 'FlexibeeAPI.php';

$api = new FlexibeeAPI();

// Sprawdź czy faktura istnieje w Flexibee
$invoiceCode = 'FUE/0218/12/25';
$result = $api->getInvoice($invoiceCode);

echo "Sprawdzanie faktury: $invoiceCode\n";
if (isset($result['winstrom']['faktura-prijata'])) {
    $invoice = $result['winstrom']['faktura-prijata'][0] ?? $result['winstrom']['faktura-prijata'];
    echo "✓ Faktura istnieje w Flexibee\n";
    echo "cisObj: " . ($invoice['cisObj'] ?? 'brak') . "\n";
} else {
    echo "✗ Faktura NIE istnieje w Flexibee\n";
    print_r($result);
}
