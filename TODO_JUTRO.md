# TODO na jutro - fast-php-log-viewer

## 🎯 Główne zadanie
**Naprawić wyświetlanie zawartości plików z podkatalogów** - katalogi listują pliki, ale zawartość się nie wyświetla.

## 📋 Lista zadań

1. **Naprawić wyświetlanie zawartości plików z podkatalogów**
   - Problem: Listuje pliki z podkatalogów ale nie pokazuje treści
   - Sprawdzić czy security check blokuje pliki w podkatalogach
   - Sprawdzić czy LogFinder poprawnie skanuje podkatalogi

2. **Przetestować ładowanie plików z różnych ścieżek**
   - Lokalne pliki
   - Pliki w podkatalogach
   - Ścieżki bezpośrednie (np. /var/log/php/php_errors.log)

3. **Sprawdzić security check dla plików w podkatalogach**
   - Funkcja respondEntries() sprawdza czy plik jest w dozwolonych katalogach
   - Może być problem z `str_starts_with` dla podkatalogów

4. **Interfejs sidebar - ulepszenia**
   - Sidebar też jest do zrobienia (interfejs)
   - Użytkownik chce ulepszenia interfejsu sidebar
   - Dokładne wymagania do ustalenia jutro

## ✅ Dzisiaj zrobione

- Powiększony sidebar (200px → 280px)
- Powiększona lista plików (flex: 3)
- Format daty: DD.MM.YYYY HH:mm:ss
- Dodana druga linia z allow
- DEBUG i INFO na górze listy poziomów
- Sortowanie i filtry przeniesione na górę sidebar
- Obsługa plików, dodawanie katalogów, SSH na dole
- Deduplikacja katalogów (nie można dodać tego samego)
- Przycisk "🧹 Czyść duplikaty"
- Dodana obsługa prostego formatu logów: [2024-06-05 12:00:00] INFO: Message
- Logowanie błędów PHP tylko do pliku (nie na ekran)
- Naprawione uprawnienia katalogu data
- Testowy plik /var/log/test506.log wygenerowany

## 🔧 Status kontenera
- Container: c66cf976ef10
- Port: 8123 → 80
- URL: http://localhost:8123

## 💡 Uwagi
- LogParser obsługuje teraz: fast-php-logger + simple format + legacy + php-errors
- Baza SQLite w /var/www/html/data/logviewer.db
- Uprawnienia data: www-data:www-data

---
Data: 2026-06-05
Status: Gotowy do pracy jutro