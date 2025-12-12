<?php
/**
 * Skrypt do utworzenia tabeli śledzenia faktur
 */

require_once __DIR__ . '/Database.php';

$db = Database::getInstance();

echo "Tworzenie tabeli Igor_faktury_wgrane_furnizone...\n";

try {
    // Sprawdź czy tabela już istnieje
    $checkSql = "
    SELECT COUNT(*) as cnt 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_NAME = 'Igor_faktury_wgrane_furnizone'
    ";
    
    $result = $db->queryOne($checkSql);
    
    if ($result['cnt'] > 0) {
        echo "✓ Tabela już istnieje.\n";
        exit(0);
    }
    
    // Utwórz tabelę
    $createSql = "
    CREATE TABLE Igor_faktury_wgrane_furnizone (
        ID INT IDENTITY(1,1) PRIMARY KEY,
        NUMER_FAKTURY NVARCHAR(50) NOT NULL,
        PLATNIK_NAZWA NVARCHAR(255),
        DATA_WYSTAWIENIA DATETIME,
        DATA_PRZETWORZENIA DATETIME DEFAULT GETDATE(),
        STATUS NVARCHAR(20) DEFAULT 'SUCCESS',
        FLEXIBEE_ID NVARCHAR(50),
        KOMUNIKAT_BLEDU NVARCHAR(MAX),
        CONSTRAINT UQ_NUMER_FAKTURY_IGOR UNIQUE (NUMER_FAKTURY)
    )
    ";
    
    $db->query($createSql);
    echo "✓ Tabela utworzona.\n";
    
    // Utwórz indeksy
    $db->query("CREATE INDEX IDX_DATA_PRZETWORZENIA ON Igor_faktury_wgrane_furnizone(DATA_PRZETWORZENIA)");
    echo "✓ Indeks DATA_PRZETWORZENIA utworzony.\n";
    
    $db->query("CREATE INDEX IDX_STATUS ON Igor_faktury_wgrane_furnizone(STATUS)");
    echo "✓ Indeks STATUS utworzony.\n";
    
    echo "\n✓ Wszystko gotowe!\n";
    
} catch (Exception $e) {
    echo "✗ Błąd: " . $e->getMessage() . "\n";
    exit(1);
}
