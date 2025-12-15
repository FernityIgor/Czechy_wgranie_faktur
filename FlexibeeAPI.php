<?php
/**
 * Flexibee API Client
 * Dokumentacja: https://podpora.flexibee.eu/cs/collections/2592813-dokumentace-rest-api
 */

require_once __DIR__ . '/app_config.php';

class FlexibeeAPI
{
    private $apiUrl;
    private $company;
    private $username;
    private $password;
    private $dryRun;
    private $defaultWarehouse;

    public function __construct()
    {
        $config = AppConfig::get();
        
        $this->apiUrl = rtrim($config['flexibee']['api_url'], '/');
        $this->company = $config['flexibee']['company_id'];
        $this->username = $config['flexibee']['username'];
        $this->password = $config['flexibee']['password'];
        $this->dryRun = $config['options']['dry_run'];
        $this->defaultWarehouse = $config['mapping']['default_warehouse'] ?? null;
    }

    /**
     * Test połączenia z API Flexibee
     * @return array Odpowiedź z API
     */
    public function testConnection()
    {
        $url = $this->apiUrl . '/c/' . $this->company . '.json';
        
        try {
            $response = $this->makeRequest('GET', $url);
            return [
                'success' => true,
                'message' => 'Połączenie z Flexibee OK',
                'response' => $response
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Błąd połączenia: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Tworzy fakturę wydaną w Flexibee
     * @param array $invoiceData Dane faktury w formacie Flexibee
     * @return array Odpowiedź z API
     */
    public function createInvoice($invoiceData)
    {
        if ($this->dryRun) {
            return [
                'success' => true,
                'dry_run' => true,
                'message' => 'DRY RUN - Faktura nie została wysłana',
                'data' => $invoiceData
            ];
        }

        // Wysyłamy fakturę PRZYJĘTĄ (zakupową), nie wydaną
        $url = $this->apiUrl . '/c/' . $this->company . '/faktura-prijata.json';
        
        try {
            $response = $this->makeRequest('POST', $url, $invoiceData);
            
            // Sprawdź czy odpowiedź zawiera sukces
            if (isset($response['winstrom']['success'])) {
                return [
                    'success' => $response['winstrom']['success'] === 'true',
                    'message' => 'Faktura utworzona pomyślnie',
                    'response' => $response
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Nieznana odpowiedź z API',
                'response' => $response
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Błąd wysyłania faktury: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Wykonuje request HTTP do API Flexibee
     * @param string $method Metoda HTTP (GET, POST, PUT, DELETE)
     * @param string $url Pełny URL endpointu
     * @param array|null $data Dane do wysłania (dla POST/PUT)
     * @return array Zdekodowana odpowiedź JSON
     * @throws Exception W przypadku błędu HTTP
     */
    private function makeRequest($method, $url, $data = null)
    {
        $ch = curl_init();

        // Podstawowa konfiguracja
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        // HTTP Basic Authentication
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);

        // Headers
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Metoda i dane
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        // Wykonaj request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Sprawdź błędy cURL
        if ($error) {
            throw new Exception("cURL error: $error");
        }

        // Sprawdź kod HTTP
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("HTTP error $httpCode: $response");
        }

        // Dekoduj JSON
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON decode error: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Pobiera listę faktur z Flexibee
     * @param array $filters Filtry zapytania
     * @return array Lista faktur
     */
    public function getInvoices($filters = [])
    {
        $url = $this->apiUrl . '/c/' . $this->company . '/faktura-prijata.json';
        
        if (!empty($filters)) {
            $url .= '?' . http_build_query($filters);
        }
        
        try {
            return $this->makeRequest('GET', $url);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Błąd pobierania faktur: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Pobiera szczegóły pojedynczej faktury
     * @param string $kod Kod/numer faktury
     * @return array Dane faktury
     */
    public function getInvoice($kod)
    {
        $url = $this->apiUrl . '/c/' . $this->company . '/faktura-prijata/' . urlencode($kod) . '.json';
        
        try {
            return $this->makeRequest('GET', $url);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Błąd pobierania faktury: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Pobiera listę kontrahentów (adresar)
     * @param array $filters Filtry zapytania
     * @return array Lista kontrahentów
     */
    public function getContacts($filters = [])
    {
        $url = $this->apiUrl . '/c/' . $this->company . '/adresar.json';
        
        if (!empty($filters)) {
            $url .= '?' . http_build_query($filters);
        }
        
        try {
            return $this->makeRequest('GET', $url);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Błąd pobierania kontrahentów: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Pobiera produkty z cenika (paginacja)
     * @param int $start Offset
     * @param int $limit Limit
     * @param string $detail Parametr detail, np. 'custom:kod,skladovy,sklad@ref'
     * @return array
     */
    public function getProducts($start = 0, $limit = 200, $detail = 'full')
    {
        $detailParam = rawurlencode($detail);
        $url = $this->apiUrl . '/c/' . $this->company . '/cenik.json?start=' . intval($start) . '&limit=' . intval($limit) . '&detail=' . $detailParam;
        try {
            $resp = $this->makeRequest('GET', $url);
            return $resp['winstrom']['cenik'] ?? [];
        } catch (Exception $e) {
            echo "getProducts error: " . $e->getMessage() . "\n";
            return [];
        }
    }

    /**
     * Tworzy nowego kontrahenta w adresarze
     * @param array $contactData Dane kontrahenta
     * @return array Odpowiedź z API
     */
    public function createContact($contactData)
    {
        $url = $this->apiUrl . '/c/' . $this->company . '/adresar.json';
        
        try {
            $response = $this->makeRequest('POST', $url, $contactData);
            
            if (isset($response['winstrom']['success'])) {
                return [
                    'success' => $response['winstrom']['success'] === 'true',
                    'message' => 'Kontrahent utworzony pomyślnie',
                    'response' => $response
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Nieznana odpowiedź z API',
                'response' => $response
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Błąd tworzenia kontrahenta: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Sprawdza czy produkt istnieje w ceniku po kodzie
     * @param string $kod Kod produktu (INDEKS_HANDLOWY)
     * @return array|null Dane produktu lub null jeśli nie istnieje
     */
    public function getProductByCode($kod)
    {
        // Użyj filtra (kod) aby znaleźć produkt
        $filter = "(kod='" . str_replace("'", "\\'", $kod) . "')";
        $url = $this->apiUrl . '/c/' . $this->company . '/cenik/' . rawurlencode($filter) . '.json?detail=full';
        
        try {
            $response = $this->makeRequest('GET', $url);
            
            if (isset($response['winstrom']['cenik']) && !empty($response['winstrom']['cenik'])) {
                return $response['winstrom']['cenik'][0];
            }
            
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Tworzy nowy produkt w ceniku
     * @param array $productData Dane produktu
     * @return array Odpowiedź z API
     */
    public function createProduct($productData)
    {
        if ($this->dryRun) {
            return [
                'success' => true,
                'dry_run' => true,
                'message' => 'DRY RUN - Produkt nie został utworzony',
                'data' => $productData
            ];
        }

        $url = $this->apiUrl . '/c/' . $this->company . '/cenik.json';
        
        try {
            $response = $this->makeRequest('POST', $url, $productData);
            
            if (isset($response['winstrom']['success'])) {
                return [
                    'success' => $response['winstrom']['success'] === 'true',
                    'message' => 'Produkt utworzony pomyślnie',
                    'response' => $response
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Nieznana odpowiedź z API',
                'response' => $response
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Błąd tworzenia produktu: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Aktualizuje produkt w ceniku
     * @param string $idOrCode Identyfikator (np. "code:XYZ" lub numeric ID)
     * @param array $fields Pola do nadpisania
     * @return bool
     */
    public function updateProduct($idOrCode, $fields)
    {
        $url = $this->apiUrl . '/c/' . $this->company . '/cenik.json';

        $payload = [
            'winstrom' => [
                '@version' => '1.0',
                'cenik' => [
                    array_merge(['id' => $idOrCode], $fields)
                ]
            ]
        ];

        try {
            $response = $this->makeRequest('POST', $url, $payload);
            if (isset($response['winstrom']['success'])) {
                return $response['winstrom']['success'] === 'true' ? $response : false;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Pobiera czeską nazwę produktu z API dkwadrat.pl
     * @param string $iaiId IAI ID produktu
     * @return string|null Nazwa produktu w języku czeskim lub null
     */
    private function fetchCzechProductName($iaiId)
    {
        if (!$iaiId) {
            return null;
        }

        $url = "https://dkwadrat.pl/api/admin/v7/products/descriptions?type=id&ids=" . urlencode($iaiId) . "&shopId=4";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-API-KEY: YXBwbGljYXRpb24xOktvTnUyTkwrV0NEbUwvMzdhMmJFN3BFSzVTTkVEM2ZjRm9xbzQ5NDREKzd1SXRsNGlPQnFkL0pBb2NMZGZsR3c=",
            "accept: application/json"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Błąd cURL lub nieudany HTTP request
        if ($error || $httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);
        
        // Błąd JSON lub brak danych
        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        // Brak wyników (produkt nie istnieje w IAI)
        if (empty($data['results']) || !is_array($data['results'])) {
            return null;
        }
        
        // Szukaj czeskiej nazwy w productDescriptionsLangData
        if (isset($data['results'][0]['productDescriptionsLangData']) && is_array($data['results'][0]['productDescriptionsLangData'])) {
            foreach ($data['results'][0]['productDescriptionsLangData'] as $langData) {
                if (isset($langData['langId']) && $langData['langId'] === 'cze' && !empty($langData['productName'])) {
                    return trim($langData['productName']);
                }
            }
        }

        // Nie znaleziono czeskiej nazwy
        return null;
    }

    /**
     * Tworzy kartę magazynową dla produktu w wybranym magazynie i roku (jeśli brak)
     * @param string $productCode Kod produktu (bez prefixu code:)
     * @param string|null $warehouseCode Kod magazynu (code:XXXX). Jeśli null, użyje domyślnego
     * @param int|null $year Rok księgowy; domyślnie bieżący
     * @return bool True gdy sukces lub gdy już istnieje; false przy błędzie
     */
    public function ensureStockCard($productCode, $warehouseCode = null, $year = null)
    {
        if (!$warehouseCode) {
            $warehouseCode = $this->defaultWarehouse;
        }

        if (!$warehouseCode) {
            return false;
        }

        if (!$year) {
            $year = intval(date('Y'));
        }

        // Flexibee utworzy kartę magazynową dla wskazanego roku
        $url = $this->apiUrl . '/c/' . $this->company . '/skladova-karta.json';

        $codeOnly = (strpos($productCode, 'code:') === 0) ? substr($productCode, 5) : $productCode;

        $payload = [
            'winstrom' => [
                '@version' => '1.0',
                'skladova-karta' => [
                    [
                        'cenik' => 'code:' . $codeOnly,
                        'sklad' => $warehouseCode,
                        'ucetObdobi' => 'code:' . $year
                    ]
                ]
            ]
        ];

        try {
            $response = $this->makeRequest('POST', $url, $payload);

            // API zwraca success=true lub błąd duplikatu gdy już istnieje (traktujemy jako OK)
            if (isset($response['winstrom']['success']) && $response['winstrom']['success'] === 'true') {
                return true;
            }

            // Jeśli zwrócił się błąd, ale to duplikat istniejącej karty, uznaj za sukces
            if (!empty($response['winstrom']['results'][0]['errors'])) {
                foreach ($response['winstrom']['results'][0]['errors'] as $err) {
                    if (isset($err['messageCode']) && stripos($err['messageCode'], 'importDuplicate') !== false) {
                        return true;
                    }
                }
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Sprawdza/tworzy produkt jeśli nie istnieje; ustawia magazynowość i magazyn gdy trzeba
     * @param string $kod Kod produktu
     * @param string $nazwa Nazwa produktu
     * @param string $jednostka Jednostka miary
     * @param string|null $skladCode Kod magazynu (code:...)
     * @param int|null $year Rok księgowy do utworzenia karty magazynowej (domyślnie bieżący)
     * @param string|null $ean Kod EAN produktu
     * @param string|null $rodzaj Rodzaj artykułu (Towar/Produkt/Usługa)
     * @param string|null $iaiId IAI ID produktu (zapisywane do kodPlu)
     * @return array|null ['code' => kod, 'skladovy' => bool]
     */
    public function ensureProduct($kod, $nazwa, $jednostka = 'ks', $skladCode = null, $year = null, $ean = null, $rodzaj = 'Towar', $iaiId = null)
    {
        if (!$year) {
            $year = intval(date('Y'));
        }
        // Sprawdź czy produkt istnieje
        $existing = $this->getProductByCode($kod);
        
        if ($existing) {
            // Jeśli nie jest magazynowy lub nie ma przypisanego magazynu, spróbuj uzupełnić
            $needsUpdate = false;
            $updateFields = [];

            $isStock = (!empty($existing['skladovy']) && $existing['skladovy'] === 'true') || (!empty($existing['skladove']) && $existing['skladove'] === 'true');

            if (!$isStock) {
                $updateFields['skladovy'] = true;
                $updateFields['skladove'] = true;
                $needsUpdate = true;
            }

            if (!$skladCode && $this->defaultWarehouse) {
                $skladCode = $this->defaultWarehouse;
            }

            // przypisz magazyn jeśli brak, a mamy domyślny
            if ($skladCode && empty($existing['sklad@ref'])) {
                $updateFields['sklad'] = $skladCode;
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $this->updateProduct('code:' . $kod, $updateFields);
                // po aktualizacji pobierz ponownie, aby wiedzieć czy jest magazynowy
                $existing = $this->getProductByCode($kod) ?: $existing;
            }

            // Upewnij się, że istnieje karta magazynowa w domyślnym magazynie
            if ($skladCode || $this->defaultWarehouse) {
                $this->ensureStockCard($kod, $skladCode ?: $this->defaultWarehouse, $year);
            }

            $isStock = (!empty($existing['skladovy']) && $existing['skladovy'] === 'true') || (!empty($existing['skladove']) && $existing['skladove'] === 'true');
            return ['code' => $kod, 'skladovy' => $isStock, 'new' => false];
        }

        // Określ grupę na podstawie rodzaju artykułu
        $isService = (stripos($rodzaj, 'usługa') !== false || stripos($rodzaj, 'usluga') !== false);
        $skupZbozCode = $isService ? 'code:SLUŽBY' : 'code:ZBOŽÍ';
        
        // Produkt nie istnieje - utwórz go
        $productData = [
            'winstrom' => [
                '@version' => '1.0',
                'cenik' => [
                    'kod' => $kod,
                    'nazev' => $nazwa,
                    'nazevA' => $nazwa,
                    'mj1' => 'code:KS',
                    'typCenik' => 'code:KATALOG', // Typ: katalog (skladová karta)
                    'skupinaZbozi' => $skupZbozCode,
                    'skupZboz' => $skupZbozCode,
                    'skladovy' => !$isService,  // Usługi nie są magazynowe
                    'skladove' => !$isService
                ]
            ]
        ];

        if ($ean) {
            $productData['winstrom']['cenik']['eanKod'] = $ean;
        }
        
        if ($iaiId) {
            $productData['winstrom']['cenik']['kodPlu'] = $iaiId;
        }

        if (!$skladCode && $this->defaultWarehouse) {
            $skladCode = $this->defaultWarehouse;
        }

        if ($skladCode) {
            $productData['winstrom']['cenik']['sklad'] = $skladCode;
        }

        $result = $this->createProduct($productData);
        
        if ($result['success']) {
            // Utwórz kartę magazynową dla nowej pozycji
            if ($skladCode || $this->defaultWarehouse) {
                $this->ensureStockCard($kod, $skladCode ?: $this->defaultWarehouse, $year);
            }

            // Pobierz czeską nazwę z API i zaktualizuj produkt
            $czechNameStatus = 'NO_IAI';
            if ($iaiId) {
                $czechName = $this->fetchCzechProductName($iaiId);
                if ($czechName) {
                    echo "Aktualizacja nazwy produktu $kod na czeską: $czechName\n";
                    $this->updateProduct('code:' . $kod, ['nazev' => $czechName, 'nazevA' => $czechName]);
                    $czechNameStatus = 'OK';
                } else {
                    echo "Brak czeskiej nazwy dla produktu $kod (IAI_ID: $iaiId) - pozostawiono polską nazwę\n";
                    $czechNameStatus = 'BRAK';
                }
            }

            return ['code' => $kod, 'skladovy' => true, 'new' => true, 'iai_id' => $iaiId, 'czech_name' => $czechNameStatus];
        }
        
        // Loguj błąd tworzenia produktu
        if (isset($result['message'])) {
            echo "BŁĄD ensureProduct dla $kod: " . $result['message'] . "\n";
            if (isset($result['response'])) {
                echo "Response: " . json_encode($result['response']) . "\n";
            }
        }

        return null;
    }
}
