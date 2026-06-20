# Implementation Plan: System konfiguracji aplikacji (app-configuration-setup)

## Overview

Implementacja centralnego systemu konfiguracji `fast-php-log-viewer` opartego na Slim Framework 4
z PHP-DI, nowym `ConfigManager`, wizard pierwszego uruchomienia oraz migracją SSH z localStorage.
Architektura zastępuje globalny `match()` w `LogController.php` czystymi kontrolerami Slim z DI.

---

## Tasks

### 1. Infrastruktura — zależności i autoloading

- [ ] 1.1 Zaktualizuj `composer.json` o nowe zależności
    - Dodaj do sekcji `require`: `"slim/slim": "^4.12"`, `"slim/psr7": "^1.7"`, `"php-di/php-di": "^7.0"`,
      `"giorgiosironi/eris": "^0.14"` (w `require-dev`)
    - Dodaj namespace `"Mariusz\\LogViewer\\Middleware\\"` → `"src/Middleware/"` do `autoload.psr-4`
    - Dodaj namespace `"Mariusz\\LogViewer\\Bootstrap\\"` → `"src/Bootstrap/"` do `autoload.psr-4`
    - Uruchom `composer install` (lub zaktualizuj `composer.lock`)
    - _Wymagania: wszystkie wymagania (prerequisite)_
    - Uruchom wszystkie testy (`vendor/bin/phpunit`) i wykonaj commit jeśli przejdą

- [ ] 1.2 Utwórz katalogi struktury projektu
    - Utwórz katalog `src/Middleware/` (plik `.gitkeep` jeśli pusty)
    - Utwórz katalog `src/Bootstrap/`
    - Utwórz katalog `tests/Config/`
    - Utwórz katalog `tests/Service/`
    - Utwórz katalog `tests/Middleware/`
    - Utwórz katalog `tests/Controller/`
    - _Wymagania: wszystkie wymagania (prerequisite)_
    - Uruchom wszystkie testy (`vendor/bin/phpunit`) i wykonaj commit jeśli przejdą

### 2. Backend — ConfigManager

- [ ] 2.1 Zaimplementuj klasę `src/Config/ConfigManager.php`
    - Plik: `src/Config/ConfigManager.php`, namespace `Mariusz\LogViewer\Config`
    - Konstruktor: `__construct(private readonly string $configPath, private readonly string $envPath)`
      gdzie `$configPath = DATA_DIR . '/app_config.json'`, `$envPath = ROOT_DIR . '/.env'`
    - Zaimplementuj metodę `isSetupComplete(): bool` — zwraca `true` wtedy i tylko wtedy, gdy plik
      istnieje, jest prawidłowym JSON i zawiera `setup_complete === true` (strict); dla wszystkich
      innych przypadków (brak pliku, `false`, `null`, brak klucza, uszkodzony JSON) zwraca `false`
    - Zaimplementuj `getSetupStatus(): array` — zwraca `{state, steps[{name, status}]}`
    - Zaimplementuj `getSetupState(): string` — zwraca `not_started|in_progress|complete|skipped`
    - Zaimplementuj `markSetupComplete(): void` — ustawia `setup_complete: true`, `setup_state: complete`
    - _Wymagania: 1.1, 1.2, 1.3, 1.4_
    - Napisz testy jeśli potrzeba (2.3, 2.6); uruchom wszystkie testy (`vendor/bin/phpunit`) i wykonaj commit jeśli przejdą

- [ ] 2.2 Zaimplementuj generatory kluczy w `ConfigManager`
    - Metoda `generateInstallationId(): string` — UUID v4 przez `random_bytes(16)` z formatowaniem
      `xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx` (wersja 4 z bitem variant `[89ab]`)
    - Metoda `generateEncryptionKey(): string` — `bin2hex(random_bytes(32))` → 64 znaki lowercase hex
    - _Wymagania: 2.1, 2.2_
    - Napisz testy jeśli potrzeba (2.3); uruchom wszystkie testy (`vendor/bin/phpunit`) i wykonaj commit jeśli przejdą

- [ ]* 2.3 Napisz testy property-based dla generatorów kluczy

- Plik: `tests/Config/ConfigManagerPropertyTest.php`, użyj `Eris\TestTrait`
- **Property 2: Generated InstallationIds are valid UUID v4**
    - Generator: wywołaj `generateInstallationId()` wielokrotnie (min 100x przez eris)
    - Asercja: każdy wynik pasuje do `/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i`
    - Asercja: żadne dwa ID nie są identyczne
    - **Validates: Requirements 2.1**
- **Property 3: Generated EncryptionKeys are 64-char hex**
    - Generator: wywołaj `generateEncryptionKey()` wielokrotnie
    - Asercja: `strlen($key) === 64` i `preg_match('/^[0-9a-f]{64}$/', $key) === 1`
    - Asercja: żadne dwa klucze nie są identyczne
    - **Validates: Requirements 2.2**


