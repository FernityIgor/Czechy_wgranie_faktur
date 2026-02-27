# Furnizone - Integracja z Flexibee (Księgowość)

## 📋 Opis projektu

Automatyczne tworzenie faktur w systemie księgowym Flexibee na podstawie danych z SQL Server (baza d2).

---

## 🤔 Co robi ten program?

Program automatyzuje księgowanie faktur zakupowych od czeskiego dostawcy (D2design s.r.o.) w systemie Flexibee. W skrócie:

1. **Pobiera faktury** z bazy danych SQL Server (systemu magazynowo-handlowego WF-Mag / IAI)
2. **Konwertuje** dane do formatu wymaganego przez Flexibee API
3. **Tworzy produkty** w cenniku Flexibee (jeśli nie istnieją), pobierając automatycznie czeskie nazwy z API sklepu
4. **Wysyła faktury** do systemu Flexibee jako faktury przyjęte (zakupowe), generując jednocześnie ruch magazynowy (przyjęcie towaru)
5. **Śledzi postęp** – każda przetworzona faktura jest oznaczana w bazie danych, dzięki czemu program nie przetwarza jej ponownie przy kolejnym uruchomieniu

### 🔄 Szczegółowy przepływ działania

```
SQL Server (baza d2)
       │
       │  1. Pobierz listę faktur (DOKUMENT_HANDLOWY)
       │     filtrowanie po dostawcy i zakresie dat
       ▼
 extract_invoice_data.php
       │
       │  2. Dla każdej faktury:
       │     - Pobierz nagłówek: numer, daty, kontrahent, waluta
       │     - Pobierz pozycje: artykuły, ilości, ceny, VAT, EAN
       │     - Pobierz powiązane numery zamówień (WFMAG + IAI)
       ▼
 FlexibeeAPI.php (ensureProduct)
       │
       │  3. Dla każdej pozycji faktury sprawdź, czy produkt istnieje w Flexibee:
       │     - Jeśli NIE istnieje → utwórz produkt w cenniku (cenik)
       │       • Pobierz czeską nazwę z API dkwadrat.pl (po IAI_ID)
       │       • Ustaw grupę towarową (ZBOŽÍ / SLUŽBY)
       │       • Utwórz kartę magazynową (skladova-karta)
       │     - Jeśli istnieje → upewnij się, że jest magazynowy i ma kartę
       ▼
 FlexibeeAPI.php (createInvoice)
       │
       │  4. Wyślij fakturę do Flexibee (POST /faktura-prijata):
       │     - cisDosle = numer faktury (zewnętrzny)
       │     - firma = code:D2PL (dostawca)
       │     - typPohybuSklad = PRIJEMKA (automatyczne przyjęcie magazynowe)
       │     - polozkyDokladu = pozycje z kodami z ceníku
       ▼
 extract_invoice_data.php (markInvoiceAsProcessed)
       │
       │  5. Zapisz wynik do tabeli Igor_faktury_wgrane_furnizone:
       │     - STATUS = SUCCESS / ERROR
       │     - PRODUKTY_INFO = lista nowych produktów
       │     - DATA_PRZETWORZENIA = timestamp
       ▼
      KONIEC
```

### 📦 Kluczowe pliki i ich rola

| Plik | Rola |
|---|---|
| `InvoiceCreator.php` | Główny skrypt – orkiestruje cały proces, obsługuje CLI |
| `extract_invoice_data.php` | Pobiera dane z SQL Server i konwertuje do formatu Flexibee |
| `FlexibeeAPI.php` | Klient REST API Flexibee (faktury, produkty, magazyn) |
| `Database.php` | Połączenie z SQL Server przez PDO/SQLSRV |
| `app_config.php` | Wczytuje konfigurację z pliku `.env` |
| `tracking_table.sql` | SQL do utworzenia tabeli śledzenia przetworzonych faktur |

---

## 🏗️ Architektura

- **Źródło danych:** SQL Server (192.168.230.100:11519, baza d2)
- **Cel:** Flexibee API (system księgowy)
- **Środowisko:** Docker + PHP 8.2 + Apache
- **Autentyzacja:** HTTP Basic Auth

## 🔐 Autentyzacja Flexibee API

Flexibee API wspiera **2 metody autentyzacji**:

### Metoda 1: HTTP Basic Auth (ZALECANA dla prostoty)

**Nie potrzebujesz tokena!** Wystarczy login i hasło.

```php
// Login i hasło wysyłane z każdym requestem
$username = 'twoj_login';
$password = 'twoje_haslo';

// Przykład z cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
```

**Plusy:**
- ✅ Bardzo proste - bez zarządzania tokenami
- ✅ Działa zawsze - nie trzeba odświeżać
- ✅ Idealne do skryptów automatycznych

