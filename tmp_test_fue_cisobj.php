<?php
require 'extract_invoice_data.php';

$invoiceNumber = 'FUE/0217/12/25';
$ord = getInvoiceOrderNumbers($invoiceNumber);
echo "Faktura: $invoiceNumber\n";
echo 'Zam WFMAG: ' . ($ord['zam_wfmag'] ?? 'brak') . "\n";
echo 'Zam IAI: ' . ($ord['zam_iai'] ?? 'brak') . "\n";