- [ ] 2.4 Zaimplementuj odczyt/zapis konfiguracji w `ConfigManager`
    - Metoda `getConfig(): array` — wczytuje `app_config.json`; jeśli plik nie istnieje lub JSON
      uszkodzony, zwraca pustą tablicę domyślną i loguje błąd (nie rzuca wyjątku)
    - Metoda `getPublicConfig(): array` — wywołuje `getConfig()` a następnie `filterSensitiveFields()`
    - Metoda `saveConfig(array $config): void` — wywołuje `validateAndWriteJson()` (atomowy zapis:
      zapisz do `.tmp.{random}`, zweryfikuj JSON, `rename()`, `chmod(0600)`)
    - Metoda `updateConfig(array $partial): void` — merge z `getConfig()`, następnie `saveConfig()`
    - Prywatna `validateAndWriteJson(string $path, array $data): void` — patrz design.md sekcja
      "Atomowy zapis JSON": tmp-file + verify + rename + chmod 0600
    - Prywatna `filterSensitiveFields(array $data): array` — rekursywnie usuwa klucze:
      `ssh_password`, `ssh_key_passphrase`, `encryption_key_raw`
    - Metoda `checkFilePermissions(): void` — jeśli perms > 0640, loguje ostrzeżenie do
      `data/php_errors.log`
    - _Wymagania: 6.1, 6.2, 6.3, 9.1, 9.2, 9.3, 9.5_
    - Napisz testy jeśli potrzeba (2.5, 2.8); uruchom wszystkie testy (`vendor/bin/phpunit`) i wykonaj commit jeśli przejdą

- [ ]* 2.5 Napisz testy property-based dla round-trip serializacji i uprawnień

- Plik: `tests/Config/ConfigManagerPropertyTest.php`
- **Property 4: Config serialization is reversible**
    - Generator eris: losowe tablice PHP (różne kombinacje pól konfiguracyjnych bez pól wrażliwych)
    - Asercja: `getConfig()` po `saveConfig($data)` zwraca strukturę równoważną `$data`
    - Asercja: plik JSON jest prawidłowy i ma wcięcia (`JSON_PRETTY_PRINT`)
    - **Validates: Requirements 6.1, 6.3**
- **Property 5: Sensitive fields never leave the system**
    - Generator eris: tablice z dowolnymi wartościami kluczy `ssh_password`, `ssh_key_passphrase`,
      `encryption_key_raw`
    - Asercja: po `saveConfig()` plik `app_config.json` nie zawiera żadnego z tych kluczy
    - Asercja: `getPublicConfig()` nie zawiera żadnego z tych kluczy
    - **Validates: Requirements 4.3, 6.2, 9.2, 9.3**
- **Property 6: Every config save sets 0600 permissions**
    - Generator eris: różne zawartości tablicy konfiguracyjnej
    - Asercja: po każdym `saveConfig()` plik ma uprawnienia `0600` (sprawdź przez `fileperms()`)
    - **Validates: Requirements 2.6, 9.1**

- [ ]* 2.6 Napisz testy property-based dla detekcji stanu setupu

- Plik: `tests/Config/ConfigManagerPropertyTest.php`
- **Property 1: Setup detection is always correct**
    - Generator eris: różne zawartości pliku `app_config.json` (brak pliku, brak klucza,
      `false`, `null`, losowe wartości, prawidłowe `true`)
    - Asercja: `isSetupComplete()` zwraca `true` tylko gdy plik istnieje, JSON prawidłowy
      i `setup_complete === true`
    - **Validates: Requirements 1.1, 1.2, 1.3, 1.4**


- [ ] 2.7 Zaimplementuj obsługę SSH Profiles i klucza szyfrowania w `ConfigManager`
    - Metoda `saveSSHProfile(array $profileData): void` — wywołuje `filterSensitiveFields()` przed
      zapisem; zapisuje profil w tablicy `ssh_profiles` w `app_config.json` z ID `profile_N`
    - Metoda `getSSHProfiles(): array` — zwraca `ssh_profiles` bez pól wrażliwych
    - Metoda `isSshEnabled(): bool` — zwraca `ssh_enabled` z konfiguracji (domyślnie `true`)
    - Metoda `saveEncryptionKeyToEnv(string $hexKey): bool` — nadpisuje/dodaje
      `BACKUP_ENCRYPTION_KEY=<hex>` w pliku `.env`; zwraca `false` gdy plik niezapisywalny
    - Metoda `exportBackup(): void` — deleguje do istniejącej logiki `LogConfig::exportBackup()`
      lub implementuje nową wersję zapisującą `data/logviewer_backup.json` z uprawnieniami 0600
    - _Wymagania: 4.3, 5.1, 5.2, 5.3, 5.4, 6.4, 9.2_
    - Napisz testy jeśli potrzeba (2.8); uruchom wszystkie testy (`vendor/bin/phpunit`) i wykonaj commit jeśli przejdą

- [ ]* 2.8 Napisz testy jednostkowe dla `ConfigManager` (edge cases)

