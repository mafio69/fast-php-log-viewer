# Audyt repozytorium fast-php-log-viewer

## PHP — martwy / nieużywany kod

### 1. `SSH::listFiles()` — nigdy nie wywoływana
- **Plik:** `src/Service/SSH.php:194`
- **Problem:** Metoda `listFiles()` parsuje `ls -la` na zdalne pliki, ale nigdzie w `src/` nie jest wywoływana. `SSHController::listFiles()` korzysta z `RemoteLogFinder::findAll()`, która używa `SSH::exec()` bezpośrednio (przez `find`).
- **Wniosek:** Martwy kod — do usunięcia.

### 2. `SSH::isAvailable()` — nigdzie nie wywoływana w kodzie produkcyjnym
- **Plik:** `src/Service/SSH.php:278`
- **Problem:** Metoda statyczna `isAvailable()` (sprawdza `extension_loaded('ssh2')`) jest używana **wyłącznie w testach** (`SSHTest.php`). Żaden kontroler ani serwis nie sprawdza dostępności SSH2 przed próbą połączenia.
- **Wniosek:** Martwy w produkcji — rozważ usunięcie lub faktyczne użycie w `SSHController`.

### 3. `SecurityService::sanitizeFilename()` — nigdy nie wywoływana w produkcji
- **Plik:** `src/Service/SecurityService.php:12`
- **Problem:** Metoda istnieje tylko dla testów (`SecurityTest.php`). Żadna część aplikacji jej nie wywołuje.
- **Wniosek:** Martwy kod — do usunięcia lub faktycznego użycia (np. w `SSH::downloadFile`).

### 4. `SecurityService::isValidTextFile()` — nigdy nie wywoływana w produkcji
- **Plik:** `src/Service/SecurityService.php:62`
- **Problem:** Jak wyżej — testowana w `SecurityTest.php`, ale nigdzie w `src/` nie używana.
- **Wniosek:** Martwy kod — do usunięcia lub faktycznego użycia.

### 5. `RemoteLogFinder::scanCommonDirectories()` — nigdy nie wywoływana w produkcji
- **Plik:** `src/Service/RemoteLogFinder.php:94`
- **Problem:** Metoda skanująca zdalne katalogi jest wywoływana tylko w testach (`RemoteLogFinderTest.php`). Żaden kontroler jej nie używa.
- **Wniosek:** Martwy kod — do usunięcia lub podłączenia do endpointu.

### 6. `LogParser::PATTERN_APK_TRIGGER` — zdefiniowana, ale nigdy nieużywana
- **Plik:** `src/Service/LogParser.php:39`
- **Problem:** Stała `PATTERN_APK_TRIGGER` jest zdefiniowana, ale nigdzie w `parseString()` nie ma bloku `if (preg_match(self::PATTERN_APK_TRIGGER, ...))`. Wzorzec `PATTERN_APK_EXEC` (linia 38) pokrywa ten sam przypadek.
- **Wniosek:** Pozostałość po refaktoryzacji — do usunięcia.

### 7. `LegacyRouter::resolve()` — nigdy nie wywoływana w produkcji
- **Plik:** `src/Routing/LegacyRouter.php:34`
- **Problem:** `resolve()` jest testowana w `LegacyRouterTest.php`, ale frontend.php używa tylko `hasAction()` i `rewriteRequestUri()`.
- **Wniosek:** Martwy kod — `rewriteRequestUri` robi to samo + więcej.

### 8. Zduplikowana ternarka `'INFO' : 'INFO'`
- **Plik:** `src/Service/LogParser.php:251`
- **Problem:** `$level = $m['action'] === 'Installing' ? 'INFO' : 'INFO';` — obie gałęzie zwracają to samo.
- **Wniosek:** Bezsensowna ternarka — zamienić na `$level = 'INFO';`.

### 9. Zduplikowany element `'*access*'` w tablicy
- **Plik:** `src/Service/LogScanner.php:33`
- **Problem:** `'*error*', '*debug*', '*access*', '*access*'` — `*access*` powtórzony 2×.
- **Wniosek:** Duplikat — usunąć drugi.

