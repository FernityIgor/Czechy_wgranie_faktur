-- Dodaje kolumnę PRODUKTY_INFO do istniejącej tabeli
-- Wykonaj tylko jeśli tabela już istnieje bez tej kolumny

IF NOT EXISTS (
    SELECT * FROM sys.columns 
    WHERE object_id = OBJECT_ID(N'dbo.Igor_faktury_wgrane_furnizone') 
    AND name = 'PRODUKTY_INFO'
)
BEGIN
    ALTER TABLE Igor_faktury_wgrane_furnizone
    ADD PRODUKTY_INFO NVARCHAR(MAX) NULL;
    
    PRINT 'Kolumna PRODUKTY_INFO została dodana.';
END
ELSE
BEGIN
    PRINT 'Kolumna PRODUKTY_INFO już istnieje.';
END