- Plik: `tests/Config/ConfigManagerTest.php`
- `testIsSetupCompleteReturnsFalseWhenFileDoesNotExist()`
- `testSaveConfigCreatesFileWithCorrectPermissions()`
- `testGetPublicConfigFiltersSensitiveFields()`
- `testSaveConfigWithInvalidDataKeepsPreviousVersion()`
- `testCheckFilePermissionsLogsWarningWhenPermissionsAreTooOpen()`
- `testSaveEncryptionKeyToEnvWritesKeyToFile()`
- `testSaveEncryptionKeyToEnvReturnsFalseWhenEnvNotWritable()`
- `testSaveSSHProfileExcludesPasswordFields()`
- _Wymagania: 2.5, 2.6, 4.3, 6.2, 9.1, 9.2, 9.5_

- [ ] 
    3. Checkpoint — testy ConfigManager

    - Upewnij się, że wszystkie testy `ConfigManagerTest` i `ConfigManagerPropertyTest` przechodzą.
      Zapytaj użytkownika jeśli pojawią się wątpliwości dotyczące zachowania `isSetupComplete()`.

### 4. Backend — SetupWizard

- [ ] 4.1 Zaimplementuj klasę `src/Service/SetupWizard.php`
    - Plik: `src/Service/SetupWizard.php`, namespace `Mariusz\LogViewer\Service`
    - Konstruktor: `__construct(private readonly ConfigManager $configManager, private readonly LogConfig $logConfig)`
    - Stała: `public const STEPS = ['generate_keys', 'ssh_config', 'local_directories', 'finalize']`
    - Metoda `getStatus(): array` — zwraca aktualny `SetupState` i listę kroków ze statusem
      `pending|complete|skipped`; odpowiada wymaganiu endpointu `GET /api/setup/status`
    - Metoda `getNextStep(string $currentStep): ?string` — zwraca następny krok z `STEPS` lub `null`
    - Metoda `getSkipWarning(string $step): string` — zwraca komunikat ostrzeżenia dla każdego kroku
      zgodnie z wymaganiem 8.2:
        - `generate_keys`: "Backup konfiguracji nie będzie szyfrowany..."
        - `ssh_config`: "Funkcja SSH jest wyłączona. Aby przeglądać logi zdalne..."
        - `local_directories`: "Brak skonfigurowanych katalogów lokalnych..."
        - `finalize`: odpowiedni komunikat dla kroku finalizacji
    - _Wymagania: 3.1, 3.2, 3.4, 8.1, 8.2_
    - Napisz testy jeśli potrzeba (4.5); uruchom wszystkie testy (`vendor/bin/phpunit`) i wykonaj commit jeśli przejdą

- [ ] 4.2 Zaimplementuj logikę kroków wizarda w `SetupWizard`
    - Metoda `processStep(string $step, array $data, bool $skip): array` — dispatcher do prywatnych
      metod `processGenerateKeys()`, `processSSHConfig()`, `processLocalDirectories()`,
      `processFinalize()`; dla nieznanego kroku rzuca `\InvalidArgumentException`
    - Prywatna `processGenerateKeys(array $data, bool $skip): array`:
        - `skip=false`: wywołuje `configManager->generateInstallationId()`,
          `configManager->generateEncryptionKey()`, `configManager->saveEncryptionKeyToEnv()`,
          `configManager->saveConfig()` z `backup_encryption_enabled: true`; zwraca
          `{success, next_step, encryption_key_display: <hex>}`
        - `skip=true`: ustawia `backup_encryption_enabled: false`; zwraca
          `{success, warning: "...", next_step}`
    - Prywatna `processSSHConfig(array $data, bool $skip): array`:
        - `skip=false`: wywołuje `validateSSHFields()`; jeśli błąd — zwraca `{error, fields}`;
          sprawdza istnienie pliku klucza SSH; zapisuje profil przez `configManager->saveSSHProfile()`
        - `skip=true`: zapisuje `ssh_enabled: false`; zwraca `{success, warning}`
    - Prywatna `processLocalDirectories(array $data, bool $skip): array`:
        - `skip=false`: dodaje katalogi przez `logConfig->addDirectory()` dla każdego katalogu w `data`
        - `skip=true`: zwraca `{success, warning}`
    - Prywatna `processFinalize(array $data, bool $skip): array`:
        - Wywołuje `configManager->markSetupComplete()`; zwraca `{setup_complete: true}`
    - Prywatna `validateSSHFields(array $data): ?array` — zwraca `null` gdy `ssh_host` i `ssh_user`
      niepuste; inaczej `['fields' => ['ssh_host']]` lub `['fields' => ['ssh_user']]` lub obu
    - _Wymagania: 3.2, 3.3, 3.5, 4.1, 4.2, 4.3, 4.4, 5.1, 5.2, 5.3, 5.5, 8.1_
    - Napisz testy jeśli potrzeba (2.9, 4.4, 4.5); uruchom wszystkie testy (`vendor/bin/phpunit`) i wykonaj commit jeśli przejdą

