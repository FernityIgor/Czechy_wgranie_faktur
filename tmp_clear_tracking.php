<?php
require 'Database.php';
$db = Database::getInstance();
$db->query("DELETE FROM Igor_faktury_wgrane_furnizone WHERE PLATNIK_NAZWA = ?", ['D2design s.r.o.']);
echo "cleared\n";
