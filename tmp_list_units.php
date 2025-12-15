<?php
require 'app_config.php';

$config = AppConfig::get();
$apiUrl = rtrim($config['flexibee']['api_url'], '/');
$company = $config['flexibee']['company_id'];
$user = $config['flexibee']['username'];
$pass = $config['flexibee']['password'];

$url = $apiUrl . '/c/' . $company . '/merna-jednotka.json?detail=full';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);

$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code == 200) {
    $data = json_decode($resp, true);
    if (isset($data['winstrom']['merna-jednotka'])) {
        echo "=== Jednostki miary ===\n\n";
        foreach ($data['winstrom']['merna-jednotka'] as $item) {
            echo "ID: " . ($item['id'] ?? 'brak') . "\n";
            echo "Kod: " . ($item['kod'] ?? 'brak') . "\n";
            echo "Nazwa: " . ($item['nazev'] ?? 'brak') . "\n";
            echo "---\n";
        }
    }
} else {
    echo "HTTP $code\n$resp\n";
}
