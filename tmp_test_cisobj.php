<?php
require 'extract_invoice_data.php';

// Test z fakturą F/4986/12/25 (z przykładu SQL)
$invoiceNumber = 'F/4986/12/25';

echo "=== Test pobierania numerów zamówień ===\n";
$orderNumbers = getInvoiceOrderNumbers($invoiceNumber);
echo "Faktura: $invoiceNumber\n";
echo "Zam WFMAG: " . ($orderNumbers['zam_wfmag'] ?? 'brak') . "\n";
echo "Zam IAI: " . ($orderNumbers['zam_iai'] ?? 'brak') . "\n\n";

echo "=== Test pełnych danych faktury ===\n";
$invoiceData = getInvoiceData($invoiceNumber);
if ($invoiceData) {
    echo "Numer: " . $invoiceData['numer'] . "\n";
    echo "Zam WFMAG: " . ($invoiceData['zam_wfmag'] ?? 'brak') . "\n";
    echo "Zam IAI: " . ($invoiceData['zam_iai'] ?? 'brak') . "\n\n";
    
    echo "=== Test konwersji do Flexibee ===\n";
    $flexibeeData = convertToFlexibeeFormat($invoiceData);
    echo "cisObj: " . ($flexibeeData['winstrom']['faktura-prijata']['cisObj'] ?? 'brak') . "\n";
} else {
    echo "Nie znaleziono faktury\n";
}
