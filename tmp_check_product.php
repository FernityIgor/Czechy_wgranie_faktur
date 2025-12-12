<?php
require 'FlexibeeAPI.php';
$api = new FlexibeeAPI();
$codes = ['Atmo03111','Sto000289','318858-660'];
foreach ($codes as $code) {
    echo "\n=== $code ===\n";
    $p = $api->getProductByCode($code);
    var_dump($p);
}