- [ ] 4.3 Zaimplementuj migrację SSH w `SetupWizard`
    - Metoda `migrateSSHFromLocalStorage(array $connections): array` — iteruje po połączeniach,
      dla każdego: sprawdza pola `keyPath` (mapuje na `ssh_key_path_original`, `ssh_key_path_warning`),
      wywołuje `configManager->saveSSHProfile()`; zwraca `{migrated: <int>, warnings: [...]}`
    - Dla pustej tablicy zwraca `{migrated: 0, warnings: []}` (wymaganie 7.5)
    - _Wymagania: 7.1, 7.2, 7.3, 7.5_
    - Napisz testy jeśli potrzeba (4.5); uruchom wszystkie testy (`vendor/bin/phpunit`) i wykonaj commit jeśli przejdą

- [ ]* 2.9 Napisz testy property-based dla kroków wizarda

- Plik: `tests/Config/ConfigManagerPropertyTest.php`
- **Property 7: Every skipped step produces a warning**
    - Generator eris: każdy z 4 kroków, losowe `data`, zawsze `skip=true`
    - Asercja: wynik `processStep($step, $data, true)` zawiera klucz `warning` będący
      niepustym ciągiem
    - **Validates: Requirements 3.3, 8.1, 8.2**
- **Property 8: Setup is complete for any complete/skipped combination**
    - Generator eris: losowe kombinacje statusów `complete|skipped` dla 4 kroków
    - Asercja: `isSetupComplete()` zwraca `true` gdy każdy krok ma `complete` lub `skipped`
    - Asercja: `isSetupComplete()` zwraca `false` gdy jakikolwiek krok ma `pending`
    - **Validates: Requirements 3.4, 3.5**

- [ ]* 4.4 Napisz testy property-based dla walidacji SSH i filtrowania katalogów

- Plik: `tests/Config/ConfigManagerPropertyTest.php`
- **Property 9: SSH validation rejects data without required fields**
    - Generator eris: tablice bez klucza `ssh_host` lub bez `ssh_user` (lub obu)
    - Asercja: `validateSSHFields($data)` (testowana przez `processStep('ssh_config', ...)`)
      zwraca tablicę z brakującymi polami
    - Generator eris: tablice z niepustymi `ssh_host` i `ssh_user`
    - Asercja: walidacja przechodzi bez błędu
    - **Validates: Requirements 4.1**
- **Property 10: SSH directories filtered when ssh disabled**
    - Generator eris: losowe listy katalogów (mix `type=local` i `type=ssh`),
      `ssh_enabled` ustawiane losowo na `true` lub `false`
    - Asercja: gdy `ssh_enabled=false`, `getDirectories()` (przez LogController) zwraca
      tylko katalogi z `type != 'ssh'`
    - Asercja: gdy `ssh_enabled=true`, katalogi SSH są uwzględniane
    - **Validates: Requirements 4.5**

- [ ]* 4.5 Napisz testy jednostkowe dla `SetupWizard`

- Plik: `tests/Service/SetupWizardTest.php`
- `testProcessGenerateKeysReturnsEncryptionKeyDisplay()`
- `testProcessSSHConfigSkipSetsSSHDisabled()`
- `testProcessSSHConfigRequiresSshHostAndUser()`
- `testProcessSSHConfigWithKeyPathWarnsWhenKeyNotFound()`
- `testMigrateSSHFromLocalStorageReturnsCountAndWarnings()`
- `testMigrateSSHFromLocalStorageWithEmptyArrayReturnsZero()`
- `testGetNextStepReturnsCorrectSequence()`
- `testFinalizeMarksSetsupComplete()`
- `testSkipGenerateKeysReturnNoEncryptionWarning()`
- _Wymagania: 3.2, 3.3, 4.1, 4.4, 5.1, 5.5, 7.3, 7.5, 8.2_

- [ ] 
    5. Checkpoint — testy SetupWizard

    - Upewnij się, że wszystkie testy `SetupWizardTest` przechodzą.
      Zapytaj użytkownika jeśli kolejność kroków lub komunikaty ostrzeżeń wymagają weryfikacji.

### 6. Backend — Bootstrap Slim Framework 4

- [ ] 6.1 Utwórz plik `src/Bootstrap/container.php` z definicjami PHP-DI
    - Plik: `src/Bootstrap/container.php`
    - Zdefiniuj bindingi dla:
        - `ConfigManager::class` — singleton z `configPath = DATA_DIR . '/app_config.json'`
          i `envPath = ROOT_DIR . '/.env'`
        - `LogConfig::class` — singleton z domyślnym `dbPath`
        - `SetupWizard::class` — wstrzykuje `ConfigManager` i `LogConfig`
        - `SetupController::class` — wstrzykuje `SetupWizard`
        - `AppConfigController::class` — wstrzykuje `ConfigManager`
        - `LogController::class` (nowy Slim) — wstrzykuje `LogConfig` i `ConfigManager`
        - `DirectoryController::class` — wstrzykuje `LogConfig`
        - `SSHController::class` — brak zależności lub wstrzykuje `LogParser`, `RemoteLogFinder`
        - `SetupMiddleware::class` — wstrzykuje `ConfigManager`
    - Zdefiniuj stałe `ROOT_DIR` i `DATA_DIR` jako parametry kontenera
    - _Wymagania: prerequisite dla wszystkich kontrolerów_

