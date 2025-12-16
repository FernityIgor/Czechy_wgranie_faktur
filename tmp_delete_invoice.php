<?php
require 'Database.php';

$db = Database::getInstance();
$invoiceNumber = 'FUE/0218/12/25';

$sql = "DELETE FROM Igor_faktury_wgrane_furnizone WHERE NUMER_FAKTURY = ?";
$db->query($sql, [$invoiceNumber]);

echo "Usunięto wpis dla faktury: $invoiceNumber\n";
