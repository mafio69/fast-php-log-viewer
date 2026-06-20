# TODO - Automatyczne ładowanie plików SSH

## Obecny stan

- ✅ Podstawowe automatyczne ładowanie po pobraniu SSH działa
- ✅ Pliki są dodawane do listy i automatycznie wyświetlane
- ✅ Security validation działa (sanityzacja, validacja binarek, suspicious content)

## Do zrobienia

### Priorytet 1 - Poprawki UX

- [ ] Dodać wizualny indicator ładowania (spinner/progress bar)
- [ ] Pokazywać status ładowania w czasie rzeczywistym
- [ ] Dodać error handling przy automatycznym ładowaniu
- [ ] Obsłużyć case gdy plik jest pusty po pobraniu
- [ ] Dodać możliwość anulowania ładowania

### Priorytet 2 - Funkcjonalność

- [ ] Dodać opcję "Auto-load" w konfiguracji SSH (włącz/wyłącz)
- [ ] Obsłużyć duże pliki (streaming zamiast pełnego ładowania)
- [ ] Dodać cache dla już pobranych plików
- [ ] Dodać historię pobranych plików
- [ ] Obsłużyć wielokrotne pobieranie tego samego pliku

### Priorytet 3 - Performance

- [ ] Optymalizacja ładowania dużych plików (paginacja)
- [ ] Lazy loading entries dla dużych plików
- [ ] Dodanie limitu entries do wyświetlenia (np. 1000)
- [ ] Wirtual scrolling dla dużych list

### Priorytet 4 - UI/UX

- [ ] Lepsze powiadomienia o statusie ładowania
- [ ] Progress bar dla pobierania dużych plików
- [ ] Animacja przy sukcesie/porażce
- [ ] Dark mode dla powiadomień
- [ ] Ikony dla różnych statusów

### Priorytet 5 - Error Handling

- [ ] Lepsze komunikaty błędów
- [ ] Retry mechanism dla failed downloads
- [ ] Timeout handling
- [ ] Network error handling
- [ ] Logowanie błędów do pliku

### Priorytet 6 - Security

- [ ] Rate limiting dla pobierania
- ] Dodanie signature verification dla plików
- [ ] Dodanie checksum (MD5/SHA256) dla pobranych plików
- [ ] Virus scanning integration (opcjonalne)
- [ ] Audit log dla wszystkich operacji pobierania

### Priorytet 7 - Admin

- [ ] Panel administracyjny do zarządzania pobranymi plikami
- [ ] Statystyki pobierań
- [ ] Możliwość czyszczenia temp directory
- [ ] Konfiguracja limitów i security
- [ ] Monitoring i alerting

## Uwagi

- Katalog `temp/` musi być dodany do konfiguracji po każdym resecie kontenera
- Security validation może być zbyt restrykcyjna dla niektórych use cases
- Rozważyć dodanie opcji "bypass security" za potwierdzeniem admina

## Testy

- [ ] Test automatycznego ładowania dla różnych typów plików
- [ ] Test ładowania dużych plików (>1MB)
- [ ] Test ładowania plików z suspicious content
- [ ] Test concurrent downloads
- [ ] Test error scenarios

  │ ● 1. [x] PRZEBUDOWA INTERFEJSU - Przenieść filtry czasu i sortowanie do sekcji filtrów poziomów
  │ ● 2. [x] PRZEBUDOWA INTERFEJSU - Przenieść poziomy info/debug do góry obok szukania tekstu
  │ ● 3. [x] PRZEBUDOWA INTERFEJSU - Zwiększyć okno dostępnych plików (280px → 350px)
  │ ● 4. [x] Wyczyścić listę katalogów - lepsze nazwy (2-3 segmenty ścieżki), usuwanie allowed_* i suffixów liczbowych
  │ ● 5. [x] Zmniejszyć czcionkę na liście plików (11px → 10px)
  │ ● 6. [x] Po dodaniu katalogu odświeżyć listę plików tylko z tego katalogu, wyświetlać 'pusto' gdy brak plików
  │ ● 7. [x] Przy zmianie katalogu w dropdown aktualizować listę plików z wybranego katalogu
  │ ● 8. [x] Ścieżka z pola tekstowego obsługiwana - auto-dodawanie katalogu przy błędzie dostępu
  └ ● 9. [x] DataTable - sortowanie kolumn, paginacja (50/100/250/500/1000 na stronę), nawigacja stron