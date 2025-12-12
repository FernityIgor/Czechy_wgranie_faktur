<?php
/**
 * Invoice Creator - Główny skrypt do przetwarzania faktur
 * Pobiera faktury z SQL Server i wysyła do Flexibee
 */

require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/FlexibeeAPI.php';
require_once __DIR__ . '/extract_invoice_data.php';

class InvoiceCreator
{
    private $db;
    private $flexibee;
    private $config;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->flexibee = new FlexibeeAPI();
        $this->config = AppConfig::get();
    }

    /**
     * Przetwarza pojedynczą fakturę - pobiera z bazy i wysyła do Flexibee
     * @param string $invoiceNumber Numer faktury (np. 'FUE/0020/12/25')
     * @param bool $skipIfProcessed Czy pominąć jeśli już przetworzono
     * @return array Wynik operacji
     */
    public function processInvoice($invoiceNumber, $skipIfProcessed = true)
    {
        echo "=== Przetwarzanie faktury: $invoiceNumber ===\n";

        // Sprawdź czy już przetworzono
        if ($skipIfProcessed && isInvoiceProcessed($invoiceNumber)) {
            echo "⊘ Faktura już przetworzono wcześniej - pomijam\n\n";
            return [
                'success' => true,
                'skipped' => true,
                'message' => "Faktura już przetworzono"
            ];
        }

        // 1. Pobierz dane z bazy
        echo "Pobieranie danych z bazy...\n";
        $invoiceData = getInvoiceData($invoiceNumber);

        if (!$invoiceData) {
            markInvoiceAsProcessed($invoiceNumber, 'ERROR', null, 'Nie znaleziono w bazie danych');
            return [
                'success' => false,
                'message' => "Nie znaleziono faktury $invoiceNumber w bazie danych"
            ];
        }

        echo "✓ Pobrano fakturę: {$invoiceData['numer']}\n";
        echo "  Kontrahent: {$invoiceData['kontrahent']}\n";
        echo "  Data wystawienia: {$invoiceData['data_wystawienia']}\n";
        echo "  Suma brutto: {$invoiceData['suma_brutto']} {$invoiceData['waluta']}\n";
        echo "  Pozycji: " . count($invoiceData['pozycje']) . "\n\n";

        // Upewnij się, że karty magazynowe istnieją zanim wyślemy fakturę
        $defaultWarehouse = $this->config['mapping']['default_warehouse'] ?? null;
        if ($defaultWarehouse) {
            $rokFaktury = intval(date('Y', strtotime($invoiceData['data_wystawienia'])));
            foreach ($invoiceData['pozycje'] as $poz) {
                if (!empty($poz['kod'])) {
                    $this->flexibee->ensureStockCard($poz['kod'], $defaultWarehouse, $rokFaktury);
                }
            }
        }

        // 2. Konwertuj do formatu Flexibee
        echo "Konwersja do formatu Flexibee...\n";
        $flexibeeData = convertToFlexibeeFormat($invoiceData);
        echo "✓ Dane przekonwertowane\n\n";

        // 3. Wyślij do Flexibee
        echo "Wysyłanie do Flexibee...\n";
        $result = $this->flexibee->createInvoice($flexibeeData);

        if ($result['success']) {
            if (isset($result['dry_run']) && $result['dry_run']) {
                echo "✓ DRY RUN - Faktura NIE została wysłana (ustaw DRY_RUN=false w .env)\n";
            } else {
                echo "✓ Faktura utworzona w Flexibee pomyślnie\n";
                // Zapisz status przetworzenia
                markInvoiceAsProcessed($invoiceNumber, 'SUCCESS', $invoiceNumber);
            }
        } else {
            echo "✗ Błąd: {$result['message']}\n";
            markInvoiceAsProcessed($invoiceNumber, 'ERROR', null, $result['message']);
        }

        return $result;
    }

    /**
     * Przetwarza wiele faktur na podstawie kryteriów
     * @param string $supplier Nazwa dostawcy (np. 'D2design s.r.o.')
     * @param string $dateFrom Data od (Y-m-d)
     * @param string $dateTo Data do (Y-m-d)
     * @param bool $skipProcessed Czy pomijać już przetworzone faktury
     * @return array Wyniki przetwarzania
     */
    public function processBatch($supplier = 'D2design s.r.o.', $dateFrom = null, $dateTo = null, $skipProcessed = true)
    {
        echo "=== Przetwarzanie wsadowe ===\n";
        echo "Dostawca: $supplier\n";
        echo "Okres: " . ($dateFrom ?: 'początek miesiąca') . " - " . ($dateTo ?: 'koniec miesiąca') . "\n\n";

        // Pobierz listę faktur
        $invoices = getInvoicesList($supplier, $dateFrom, $dateTo);
        
        if (empty($invoices)) {
            echo "Nie znaleziono faktur do przetworzenia.\n";
            return [
                'success' => true,
                'total' => 0,
                'processed' => 0,
                'skipped' => 0,
                'errors' => 0
            ];
        }

        echo "Znaleziono " . count($invoices) . " faktur do przetworzenia.\n\n";

        $stats = [
            'total' => count($invoices),
            'processed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => []
        ];

        foreach ($invoices as $invoiceNumber) {
            $result = $this->processInvoice($invoiceNumber, $skipProcessed);
            
            if (isset($result['skipped']) && $result['skipped']) {
                $stats['skipped']++;
            } elseif ($result['success']) {
                $stats['processed']++;
            } else {
                $stats['errors']++;
            }

            $stats['details'][$invoiceNumber] = $result;
            
            // Krótka przerwa między fakturami aby nie przeciążać API
            usleep(500000); // 0.5 sekundy
        }

        echo "\n=== PODSUMOWANIE ===\n";
        echo "Razem faktur: {$stats['total']}\n";
        echo "Przetworzono: {$stats['processed']}\n";
        echo "Pominięto (już przetworzone): {$stats['skipped']}\n";
        echo "Błędy: {$stats['errors']}\n";

        return [
            'success' => true,
            'stats' => $stats
        ];
    }

    /**
     * Testuje połączenie z Flexibee API
     * @return array Wynik testu
     */
    public function testConnection()
    {
        echo "=== Test połączenia z Flexibee ===\n";
        
        $result = $this->flexibee->testConnection();
        
        if ($result['success']) {
            echo "✓ Połączenie z Flexibee OK\n";
            echo "  URL: " . $this->config['flexibee']['api_url'] . "\n";
            echo "  Firma: " . $this->config['flexibee']['company_id'] . "\n";
        } else {
            echo "✗ " . $result['message'] . "\n";
        }
        
        return $result;
    }

    /**
     * Testuje połączenie z bazą danych
     * @return array Wynik testu
     */
    public function testDatabase()
    {
        echo "=== Test połączenia z bazą danych ===\n";
        
        try {
            $result = $this->db->query("SELECT @@VERSION as version");
            echo "✓ Połączenie z SQL Server OK\n";
            echo "  Server: " . $this->config['database']['server'] . "\n";
            echo "  Database: " . $this->config['database']['database'] . "\n";
            
            return [
                'success' => true,
                'message' => 'Połączenie z bazą danych OK'
            ];
        } catch (Exception $e) {
            echo "✗ Błąd połączenia: " . $e->getMessage() . "\n";
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

// ===== CLI USAGE =====
if (php_sapi_name() === 'cli') {
    $creator = new InvoiceCreator();

    // Sprawdź argumenty wiersza poleceń
    $command = $argv[1] ?? 'help';

    switch ($command) {
        case 'test':
            // Test połączeń
            $creator->testDatabase();
            echo "\n";
            $creator->testConnection();
            break;

        case 'process':
            // Przetwórz pojedynczą fakturę
            $invoiceNumber = $argv[2] ?? null;
            
            if (!$invoiceNumber) {
                echo "Użycie: php InvoiceCreator.php process <numer_faktury>\n";
                echo "Przykład: php InvoiceCreator.php process FUE/0020/12/25\n";
                exit(1);
            }
            
            $result = $creator->processInvoice($invoiceNumber);
            exit($result['success'] ? 0 : 1);

        case 'batch':
            // Przetwarzanie wsadowe
            $supplier = $argv[2] ?? 'D2design s.r.o.';
            $dateFrom = $argv[3] ?? null;
            $dateTo = $argv[4] ?? null;
            
            echo "Przetwarzanie wsadowe faktur\n";
            if (!$dateFrom && !$dateTo) {
                echo "Okres: bieżący miesiąc\n";
            }
            echo "\n";
            
            $result = $creator->processBatch($supplier, $dateFrom, $dateTo);
            exit($result['success'] ? 0 : 1);

        case 'new':
            // Przetwarzanie tylko nowych faktur (nie przetworzonych wcześniej)
            $supplier = $argv[2] ?? 'D2design s.r.o.';
            $dateFrom = $argv[3] ?? null;
            $dateTo = $argv[4] ?? null;
            
            echo "Przetwarzanie NOWYCH faktur (pomijam już przetworzone)\n";
            if (!$dateFrom && !$dateTo) {
                echo "Okres: bieżący miesiąc\n";
            }
            echo "\n";
            
            $result = $creator->processBatch($supplier, $dateFrom, $dateTo, true);
            exit($result['success'] ? 0 : 1);

        case 'contacts':
            // Lista kontrahentów
            $api = new FlexibeeAPI();
            $result = $api->getContacts(['limit' => 10]);
            
            if (isset($result['winstrom']['adresar'])) {
                echo "=== Lista kontrahentów (pierwsze 10) ===\n\n";
                foreach ($result['winstrom']['adresar'] as $contact) {
                    echo "ID: " . ($contact['id'] ?? 'brak') . "\n";
                    echo "Kod: " . ($contact['kod'] ?? 'brak') . "\n";
                    echo "Nazwa: " . ($contact['nazev'] ?? 'brak') . "\n";
                    echo "---\n";
                }
            } else {
                echo "Błąd pobierania kontrahentów\n";
                print_r($result);
            }
            break;

        case 'help':
        default:
            echo "=== Furnizone Invoice Creator ===\n\n";
            echo "Użycie: php InvoiceCreator.php <komenda> [argumenty]\n\n";
            echo "Dostępne komendy:\n";
            echo "  test                           - Test połączeń z bazą i Flexibee\n";
            echo "  process <numer>                - Przetwarza pojedynczą fakturę\n";
            echo "  batch [dostawca] [od] [do]     - Przetwarza wszystkie faktury z okresu\n";
            echo "  new [dostawca] [od] [do]       - Przetwarza tylko nowe faktury\n";
            echo "  contacts                       - Wyświetla listę kontrahentów z Flexibee\n";
            echo "  help                           - Wyświetla tę pomoc\n\n";
            echo "Przykłady:\n";
            echo "  php InvoiceCreator.php test\n";
            echo "  php InvoiceCreator.php process FUE/0020/12/25\n";
            echo "  php InvoiceCreator.php batch \"D2design s.r.o.\"\n";
            echo "  php InvoiceCreator.php batch \"D2design s.r.o.\" 2025-12-01 2025-12-31\n";
            echo "  php InvoiceCreator.php new \"D2design s.r.o.\"\n";
            break;
    }
}
