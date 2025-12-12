<?php
require 'FlexibeeAPI.php';
$api = new FlexibeeAPI();
$code = $argv[1] ?? 'Test123';
$name = 'Test product ' . $code;
$unit = 'ks';
$data = [
	'winstrom' => [
		'@version' => '1.0',
		'cenik' => [
			'kod' => $code,
			'nazev' => $name,
			'mj' => $unit,
			'typCenik' => 'code:KATALOG'
		]
	]
];

echo "\n== getProduct ==\n";
var_dump($api->getProductByCode($code));

echo "\n== createProduct ==\n";
$result = $api->createProduct($data);
var_dump($result);