**Minusy:**
- ⚠️ Login/hasło w każdym requeście (ale przez HTTPS jest bezpieczne)

---

### Metoda 2: Token Auth (dla zaawansowanych)

**Wymaga tokena** - najpierw musisz się zalogować aby go otrzymać.

#### Krok 1: Logowanie i pobranie tokena

```bash
POST https://twoja-domena.flexibee.eu/login-logout/login.json

Body (JSON):
{
    "username": "twoj_login",
    "password": "twoje_haslo"
}

Odpowiedź:
{
    "success": true,
    "authSessionId": "00112233445566778899aabbccddeeff..."
}
```

#### Krok 2: Używanie tokena

Token możesz wysłać na 3 sposoby:

```php
// 1. Cookie (NAJLEPSZE)
Cookie: authSessionId=00112233445566778899aabbccddeeff...

// 2. HTTP Header
X-authSessionId: 00112233445566778899aabbccddeeff...

// 3. URL Query (NIE ZALECANE - logowane na serwerze)
?authSessionId=00112233445566778899aabbccddeeff...
```

#### Krok 3: Utrzymywanie tokena (keep-alive)

Token wygasa po ~30 minutach nieaktywności. Aby go utrzymać:

```bash
GET /login-logout/session-keep-alive.js
# Wywołuj co 60 sekund lub co 30 minut
```

**Plusy:**
- ✅ Login/hasło tylko raz podczas logowania
- ✅ Szybsze dla wielu requestów

**Minusy:**
- ⚠️ Trzeba zarządzać wygasaniem tokena
- ⚠️ Trzeba implementować keep-alive lub refresh

---

## 🎯 Czego potrzebujesz?

### Dane dostępowe:

1. **URL API** - np. `https://twoja-firma.flexibee.eu`
2. **Login** - nazwa użytkownika Flexibee
3. **Hasło** - hasło użytkownika
4. **Company ID** - identyfikator firmy (np. `demo`, `firma1`)

### Opcjonalnie:

- **Port API** - domyślnie `5434` (HTTPS) lub `5433` (HTTP)
- **Konto bankowe** - ID konta do przypisania na fakturach

---

## 🚀 Instalacja

### Lokalnie (Windows/development):

1. **Skopiuj plik .env:**
   ```bash
   cp .env.example .env
   ```

2. **Uzupełnij dane w `.env`:**
   ```env
   FLEXIBEE_API_URL=https://twoja-firma.flexibee.eu
   FLEXIBEE_USERNAME=twoj_login
   FLEXIBEE_PASSWORD=twoje_haslo
   FLEXIBEE_COMPANY_ID=twoja_firma
   
   DB_SERVER=192.168.230.100,11519
   DB_DATABASE=d2
   DB_USERNAME=IgorCenyLive
   DB_PASSWORD=IgorCenyLive1979
   ```

3. **Utwórz katalog na logi:**
   ```bash
   mkdir logs
   ```

### Docker (Linux/production):

1. **Skopiuj i skonfiguruj .env** (jak wyżej)

2. **Zbuduj i uruchom:**
   ```bash
   docker-compose up -d --build
   ```

3. **Sprawdź logi:**
   ```bash
   docker-compose logs -f
   ```

4. **Aplikacja dostępna na:**
   ```
   http://localhost:8080
   ```

---

## 📚 Struktura plików

```
furnizone_ksiegowosc/
├── .env                    # Konfiguracja środowiska (NIE commituj!)
├── .env.example            # Przykład konfiguracji
├── config.php              # Loader konfiguracji z .env
├── Database.php            # Połączenie z SQL Server
├── FlexibeeAPI.php         # Klasa do komunikacji z Flexibee API
├── InvoiceCreator.php      # Logika tworzenia faktur
├── test_connection.php     # Test połączenia z oboma systemami
├── create_invoices.php     # Główny skrypt tworzący faktury
├── Dockerfile              # Konfiguracja Docker
├── docker-compose.yml      # Docker Compose setup
├── logs/                   # Logi (gitignore)
└── README.md               # Dokumentacja
```

---

## 💡 Rekomendacja

**Użyj HTTP Basic Auth** - jest prostsze i wystarczające dla większości zastosowań.

Token Auth przyda się tylko jeśli:
- Robisz setki requestów w krótkim czasie
- Chcesz uniknąć przesyłania hasła w każdym requeście
- Budujesz aplikację webową z sesją użytkownika

---

## 🔗 Linki

- [Dokumentacja Flexibee API](https://podpora.flexibee.eu/cs/collections/2592813-dokumentace-rest-api)
- [Autentyzacja](https://podpora.flexibee.eu/cs/articles/4713880-autentizace)
- [Demo API](https://demo.flexibee.eu/c/demo/)
