<?php
/**
 * Ekstrakcja danych faktury z bazy danych do formatu Flexibee
 */

require_once 'app_config.php';
require_once 'Database.php';
require_once 'FlexibeeAPI.php';

/**
 * Sprawdza czy faktura została już przetworzona
 * @param string $invoiceNumber Numer faktury
 * @return bool
 */
function isInvoiceProcessed($invoiceNumber) {
    $db = Database::getInstance();
    
    $sql = "SELECT COUNT(*) as cnt FROM Igor_faktury_wgrane_furnizone WHERE NUMER_FAKTURY = ? AND STATUS = 'SUCCESS'";
    
    try {
        $result = $db->queryOne($sql, [$invoiceNumber]);
        return $result && $result['cnt'] > 0;
    } catch (Exception $e) {
        // Jeśli tabela nie istnieje, zwróć false
        return false;
    }
}

/**
 * Zapisuje informację o przetworzeniu faktury
 * @param string $invoiceNumber Numer faktury
 * @param string $status SUCCESS lub ERROR
 * @param string $flexibeeId ID w Flexibee (opcjonalne)
 * @param string $errorMessage Komunikat błędu (opcjonalnie)
 * @param string $produktyInfo Informacje o dodanych produktach (opcjonalnie)
 * @return bool
 */
function markInvoiceAsProcessed($invoiceNumber, $status = 'SUCCESS', $flexibeeId = null, $errorMessage = null, $produktyInfo = null) {
    $db = Database::getInstance();
    
    // Pobierz dane faktury
    $sqlInvoice = "
    SELECT TOP(1) 
        PLATNIK_NAZWA,
        FORMAT(DATEADD(day, DATA_WYSTAWIENIA - 36163, 0), 'yyyy-MM-dd HH:mm:ss') as DATA_WYSTAWIENIA
    FROM DOKUMENT_HANDLOWY
    WHERE NUMER = ?
    ";
    
    try {
        $invoice = $db->queryOne($sqlInvoice, [$invoiceNumber]);
        
        // Upsert: sprawdź istnienie i wykonaj UPDATE lub INSERT (bez batcha SQL, żeby uniknąć błędu drivera)
        $exists = $db->queryOne("SELECT 1 FROM Igor_faktury_wgrane_furnizone WHERE NUMER_FAKTURY = ?", [$invoiceNumber]);

        if ($exists) {
            $db->query(
                "UPDATE Igor_faktury_wgrane_furnizone
                 SET PLATNIK_NAZWA = ?,
                     DATA_WYSTAWIENIA = CONVERT(DATETIME, ?, 120),
                     STATUS = ?,
                     FLEXIBEE_ID = ?,
                     KOMUNIKAT_BLEDU = ?,
                     PRODUKTY_INFO = ?,
                     DATA_PRZETWORZENIA = GETDATE()
                 WHERE NUMER_FAKTURY = ?",
                [
                    $invoice ? $invoice['PLATNIK_NAZWA'] : null,
                    $invoice ? $invoice['DATA_WYSTAWIENIA'] : null,
                    $status,
                    $flexibeeId,
                    $errorMessage,
                    $produktyInfo,
                    $invoiceNumber
                ]
            );
        } else {
            $db->query(
                "INSERT INTO Igor_faktury_wgrane_furnizone
                 (NUMER_FAKTURY, PLATNIK_NAZWA, DATA_WYSTAWIENIA, STATUS, FLEXIBEE_ID, KOMUNIKAT_BLEDU, PRODUKTY_INFO)
                 VALUES (?, ?, CONVERT(DATETIME, ?, 120), ?, ?, ?, ?)",
                [
                    $invoiceNumber,
                    $invoice ? $invoice['PLATNIK_NAZWA'] : null,
                    $invoice ? $invoice['DATA_WYSTAWIENIA'] : null,
                    $status,
                    $flexibeeId,
                    $errorMessage,
                    $produktyInfo
                ]
            );
        }
        
        return true;
    } catch (Exception $e) {
        echo "Uwaga: Nie można zapisać statusu przetworzenia: " . $e->getMessage() . "\n";
        echo "Uruchom skrypt tracking_table.sql aby utworzyć tabelę śledzenia.\n";
        return false;
    }
}

/**
 * Pobiera datę ostatniej przetworzonej faktury
 * @param string $supplier Nazwa dostawcy
 * @return string|null Data w formacie Y-m-d lub null
 */
