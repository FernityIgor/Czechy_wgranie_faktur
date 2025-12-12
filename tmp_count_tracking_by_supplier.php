<?php
require 'Database.php';
$db = Database::getInstance();
$rows = $db->query("SELECT PLATNIK_NAZWA, STATUS, COUNT(*) cnt, MAX(DATA_WYSTAWIENIA) last_date FROM Igor_faktury_wgrane_furnizone GROUP BY PLATNIK_NAZWA, STATUS ORDER BY PLATNIK_NAZWA, STATUS");
print_r($rows);