- [ ] 6.2 Utwórz plik `src/Bootstrap/routes.php` z routingiem
    - Plik: `src/Bootstrap/routes.php`
    - Zarejestruj wszystkie trasy zgodnie z mapą z design.md:
        - `GET  /api/setup/status` → `[SetupController::class, 'getStatus']`
        - `POST /api/setup/step` → `[SetupController::class, 'postStep']`
        - `POST /api/setup/migrate-ssh` → `[SetupController::class, 'postMigrateSSH']`
        - `GET  /api/app-config` → `[AppConfigController::class, 'getConfig']`
        - `POST /api/app-config` → `[AppConfigController::class, 'patchConfig']`
        - `GET  /api/directories` → `[LogController::class, 'getDirectories']`
        - `GET  /api/files` → `[LogController::class, 'getFiles']`
        - `GET  /api/entries` → `[LogController::class, 'getEntries']`
        - `POST /api/config/directories` → `[DirectoryController::class, 'add']`
        - `PUT  /api/config/directories/{id}` → `[DirectoryController::class, 'update']`
        - `DELETE /api/config/directories/{id}` → `[DirectoryController::class, 'delete']`
        - `POST /api/config/cleanup-duplicates` → `[DirectoryController::class, 'cleanupDuplicates']`
        - `GET  /api/scan/directories` → `[DirectoryController::class, 'scanDirectories']`
        - `POST /api/ssh/test-connection` → `[SSHController::class, 'testConnection']`
        - `POST /api/ssh/list-files` → `[SSHController::class, 'listFiles']`
        - `POST /api/ssh/read-file` → `[SSHController::class, 'readFile']`
        - `POST /api/ssh/download-file` → `[SSHController::class, 'downloadFile']`
    - _Wymagania: wszystkie wymagania dot. API_

- [ ] 6.3 Utwórz plik `src/Bootstrap/app.php` z bootstrapem Slim App
    - Plik: `src/Bootstrap/app.php`
    - Zbuduj kontener: `ContainerBuilder` → `addDefinitions(container.php)` → `build()`
    - `AppFactory::setContainer($container); $app = AppFactory::create()`
    - Dodaj middleware: `$app->addBodyParsingMiddleware()`, `$app->addErrorMiddleware(false, true, true)`
      (logger do `data/php_errors.log`), `$app->add(SetupMiddleware::class)`
    - Zaincluduj `routes.php`; zwróć `$app`
    - _Wymagania: 1.4, prerequisite dla index.php_

### 7. Backend — SetupMiddleware

- [ ] 7.1 Zaimplementuj `src/Middleware/SetupMiddleware.php`
    - Plik: `src/Middleware/SetupMiddleware.php`, namespace `Mariusz\LogViewer\Middleware`
    - Implementuje `Psr\Http\Server\MiddlewareInterface`
    - Konstruktor: `__construct(private readonly ConfigManager $configManager)`
    - Stała: `private const PROTECTED_ROUTES = ['/api/directories', '/api/files', '/api/entries']`
    - Metoda `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`:
        - Pobierz `$path = $request->getUri()->getPath()`
        - Jeśli `in_array($path, self::PROTECTED_ROUTES)` i `!$this->configManager->isSetupComplete()`:
          zwróć odpowiedź HTTP 503 z treścią `{"error":"setup_required"}`
        - W przeciwnym razie: `return $handler->handle($request)`
    - _Wymagania: 1.4_

- [ ]* 7.2 Napisz testy jednostkowe dla `SetupMiddleware`

- Plik: `tests/Middleware/SetupMiddlewareTest.php`
- Użyj PSR-7 mock requests (Slim\Psr7 lub PHPUnit mocks)
- `testBlocksDirectoriesWhenSetupIncomplete()` — 503 dla `/api/directories`
- `testAllowsSetupEndpointsWithoutSetup()` — przepuszcza `/api/setup/status`
- `testAllowsDirectoriesWhenSetupComplete()` — 200 dla `/api/directories` gdy setup kompletny
- _Wymagania: 1.4_

### 8. Backend — Kontrolery Slim

