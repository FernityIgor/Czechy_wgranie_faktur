<?php
require 'extract_invoice_data.php';
$num = $argv[1] ?? 'FUE/0001/12/25';
$inv = getInvoiceData($num);
if (!$inv) { echo "no invoice"; exit; }
$d = convertToFlexibeeFormat($inv);
echo json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
