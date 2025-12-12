<?php
require 'FlexibeeAPI.php';
$code = $argv[1] ?? '0000054252';
$api = new FlexibeeAPI();
$p = $api->getProductByCode($code);
var_dump($p);
