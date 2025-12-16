<?php
require 'app_config.php';

$config = AppConfig::get();
$url = rtrim($config['flexibee']['api_url'], '/') . '/c/' . $config['flexibee']['company_id'] . '/cenik.json';

echo "URL Flexibee: $url\n";
echo "\nTestowanie cURL:\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $config['flexibee']['username'] . ':' . $config['flexibee']['password']);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($error) {
    echo "✗ Błąd cURL: $error\n";
} else {
    echo "✓ cURL działa, HTTP code: $httpCode\n";
}
