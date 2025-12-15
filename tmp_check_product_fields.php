<?php
require 'app_config.php';

$config = AppConfig::get();
$apiUrl = rtrim($config['flexibee']['api_url'], '/');
$company = $config['flexibee']['company_id'];
$user = $config['flexibee']['username'];
$pass = $config['flexibee']['password'];

// Pobierz pełne dane produktu ATMO00735
$filter = "(kod='ATMO00735')";
$url = $apiUrl . '/c/' . $company . '/cenik/' . rawurlencode($filter) . '.json?detail=full';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);

$resp = curl_exec($ch);
curl_close($ch);

$data = json_decode($resp, true);
if (isset($data['winstrom']['cenik'][0])) {
    $product = $data['winstrom']['cenik'][0];
    
    echo "=== Wszystkie pola produktu ATMO00735 ===\n\n";
    
    // Pokaż tylko pola związane ze 'skup' lub 'zboz'
    foreach ($product as $key => $value) {
        if (stripos($key, 'skup') !== false || stripos($key, 'zboz') !== false || stripos($key, 'typ') !== false) {
            echo "$key: ";
            if (is_array($value)) {
                echo json_encode($value, JSON_UNESCAPED_UNICODE);
            } else {
                echo $value;
            }
            echo "\n";
        }
    }
}