- [ ] 8.1 Zaimplementuj `src/Controller/SetupController.php`
    - Plik: `src/Controller/SetupController.php`, namespace `Mariusz\LogViewer\Controller`
    - Konstruktor: `__construct(private readonly SetupWizard $wizard)`
    - Metoda `getStatus(Request $request, Response $response): Response`:
        - Wywołuje `$this->wizard->getStatus()`
        - Jeśli `setup_complete !== true`, doda `setup_required: true` do odpowiedzi
        - Zwróć JSON 200
    - Metoda `postStep(Request $request, Response $response): Response`:
        - Pobierz body przez `$request->getParsedBody()` (Slim parseuje JSON dzięki `addBodyParsingMiddleware`)
        - Wyciągnij `step`, `data`, `skip` (ignoruj nieznane pola — wymaganie 3.6)
        - Wywołaj `$this->wizard->processStep($step, $data, $skip)`
        - Dla nieznanego kroku zwróć HTTP 400 `{"error":"unknown_step"}`
        - Dla błędów walidacji SSH zwróć HTTP 400 `{"error":"missing_fields","fields":[...]}`
        - Zwróć JSON 200 z wynikiem
    - Metoda `postMigrateSSH(Request $request, Response $response): Response`:
        - Pobierz tablicę `connections` z body
        - Wywołaj `$this->wizard->migrateSSHFromLocalStorage($connections)`
        - Zwróć JSON 200
    - _Wymagania: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 7.1, 7.2, 7.3_

- [ ] 8.2 Zaimplementuj `src/Controller/AppConfigController.php`
    - Plik: `src/Controller/AppConfigController.php`, namespace `Mariusz\LogViewer\Controller`
    - Konstruktor: `__construct(private readonly ConfigManager $configManager)`
    - Metoda `getConfig(Request $request, Response $response): Response`:
        - Wywołuje `$this->configManager->getPublicConfig()`
        - Zwróć JSON 200
    - Metoda `patchConfig(Request $request, Response $response): Response`:
        - Pobierz body, wywołaj `$this->configManager->updateConfig($body)`
        - Zwróć JSON 200 `{"success":true}`
    - _Wymagania: 6.2, 6.5, 6.6_

- [ ] 8.3 Zaimplementuj nowy `src/Controller/LogController.php` (Slim class)
    - Zastąp obecny plik `src/Controller/LogController.php` (globalny `match()`) klasą Slim
    - Konstruktor: `__construct(private readonly LogConfig $logConfig, private readonly ConfigManager $configManager)`
    - Metoda `getDirectories(Request $request, Response $response): Response`:
        - Pobierz `$dirs = $this->logConfig->getDirectories()`
        - Jeśli `!$this->configManager->isSshEnabled()`, odfiltruj katalogi z `type === 'ssh'`
        - Mapuj na format `{key, path}` (key = name)
        - Zwróć JSON 200
    - Metoda `getFiles(Request $request, Response $response): Response`:
        - Pobierz `dir` z query params, rozwiąż katalog
        - Użyj `LogFinder` do znalezienia plików
        - Zwróć JSON z polami `{file, date, size}`
    - Metoda `getEntries(Request $request, Response $response): Response`:
        - Pobierz `file` z query params
        - Waliduj ścieżkę przez `realpath()` + sprawdzenie prefiksu dozwolonych katalogów
        - Dla ścieżki poza katalogami: HTTP 403 `{"error":"access_denied"}`
        - Parsuj przez `LogParser`, opcjonalnie filtruj po `level`
        - Zwróć JSON 200
    - _Wymagania: 1.4, 4.5, 9.4_


- [ ] 8.4 Zaimplementuj `src/Controller/DirectoryController.php`
    - Plik: `src/Controller/DirectoryController.php`, namespace `Mariusz\LogViewer\Controller`
    - Konstruktor: `__construct(private readonly LogConfig $logConfig)`
    - Metoda `add(Request $request, Response $response): Response`:
        - Pobierz body (JSON z `name`, `path`, `type`)
        - Wywołaj `$this->logConfig->addDirectory($body)`; HTTP 400 gdy duplikat
        - Zwróć JSON `{success: true, id: N}`
    - Metoda `update(Request $request, Response $response, array $args): Response`:
        - Pobierz `$id` z `$args['id']`, pobierz body
        - Wywołaj `$this->logConfig->updateDirectory((int)$id, $body)`
        - Zwróć JSON `{success: true}`
    - Metoda `delete(Request $request, Response $response, array $args): Response`:
        - Pobierz `$id` z `$args['id']`
        - Wywołaj `$this->logConfig->deleteDirectory((int)$id)`
        - Zwróć JSON `{success: true}`
    - Metoda `cleanupDuplicates(Request $request, Response $response): Response`:
        - Wywołaj `$this->logConfig->removeDuplicates()`; zwróć `{success, removed}`
    - Metoda `scanDirectories(Request $request, Response $response): Response`:
        - Użyj `LogScanner::scanCommonDirectories()`; zwróć JSON
    - _Wymagania: 6.4_

