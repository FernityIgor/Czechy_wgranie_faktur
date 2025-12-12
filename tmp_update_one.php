<?php
require 'FlexibeeAPI.php';
$api = new FlexibeeAPI();
$code = $argv[1] ?? 'ATMO02393';
$res = $api->updateProduct('code:' . $code, ['skladovy' => true, 'skladove' => true, 'sklad' => 'code:SKLAD']);
var_dump($res);
