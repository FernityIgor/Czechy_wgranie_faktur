<?php
require 'Database.php';
$db = Database::getInstance();
$rows = $db->query("SELECT STATUS, COUNT(*) cnt FROM Igor_faktury_wgrane_furnizone GROUP BY STATUS");
print_r($rows);
