<?php
require 'app_config.php';

$config = AppConfig::get();
$apiUrl = rtrim($config['flexibee']['api_url'], '/');
$company = $config['flexibee']['company_id'];
$user = $config['flexibee']['username'];
$pass = $config['flexibee']['password'];

$ch = curl_init();
$url = $apiUrl . '/c/' . $company . '/sklad.json?detail=full';
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP $code\n";
echo "Error: $err\n";
$data = json_decode($resp, true);
if (!empty($data['winstrom']['sklad'])) {
	foreach ($data['winstrom']['sklad'] as $s) {
		echo ($s['kod'] ?? 'brak') . " | " . ($s['nazev'] ?? '') . "\n";
	}
} else {
	print_r($resp);
}