function getLastProcessedInvoiceDate($supplier = 'D2design s.r.o.') {
    $db = Database::getInstance();
    
    $sql = "
    SELECT TOP(1) 
        DATA_PRZETWORZENIA,
        YEAR(DATA_PRZETWORZENIA) as ROK,
        MONTH(DATA_PRZETWORZENIA) as MIESIAC,
        DAY(DATA_PRZETWORZENIA) as DZIEN
    FROM Igor_faktury_wgrane_furnizone
    WHERE PLATNIK_NAZWA = ?
    AND STATUS = 'SUCCESS'
    ORDER BY DATA_PRZETWORZENIA DESC
    ";
    
    try {
        $result = $db->queryOne($sql, [$supplier]);
        if ($result && $result['DATA_PRZETWORZENIA']) {
            // Pobierz komponenty daty z SQL Server (są w poprawnym formacie)
            $year = $result['ROK'];
            $month = $result['MIESIAC'];
            $day = $result['DZIEN'];
            
            // Utwórz datę w formacie Y-m-d
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    } catch (Exception $e) {
        // Tabela nie istnieje lub błąd - zwróć null
    }
    
    return null;
}

/**
 * Pobiera listę faktur do przetworzenia
 * @param string $supplier Nazwa dostawcy (np. 'D2design s.r.o.')
 * @param string $dateFrom Data od (Y-m-d)
 * @param string $dateTo Data do (Y-m-d)
 * @return array Lista numerów faktur
 */
function getInvoicesList($supplier = 'D2design s.r.o.', $dateFrom = null, $dateTo = null) {
    $db = Database::getInstance();
    
    // Jeśli nie podano dateFrom, sprawdź ostatnią przetworzoną fakturę
    if (!$dateFrom) {
        $lastDate = getLastProcessedInvoiceDate($supplier);
        if ($lastDate) {
            // Jeśli mamy już przetworzone faktury, szukaj od tej daty
            $dateFrom = $lastDate;
            echo "Ostatnia przetworzona faktura z: $lastDate - szukam od tej daty\n";
        } else {
            // Jeśli to pierwsze uruchomienie, weź cały bieżący miesiąc
            $dateFrom = date('Y-m-01');
            echo "Pierwsze uruchomienie - szukam od początku miesiąca\n";
        }
    }
    
    // Domyślnie do końca miesiąca
    if (!$dateTo) {
        $dateTo = date('Y-m-t'); // ostatni dzień miesiąca
    }
    
    // Konwersja dat do formatu SQL Server
    // DATA_WYSTAWIENIA w bazie to liczba dni + 36163
    // Więc musimy przekonwertować datę na dni i dodać 36163
    $dateFromObj = new DateTime($dateFrom);
    $dateToObj = new DateTime($dateTo);
    
    // Oblicz dni od 1900-01-01 (bazowa data SQL Server)
    $baseDate = new DateTime('1900-01-01');
    $dateFromSqlValue = $dateFromObj->diff($baseDate)->days + 36163;
    $dateToSqlValue = $dateToObj->diff($baseDate)->days + 36163;
    
    $sql = "
    SELECT DISTINCT han.NUMER
    FROM DOKUMENT_HANDLOWY han
    WHERE han.PLATNIK_NAZWA = ?
    AND han.DATA_WYSTAWIENIA >= ?
    AND han.DATA_WYSTAWIENIA <= ?
    AND han.NUMER NOT LIKE 'FK/%'
    ORDER BY han.NUMER
    ";
    
    try {
        $results = $db->query($sql, [$supplier, $dateFromSqlValue, $dateToSqlValue]);
        return array_column($results, 'NUMER');
    } catch (Exception $e) {
        echo "Błąd pobierania listy faktur: " . $e->getMessage() . "\n";
        return [];
    }
}

/**
 * Pobiera numery zamówień powiązane z fakturą
 * @param string $invoiceNumber Numer faktury
 * @return array Tablica z numerami zamówień ['zam_wfmag' => ..., 'zam_iai' => ...]
 */
function getInvoiceOrderNumbers($invoiceNumber) {
    $db = Database::getInstance();
    
    $sql = "
    SELECT DISTINCT 
        zam.NUMER as zam_wfmag, 
        zam.NR_ZAMOWIENIA_KLIENTA as zam_iai
    FROM ZAMOWIENIE zam
    INNER JOIN POZYCJA_ZAMOWIENIA pzam ON zam.ID_ZAMOWIENIA = pzam.ID_ZAMOWIENIA
    INNER JOIN POZYCJA_DOKUMENTU_MAGAZYNOWEGO pmag ON pzam.ID_POZYCJI_ZAMOWIENIA = pmag.ID_POZ_ZAM
    INNER JOIN DOKUMENT_HANDLOWY han ON pmag.ID_DOK_HANDLOWEGO = han.ID_DOKUMENTU_HANDLOWEGO
    WHERE han.NUMER LIKE ?
    ";
    
    try {
        $results = $db->query($sql, [$invoiceNumber]);
        
        if (!empty($results)) {
            // Jeśli jest wiele zamówień, połącz je przecinkami
            $wfmagNumbers = array_filter(array_column($results, 'zam_wfmag'));
            $iaiNumbers = array_filter(array_column($results, 'zam_iai'));
            
            return [
                'zam_wfmag' => !empty($wfmagNumbers) ? implode(', ', $wfmagNumbers) : null,
                'zam_iai' => !empty($iaiNumbers) ? implode(', ', $iaiNumbers) : null
            ];
        }
    } catch (Exception $e) {
        // Jeśli nie ma zamówień lub błąd, zwróć puste wartości
    }
    
    return ['zam_wfmag' => null, 'zam_iai' => null];
}

/**
 * Pobiera dane faktury z bazy danych
 * @param string $invoiceNumber Numer faktury (np. 'FUE/0020/12/25')
 * @return array|null Dane faktury lub null jeśli nie znaleziono
 */
function getInvoiceData($invoiceNumber) {
    $db = Database::getInstance();
    
    // Zapytanie z konwersją dat
    $sql = "
    SELECT TOP(100) 
        han.ID_DOKUMENTU_HANDLOWEGO,
        han.NUMER,
        han.PLATNIK_NAZWA,
        CONVERT(datetime, han.DATA_WYSTAWIENIA - 36163) as DATA_WYSTAWIENIA,
        CONVERT(datetime, han.DATA_SPRZEDAZY - 36163) as DATA_SPRZEDAZY,
        CONVERT(datetime, han.TERMIN_PLAT - 36163) as TERMIN_PLAT,
        han.SYM_WAL,
        han.FORMA_PLATNOSCI,
        han.UWAGI,
        pmag.ID_POZ_DOK_MAG,
        pmag.ILOSC,
        pmag.CENA_NETTO,
        pmag.CENA_BRUTTO,
        pmag.JEDNOSTKA,
        ar.NAZWA,
        ar.INDEKS_KATALOGOWY,
        ar.PKWIU,
        ar.VAT_SPRZEDAZY,
        ar.KOD_KRESKOWY,
        ar.RODZAJ,
        iai.iai_id
    FROM DOKUMENT_HANDLOWY han
    INNER JOIN POZYCJA_DOKUMENTU_MAGAZYNOWEGO pmag ON han.ID_DOKUMENTU_HANDLOWEGO = pmag.ID_DOK_HANDLOWEGO
    INNER JOIN ARTYKUL ar ON pmag.ID_ARTYKULU = ar.ID_ARTYKULU
    LEFT JOIN MAGGEN_IAI iai ON ar.ID_ARTYKULU = iai.ID_NADRZEDNEGO
    WHERE han.NUMER LIKE ?
    ORDER BY han.ID_DOKUMENTU_HANDLOWEGO DESC
    ";
    
    try {
        $results = $db->query($sql, [$invoiceNumber]);
        
        if (empty($results)) {
            return null;
        }
        
        // Buduj strukturę faktury
        $firstRow = $results[0];
        
        // Pobierz numery zamówień
        $orderNumbers = getInvoiceOrderNumbers($invoiceNumber);
        
        $invoice = [
            'id_dokumentu' => $firstRow['ID_DOKUMENTU_HANDLOWEGO'],
            'numer' => $firstRow['NUMER'],
            'kontrahent' => $firstRow['PLATNIK_NAZWA'],
            'data_wystawienia' => $firstRow['DATA_WYSTAWIENIA'],
            'data_sprzedazy' => $firstRow['DATA_SPRZEDAZY'],
            'termin_platnosci' => $firstRow['TERMIN_PLAT'],
            'waluta' => $firstRow['SYM_WAL'] ?: 'PLN',
            'forma_platnosci' => $firstRow['FORMA_PLATNOSCI'],
            'uwagi' => $firstRow['UWAGI'],
            'zam_wfmag' => $orderNumbers['zam_wfmag'],
            'zam_iai' => $orderNumbers['zam_iai'],
            'suma_netto' => 0,
            'suma_brutto' => 0,
            'pozycje' => []
        ];
        
        // Dodaj pozycje i oblicz sumy
        foreach ($results as $row) {
            $cenaNetto = floatval($row['CENA_NETTO']);
            $cenaBrutto = floatval($row['CENA_BRUTTO']);
            $ilosc = floatval($row['ILOSC']);
            
            $wartoscNetto = $cenaNetto * $ilosc;
            $wartoscBrutto = $cenaBrutto * $ilosc;
            
            $invoice['suma_netto'] += $wartoscNetto;
            $invoice['suma_brutto'] += $wartoscBrutto;
            
            $invoice['pozycje'][] = [
                'id_pozycji' => $row['ID_POZ_DOK_MAG'],
                'nazwa' => $row['NAZWA'],
                'kod' => $row['INDEKS_KATALOGOWY'],
                'pkwiu' => $row['PKWIU'],
                'ilosc' => $ilosc,
                'jednostka' => $row['JEDNOSTKA'],
                'cena_netto' => $cenaNetto,
                'cena_brutto' => $cenaBrutto,
                'wartosc_netto' => $wartoscNetto,
                'wartosc_brutto' => $wartoscBrutto,
                'stawka_vat' => floatval($row['VAT_SPRZEDAZY']),
                'ean' => $row['KOD_KRESKOWY'],
                'rodzaj' => $row['RODZAJ'],
                'iai_id' => $row['iai_id']
            ];
        }
        
        return $invoice;
        
    } catch (Exception $e) {
        echo "BŁĄD: " . $e->getMessage() . "\n";
        return null;
    }
}

/**
 * Konwertuje dane faktury do formatu Flexibee
 * @param array $invoice Dane faktury z bazy
 * @return array Struktura JSON dla Flexibee API
 */
function convertToFlexibeeFormat($invoice) {
    // Mapowanie na strukturę Flexibee
    // Dokumentacja: https://podpora.flexibee.eu/cs/collections/2592813-dokumentace-rest-api
    
    $flexibee = new FlexibeeAPI();
    $config = AppConfig::get();
    $defaultWarehouse = $config['mapping']['default_warehouse'] ?? null;
    
    // Tablica do śledzenia nowych produktów
    $newProducts = [];

    // Normalizuje jednostki z bazy do kodów Flexibee (np. szt -> ks)
    $normalizeUnit = function ($unit) {
        $clean = strtolower(trim($unit ?: 'ks'));
        $clean = rtrim($clean, '.');
        $map = [
            'szt' => 'ks',
            'sztuka' => 'ks',
            'sztuki' => 'ks',
            'sztuk' => 'ks',
            'kom' => 'ks',
            'ks' => 'ks'
        ];
        return $map[$clean] ?? $clean;
    };
    
    // Przygotuj cisObj z numerami zamówień
    $cisObj = '';
    if ($invoice['zam_wfmag']) {
        $cisObj .= 'Zam WFMAG: ' . $invoice['zam_wfmag'];
    }
    if ($invoice['zam_iai']) {
        if ($cisObj) $cisObj .= ' | ';
        $cisObj .= 'Zam IAI: ' . $invoice['zam_iai'];
    }
    
    $flexibeeInvoice = [
        'winstrom' => [
            '@version' => '1.0',
            'faktura-prijata' => [
                // Podstawowe dane
                'typDokl' => 'code:FA PLN',  // Typ dokumentu - faktura
                'cisDosle' => $invoice['numer'],  // Numer faktury od dostawcy (zewnętrzny)
                'datVyst' => date('Y-m-d', strtotime($invoice['data_wystawienia'])),  // Data wystawienia
                'datSplat' => date('Y-m-d', strtotime($invoice['termin_platnosci'])),  // Termin płatności
                'datUcPrij' => date('Y-m-d', strtotime($invoice['data_sprzedazy'])),  // Data sprzedaży
                'mena' => 'code:' . $invoice['waluta'],  // Waluta z prefiksem code:
                
                // Kontrahent - Fernity (kod D2PL w Flexibee)
                'firma' => 'code:D2PL',

                // Automatyczny ruch magazynowy (příjemka)
                // UWAGA: podaj właściwy kod typu skladového dokladu przyjęcia
                'typPohybuSklad' => 'code:PRIJEMKA',
                
                // Numer objednávky (zamówienia)
                'cisObj' => $cisObj ?: null,
                
                // Pozycje faktury
                'polozkyDokladu' => []
            ]
        ]
    ];
    
    // Dodaj pozycje
    foreach ($invoice['pozycje'] as $pozycja) {
        // Pomiń pozycje z zerową ilością lub ceną (Flexibee ich nie akceptuje)
        if ($pozycja['ilosc'] <= 0) {
            continue;
        }

        // Upewnij się, że produkt istnieje w ceníku (kod = INDEKS_KATALOGOWY)
        $produktKod = $pozycja['kod'];
        $produktNazwa = $pozycja['nazwa'];
        $produktMj = $normalizeUnit($pozycja['jednostka']);
        $produktEan = $pozycja['ean'] ?? null;
        $produktRodzaj = $pozycja['rodzaj'] ?? 'Towar';
        $produktIaiId = $pozycja['iai_id'] ?? null;

        $rokFaktury = intval(date('Y', strtotime($invoice['data_wystawienia'])));
        $produktInfo = $flexibee->ensureProduct($produktKod, $produktNazwa, $produktMj, $defaultWarehouse, $rokFaktury, $produktEan, $produktRodzaj, $produktIaiId) ?: ['code' => $pozycja['kod'], 'skladovy' => false];
        
        // Zapisz informacje o nowym produkcie
        if (isset($produktInfo['new']) && $produktInfo['new']) {
            $newProducts[] = [
                'kod' => $produktInfo['code'],
                'iai_id' => $produktInfo['iai_id'] ?? null,
                'czech_name' => $produktInfo['czech_name'] ?? 'NO_IAI'
            ];
        }
        
        // Dodatkowe zabezpieczenie: upewnij się, że istnieje karta magazynowa dla danego roku
        if ($defaultWarehouse) {
            $flexibee->ensureStockCard($produktKod, $defaultWarehouse, $rokFaktury);
        }
        $produktKod = $produktInfo['code'];
        
        $line = [
            'nazev' => $pozycja['nazwa'],  // Nazwa produktu/usługi
            // Ustawiamy kartę z ceníku po kodzie, aby kod był widoczny na fakturze
            'cenik' => 'code:' . $produktKod,
            'mnozMj' => $pozycja['ilosc'],  // Ilość
            'cenaMj' => $pozycja['cena_netto'],  // Cena jednostkowa netto
        ];

        // Magazyn na linii: tylko gdy mamy kod magazynu i towar jest magazynowy
        if ($defaultWarehouse && !empty($produktInfo['skladovy'])) {
            $line['sklad'] = $defaultWarehouse;
        }

        $flexibeeInvoice['winstrom']['faktura-prijata']['polozkyDokladu'][] = $line;
    }
    
    // Dodaj informacje o nowych produktach do wyniku
    $flexibeeInvoice['_new_products'] = $newProducts;
    
    return $flexibeeInvoice;
}

// ===== TEST =====
// Uruchamiaj tylko gdy wywołany bezpośrednio, nie przez include
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    echo "=== TEST EKSTRAKCJI DANYCH FAKTURY ===\n\n";
    
    $invoiceNumber = 'FUE/0020/12/25';
    echo "Pobieranie faktury: $invoiceNumber\n\n";
    
    $invoice = getInvoiceData($invoiceNumber);
    
    if ($invoice) {
        echo "=== DANE Z BAZY ===\n";
        echo "Numer: " . $invoice['numer'] . "\n";
        echo "Kontrahent: " . $invoice['kontrahent'] . "\n";
        echo "Data wystawienia: " . $invoice['data_wystawienia'] . "\n";
        echo "Data sprzedaży: " . $invoice['data_sprzedazy'] . "\n";
        echo "Termin płatności: " . $invoice['termin_platnosci'] . "\n";
        echo "Waluta: " . $invoice['waluta'] . "\n";
        echo "Suma netto: " . $invoice['suma_netto'] . "\n";
        echo "Suma brutto: " . $invoice['suma_brutto'] . "\n";
        echo "Liczba pozycji: " . count($invoice['pozycje']) . "\n\n";
        
        echo "=== POZYCJE ===\n";
        foreach ($invoice['pozycje'] as $i => $poz) {
            echo "\nPozycja " . ($i + 1) . ":\n";
            echo "  Nazwa: " . $poz['nazwa'] . "\n";
            echo "  Kod: " . $poz['kod'] . "\n";
            echo "  Ilość: " . $poz['ilosc'] . " " . $poz['jednostka'] . "\n";
            echo "  Cena netto: " . $poz['cena_netto'] . "\n";
            echo "  Cena brutto: " . $poz['cena_brutto'] . "\n";
            echo "  Wartość netto: " . $poz['wartosc_netto'] . "\n";
            echo "  Wartość brutto: " . $poz['wartosc_brutto'] . "\n";
            echo "  VAT: " . $poz['stawka_vat'] . "%\n";
        }
        
        echo "\n\n=== FORMAT FLEXIBEE ===\n";
        $flexibeeData = convertToFlexibeeFormat($invoice);
        echo json_encode($flexibeeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        
    } else {
        echo "Nie znaleziono faktury!\n";
    }
    
    echo "\n=== KONIEC TESTU ===\n";
}
