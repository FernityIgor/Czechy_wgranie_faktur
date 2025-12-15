<?php
require 'app_config.php';

$config = AppConfig::get();
$apiUrl = rtrim($config['flexibee']['api_url'], '/');
$company = $config['flexibee']['company_id'];
$user = $config['flexibee']['username'];
$pass = $config['flexibee']['password'];

// Pobierz listę grup towarowych - próba różnych endpointów
$endpoints = [
    'skupina-zbozi',
    'skup-zboz',
    'skup-zbozi',
    'typZbozi'
];

foreach ($endpoints as $endpoint) {
    echo "\n=== Próba: $endpoint ===\n";
    $url = $apiUrl . '/c/' . $company . '/' . $endpoint . '.json?detail=full';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json','Accept: application/json']);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    echo "HTTP $code\n";
    if ($err) {
        echo "Error: $err\n";
    }

    if ($code == 200) {
        $data = json_decode($resp, true);
        // Szukaj w odpowiedzi
        foreach ($data['winstrom'] as $key => $value) {
            if (is_array($value) && $key !== '@version') {
                echo "\n=== Znaleziono: $key ===\n\n";
                $items = is_array($value) && isset($value[0]) ? $value : [$value];
                foreach ($items as $item) {
                    if (is_array($item)) {
                        echo "ID: " . ($item['id'] ?? 'brak') . "\n";
                        echo "Kod: " . ($item['kod'] ?? 'brak') . "\n";
                        echo "Nazwa: " . ($item['nazev'] ?? 'brak') . "\n";
                        echo "---\n";
                    }
                }
                break 2; // Znaleziono, wyjdź z obu pętli
            }
        }
    } else {
        echo "Response: " . substr($resp, 0, 200) . "...\n";
    }
}