### 10. Zduplikowana logika detekcji poziomu logów (3× w LogParser)
- **Plik:** `src/Service/LogParser.php` linie 82-92, 304-311, 330-337
- **Problem:** Trzy identyczne bloki `str_contains($messageLower, 'error')...` do heurystycznego wykrywania poziomu logów (syslog, APT, systemd journal). Naruszenie DRY.
- **Wniosek:** Wyekstrahować do prywatnej metody, np. `private function guessLevel(string $message): string`.

---

## JavaScript — martwy / nieużywany kod

### 11. `levelDot` importowane w DataTable, ale nieużywane w szablonie
- **Plik:** `public/js/components/DataTable.js:122`
- **Problem:** `levelDot: F.levelDot` jest zwracane z `setup()`, ale szablon DataTable nigdzie nie wywołuje `levelDot()`.
- **Wniosek:** Martwy import — do usunięcia.

### 12. `clear-step-data` emit zadeklarowany, ale nigdy nie emitowany
- **Plik:** `public/js/components/SetupWizard.js:13`
- **Problem:** SetupWizard deklaruje emit `'clear-step-data'`, VApp nasłuchuje na `@clear-step-data`, ale **żaden przycisk ani event w SetupWizard nigdy nie emituje tego zdarzenia**. Czyszczenie `setupStepData` odbywa się bezpośrednio w `proceedStep()` w store.js.
- **Wniosek:** Martwy emit + handler — do usunięcia z emits i z VApp.

### 13. `filesApiUrl` eksportowana, ale używana tylko wewnętrznie
- **Plik:** `public/js/store.js:827`
- **Problem:** `filesApiUrl` jest dodawana do `window.FPLV` via `Object.assign`, ale nigdy nie jest wywoływana z zewnątrz store.js (żaden komponent jej nie używa).
- **Wniosek:** Nie trzeba eksportować — usunąć z eksportu dla porządku.

---

## JavaScript — duplikacja kodu

### 14. Reset `sshForm` powtórzony 3× (identyczny obiekt)
- **Plik:** `public/js/store.js` — linie 70-74 (inicjalizacja), 635-638, 661-664
- **Problem:** Identyczny obiekt resetu SSH formularza jest zapisany w 3 miejscach. Zmiana jednego pola wymaga edycji 3 miejsc.
- **Wniosek:** Wyekstrahować do stałej `const SSH_FORM_DEFAULTS = {...}` i używać `Object.assign(store.sshForm, SSH_FORM_DEFAULTS)`.

### 15. `Object.keys(store.expanded).forEach(k => delete store.expanded[k])` powtórzony 3×
- **Plik:** `public/js/store.js` — linie 333, 376, 566
- **Wniosek:** Wyekstrahować do funkcji `clearExpanded()`.

### 16. `Object.keys(store.setupStepData).forEach(k => delete store.setupStepData[k])` powtórzony 3×
- **Plik:** `public/js/store.js` — linie 483, 501; `public/js/components/VApp.js` — linia 20
- **Wniosek:** Wyekstrahować do funkcji `clearSetupStepData()`.

### 17. Reset `connectingConnectionIndex / passwordForConnection` powtórzony 5×
- **Plik:** `public/js/store.js` — linie 680-681, 717-718, 724-725, 784-785, 791-792
- **Wniosek:** Wyekstrahować do funkcji `resetConnectionState()`.

---

## Inne

### 18. `.phpunit.result.cache` śledzony w repozytorium
- **Plik:** `.phpunit.result.cache`
- **Problem:** Plik cache PHPUnit nie powinien być commitowany.
- **Wniosek:** Dodać do `.gitignore` i usunąć z repo.

### 19. `RemoteLogFinder` nie implementuje `LogFinderInterface`
- **Plik:** `src/Service/RemoteLogFinder.php:10`
- **Problem:** `GlobLogFinder implements LogFinderInterface`, ale `RemoteLogFinder` tego nie robi, mimo że oba mają metodę `findAll()`. Brak polimorfizmu.
- **Wniosek:** Rozważ implementację interfejsu lub stworzenie dedykowanego `RemoteLogFinderInterface`.

---

## Podsumowanie

| Kategoria | Ilość |
|---|---|
| Martwy kod PHP (metody/stałe nigdy nie wywoływane w produkcji) | 7 |
| Martwy kod JS (nieużywane importy/emity) | 3 |
| Duplikacja kodu PHP | 2 |
| Duplikacja kodu JS | 4 |
| Inne (cache, design) | 2 |
| **Razem** | **18** |
