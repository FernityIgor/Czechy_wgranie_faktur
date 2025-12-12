<?php
require 'app_config.php';

$config = AppConfig::get();
$apiUrl = rtrim($config['flexibee']['api_url'], '/');
$company = $config['flexibee']['company_id'];
$user = $config['flexibee']['username'];
$pass = $config['flexibee']['password'];

$product = $argv[1] ?? 'ATMO00755';
$year = intval($argv[2] ?? date('Y'));

$payload = [
	'winstrom' => [
		'@version' => '1.0',
		'skladova-karta' => [
			[
				'cenik' => 'code:' . $product,
				'sklad' => 'code:SKLAD',
				'ucetObdobi' => 'code:' . $year
			]
		]
	]
];

$candidates = [
	'skladova-karta'
];

foreach ($candidates as $path) {
	$ch = curl_init();
	$url = $apiUrl . '/c/' . $company . '/' . $path . '.json';
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
	curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json','Accept: application/json']);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

	$resp = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$err = curl_error($ch);
	curl_close($ch);

	echo "=== TRY $path ===\n";
	echo "URL: $url\n";
	echo "HTTP $code\n";
	echo "Error: $err\n";
	echo "Response:\n$resp\n\n";
}
