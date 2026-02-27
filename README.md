# Furnizone - Integracja z Flexibee (Księgowość)

## 📋 Opis projektu

Automatyczne tworzenie faktur w systemie księgowym Flexibee na podstawie danych z SQL Server (baza d2).

---

## ❓ Co robi ten program?

Program **automatycznie importuje faktury zakupowe** z wewnętrznego systemu WFMag (baza SQL Server) do czeskiego systemu księgowego **Flexibee**.

### Przepływ działania (krok po kroku):

1. **Pobiera listę faktur z bazy WFMag** – wyszukuje faktury od wskazanego dostawcy (domyślnie `D2design s.r.o.`) z podanego okresu (domyślnie bieżący miesiąc).

2. **Sprawdza, które faktury zostały już przetworzone** – korzysta z tabeli śledzenia `Igor_faktury_wgrane_furnizone` w SQL Server, aby nie importować tej samej faktury dwukrotnie.

3. **Pobiera dane faktury** – dla każdej nowej faktury pobiera z bazy pełne dane: nagłówek (kontrahent, daty, waluta), pozycje (produkty, ilości, ceny, VAT) oraz powiązane numery zamówień (WFMag i IAI).

4. **Weryfikuje i tworzy produkty w Flexibee** – dla każdej pozycji faktury sprawdza, czy produkt istnieje już w cenniku (katalogu) Flexibee:
   - Jeśli produkt istnieje – upewnia się, że jest oznaczony jako magazynowy i ma przypisany magazyn.
   - Jeśli produkt **nie istnieje** – tworzy go automatycznie, pobiera jego czeską nazwę z API sklepu (`dkwadrat.pl`) i aktualizuje wpis w Flexibee.

5. **Tworzy karty magazynowe** – jeśli karta magazynowa dla danego produktu i roku nie istnieje, program tworzy ją automatycznie w wybranym magazynie Flexibee.

6. **Wysyła fakturę do Flexibee** – konwertuje dane do formatu API Flexibee i tworzy fakturę zakupową (`faktura-prijata`) wraz z ruchem magazynowym (przyjęcie towaru).

7. **Zapisuje status przetworzenia** – po udanym imporcie oznacza fakturę jako `SUCCESS` w tabeli śledzenia (z datą, ID w Flexibee i informacją o dodanych produktach). W razie błędu zapisuje komunikat błędu ze statusem `ERROR`.

### Tryb DRY RUN

Gdy w pliku `.env` ustawiono `DRY_RUN=true`, program **nie wysyła żadnych danych** do Flexibee ani nie tworzy produktów – tylko symuluje działanie. Przydatne do testowania.

### Obsługiwane komendy CLI

```bash
php InvoiceCreator.php test                              # Test połączeń z bazą i Flexibee
php InvoiceCreator.php process FUE/0020/12/25           # Import pojedynczej faktury
php InvoiceCreator.php batch "D2design s.r.o."          # Import wszystkich faktur z bieżącego miesiąca
php InvoiceCreator.php batch "D2design s.r.o." 2025-12-01 2025-12-31  # Import z podanego okresu
php InvoiceCreator.php new "D2design s.r.o."            # Import tylko nowych (nieprzetworzone) faktur
php InvoiceCreator.php contacts                         # Podgląd kontrahentów w Flexibee
```

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