- [ ] 8.5 Zaimplementuj `src/Controller/SSHController.php`
    - Plik: `src/Controller/SSHController.php`, namespace `Mariusz\LogViewer\Controller`
    - Konstruktor bez wymaganych zależności (tworzy `SSH` on-demand lub wstrzykuje factory)
    - Metoda `testConnection(Request $request, Response $response): Response`:
        - Waliduj body, sprawdź dostępność SSH2 extension
        - Utwórz `SSH($body)->connect()->disconnect()`; zwróć `{success:true}` lub HTTP 500
    - Metoda `listFiles(Request $request, Response $response): Response`:
        - Pobierz `path` z body; utwórz `SSH`, użyj `RemoteLogFinder`
        - Zwróć `{success:true, files:[...]}`
    - Metoda `readFile(Request $request, Response $response): Response`:
        - Pobierz `path`, odczytaj przez SSH, sparsuj przez `LogParser`
        - Zwróć `{success:true, entries:[...]}`
    - Metoda `downloadFile(Request $request, Response $response): Response`:
        - Przenieś logikę z `respondSSHDownloadFile()` z obecnego `LogController.php`
        - Zachowaj wszystkie walidacje bezpieczeństwa (rozmiar, binarne, suspicious content)
        - Zwróć `{success:true, localPath, size}`
    - _Wymagania: nie jest bezpośrednio nowym wymaganiem, ale wymagane do zachowania funkcjonalności_

- [ ]* 8.6 Napisz testy jednostkowe dla kontrolerów

- Plik: `tests/Controller/SetupControllerTest.php`
- Użyj PSR-7 ServerRequest z Slim\Psr7 lub PHPUnit mock
- `testGetStatusReturnsSetupRequired()`
- `testPostStepProcessesStep()`
- `testPostStepWithUnknownFieldsIgnoresThem()`
- `testPostMigrateSSHAcceptsConnections()`
- _Wymagania: 3.1, 3.2, 3.6, 7.1_

- [ ] 
    9. Checkpoint — kontrolery i middleware

    - Upewnij się, że wszystkie testy `SetupMiddlewareTest` i `SetupControllerTest` przechodzą.
      Zapytaj użytkownika jeśli zachowanie middleware dla nieznanych ścieżek wymaga wyjaśnienia.

### 10. Routing — przepisanie `public/index.php` + kompatybilność wsteczna

- [ ] 10.1 Przepisz `public/index.php` — bootstrap Slim zamiast require LogController
    - Plik: `public/index.php`
    - Usuń blok `if (isset($_GET['action'])) { require ... LogController.php; exit; }`
    - Dodaj na początku (przed HTML): bootstrap Slim App z `require __DIR__ . '/../src/Bootstrap/app.php'`
    - Zaimplementuj mapę aliasów `?action=` → nowe `/api/` URL-e (patrz design.md sekcja
      "Kompatybilność wsteczna"):
      ```php
      $legacyActionMap = [
          'directories'        => '/api/directories',
          'files'              => '/api/files',
          'entries'            => '/api/entries',
          'config-add-dir'     => '/api/config/directories',
          'ssh-test-connection'=> '/api/ssh/test-connection',
          'ssh-list-files'     => '/api/ssh/list-files',
          'ssh-read-file'      => '/api/ssh/read-file',
          'ssh-download-file'  => '/api/ssh/download-file',
          'setup-status'       => '/api/setup/status',
          'setup-step'         => '/api/setup/step',
          'setup-migrate-ssh'  => '/api/setup/migrate-ssh',
          'app-config'         => '/api/app-config',
      ];
      ```
    - Gdy `isset($_GET['action'])` i akcja jest w mapie: przepisz `REQUEST_URI` i uruchom Slim
    - Gdy brak `?action` lub URL zaczyna się od `/api/`: uruchom Slim i `$app->run()`; `exit`
    - Resztę pliku (HTML SPA) wyświetl tylko dla żądań bez `?action` i bez `/api/`
    - _Wymagania: wszystkie (prerequisite do działania aplikacji)_

### 11. Frontend — migracja URL-i i logika wizarda w `app.js`

- [ ] 11.1 Zaktualizuj URL-e API w `public/js/app.js` z `?action=X` na `/api/X`
    - Plik: `public/js/app.js`
    - Zamień wszystkie wywołania `fetch('?action=files', ...)` → `fetch('/api/files', ...)`
    - Zamień `?action=entries` → `/api/entries`
    - Zamień `?action=directories` → `/api/directories`
    - Zamień `?action=config-add-dir` → `/api/config/directories` (POST)
    - Zamień `?action=config-cleanup-duplicates` → `/api/config/cleanup-duplicates` (POST)
    - Zamień `?action=config-cleanup-allowed` → (usuń lub pomiń — brak odpowiednika w nowym API)
    - Zamień `?action=ssh-test-connection` → `/api/ssh/test-connection`
    - Zamień `?action=ssh-list-files` → `/api/ssh/list-files`
    - Zamień `?action=ssh-read-file` → `/api/ssh/read-file`
    - Zamień `?action=ssh-download-file` → `/api/ssh/download-file`
    - _Wymagania: 6.5_

- [ ] 11.2 Dodaj stan Vue dla wizarda i logikę sprawdzenia setupu w `init()`
    - Plik: `public/js/app.js`
    - Dodaj reaktywne zmienne w `setup()`:
      ```javascript
      const showSetupWizard = ref(false);
      const setupSteps = ref([]);
      const currentSetupStep = ref('');
      const setupSkipConfirm = ref(false);
      const setupStepData = reactive({});
      const setupWarning = ref('');
      const sshEnabled = ref(true);
      ```
    - Zmodyfikuj funkcję `init()`:
        - Na początku: `const status = await fetchJson('/api/setup/status')`
        - Jeśli `status.setup_required`: `showSetupWizard.value = true; return`
        - Po potwierdzeniu setupu: `const config = await fetchJson('/api/app-config')`
          i `sshEnabled.value = config.ssh_enabled ?? true`
        - Zastąp `fetchJson('?action=directories')` → `fetchJson('/api/directories')`
    - _Wymagania: 1.1, 1.2, 1.3, 6.5_

