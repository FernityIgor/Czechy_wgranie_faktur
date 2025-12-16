<?php
require 'extract_invoice_data.php';

$inv = getInvoiceData('FUE/0218/12/25');
echo "Pozycje faktury FUE/0218/12/25:\n";
print_r($inv['pozycje']);
echo "\ncisObj: " . ($inv['zam_wfmag'] ? 'Zam WFMAG: ' . $inv['zam_wfmag'] : '') . ($inv['zam_iai'] ? ' | Zam IAI: ' . $inv['zam_iai'] : '') . "\n";
