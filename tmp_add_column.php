<?php
require 'Database.php';

$db = Database::getInstance();

$sql = "
IF NOT EXISTS (
    SELECT * FROM sys.columns 
    WHERE object_id = OBJECT_ID(N'dbo.Igor_faktury_wgrane_furnizone') 
    AND name = 'PRODUKTY_INFO'
)
BEGIN
    ALTER TABLE Igor_faktury_wgrane_furnizone
    ADD PRODUKTY_INFO NVARCHAR(MAX) NULL;
    
    SELECT 'Kolumna PRODUKTY_INFO została dodana.' as Result;
END
ELSE
BEGIN
    SELECT 'Kolumna PRODUKTY_INFO już istnieje.' as Result;
END
";

try {
    $result = $db->queryOne($sql);
    echo $result['Result'] . "\n";
} catch (Exception $e) {
    echo "Błąd: " . $e->getMessage() . "\n";
}