- [ ] 11.3 Dodaj funkcje wizarda i jednorazową migrację SSH w `app.js`
    - Plik: `public/js/app.js`
    - Dodaj funkcję `migrateSSHIfNeeded()`:
        - Sprawdź `localStorage.getItem('fplv_ssh_connections')`
        - Jeśli niepusty: wyślij POST `/api/setup/migrate-ssh` z połączeniami
        - Po sukcesie: `localStorage.removeItem('fplv_ssh_connections')`
        - Wypisz ostrzeżenia do `console.warn()`
    - Dodaj funkcję `proceedStep(skip: boolean)`:
        - POST `/api/setup/step` z `{step: currentSetupStep.value, data: setupStepData, skip}`
        - Obsłuż odpowiedź: jeśli `next_step` — przejdź do następnego kroku
        - Jeśli `setup_complete` — schowaj wizard, wołaj `init()`
        - Jeśli `warning` — pokaż w `setupWarning.value` przed przejściem
    - Wywołaj `migrateSSHIfNeeded()` przed pierwszym ładowaniem katalogów w `init()`
    - _Wymagania: 7.1, 7.4, 8.3, 8.4_

- [ ] 11.4 Dodaj HTML wizarda konfiguracji do `public/index.php` (SPA template)
    - Plik: `public/index.php` — sekcja HTML Vue app
    - Dodaj div wizarda `v-if="showSetupWizard"` przed głównym layoutem:
        - Krok `generate_keys`: przycisk "Generuj klucze" i "Pomiń" z potwierdzeniem
        - Krok `ssh_config`: formularz SSH (host, user, port, auth_method, key_path)
        - Krok `local_directories`: pole tekstowe ścieżki katalogu
        - Krok `finalize`: komunikat potwierdzający zakończenie konfiguracji
    - Dla każdego kroku: wyświetl `setupWarning` gdy niepusty
    - Przycisk "Pomiń" musi wymagać dwukrotnego kliknięcia: `setupSkipConfirm` → "Rozumiem, pomiń"
      (zgodnie z wymaganiem 8.4)
    - _Wymagania: 8.3, 8.4_

- [ ] 
    12. Checkpoint — integracja Slim + Frontend

    - Uruchom `vendor/bin/phpunit` — upewnij się że wszystkie istniejące testy nadal przechodzą.
      Sprawdź ręcznie endpoint `/api/setup/status` i `/api/directories` w przeglądarce.
      Zapytaj użytkownika w razie wątpliwości co do kompatybilności wstecznej `?action=`.

---

## Notes

- Zadania oznaczone `*` są opcjonalne — można je pominąć przy implementacji MVP
- Każde zadanie odwołuje się do konkretnych wymagań dla pełnej traceability
- Checkpointy zapewniają inkrementalną walidację w kluczowych punktach
- Testy property-based (PBT) używają biblioteki **eris** (`giorgiosironi/eris`), każda właściwość
  jest uruchamiana minimum 100 razy przez generator
- Stary `src/Controller/LogController.php` (plik z globalnymi funkcjami) jest **zastępowany** przez
  nową klasę Slim — przed usunięciem należy upewnić się, że wszystkie funkcje zostały przeniesione
- Kompatybilność wsteczna `?action=` jest zapewniona przez mapę aliasów w `public/index.php`
  i może być usunięta po pełnej migracji frontendu
- Klucz szyfrowania (`BACKUP_ENCRYPTION_KEY`) **nigdy** nie jest przechowywany w `app_config.json`
  — trafia wyłącznie do `.env` i jest wyświetlany jednorazowo przez endpoint `generate_keys`

---

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["2.1", "2.2"] },
    { "id": 2, "tasks": ["2.4", "2.3"] },
    { "id": 3, "tasks": ["2.7", "2.6", "2.5"] },
    { "id": 4, "tasks": ["2.8", "4.1"] },
    { "id": 5, "tasks": ["4.2", "2.9"] },
    { "id": 6, "tasks": ["4.3", "4.4", "4.5"] },
    { "id": 7, "tasks": ["6.1", "7.1"] },
    { "id": 8, "tasks": ["6.2", "7.2"] },
    { "id": 9, "tasks": ["6.3"] },
    { "id": 10, "tasks": ["8.1", "8.2", "8.3", "8.4", "8.5"] },
    { "id": 11, "tasks": ["8.6", "10.1"] },
    { "id": 12, "tasks": ["11.1", "11.2"] },
    { "id": 13, "tasks": ["11.3"] },
    { "id": 14, "tasks": ["11.4"] }
  ]
}
```
