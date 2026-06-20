# Dokument wymagań: System konfiguracji aplikacji (app-configuration-setup)

## Wprowadzenie

Funkcjonalność wprowadza centralny system konfiguracji po stronie serwera dla fast-php-log-viewer.
Świeży użytkownik pobierający aplikację po raz pierwszy przechodzi przez wizard pierwszego uruchomienia,
który automatycznie generuje dane (klucz szyfrowania, unikalny ID instalacji) i pyta o parametry
opcjonalne (połączenie SSH, ścieżka klucza SSH). Użytkownik może pominąć każdy krok — aplikacja
informuje go wtedy o konsekwencjach danego wyboru. Konfiguracja jest trwale przechowywana w pliku
`data/app_config.json` po stronie serwera, a połączenia SSH migrują z localStorage przeglądarki
do serwera.

## Słownik

- **ConfigManager**: Klasa PHP odpowiedzialna za odczyt, zapis i walidację `app_config.json`.
- **SetupWizard**: Kontroler PHP obsługujący endpoint API wizarda pierwszego uruchomienia.
- **AppConfig**: Obiekt konfiguracyjny przechowywany w `data/app_config.json`.
- **InstallationId**: Unikalny identyfikator instalacji generowany jednorazowo podczas pierwszego uruchomienia.
- **EncryptionKey**: Klucz AES-256 generowany automatycznie, używany do szyfrowania backupu.
- **SSHProfile**: Zestaw parametrów połączenia SSH (host, użytkownik, metoda auth, ścieżka klucza) przechowywany po
  stronie serwera.
- **WizardStep**: Pojedynczy krok wizarda (np. krok SSH, krok klucza szyfrowania).
- **SetupState**: Stan procesu konfiguracji — `not_started`, `in_progress`, `complete`, `skipped`.

---

## Wymagania

### Wymaganie 1: Detekcja pierwszego uruchomienia

**User Story:** Jako nowy użytkownik uruchamiający aplikację po raz pierwszy, chcę aby aplikacja
automatycznie wykryła brak konfiguracji i poinformowała mnie o konieczności przeprowadzenia
konfiguracji, żeby nie musieć ręcznie szukać pliku konfiguracyjnego.

#### Kryteria akceptacji

1. WHEN aplikacja odbiera żądanie HTTP, THE ConfigManager SHALL sprawdzić czy plik `data/app_config.json` istnieje i
   zawiera pole `setup_complete: true`.
2. WHEN plik `data/app_config.json` nie istnieje, THE SetupWizard SHALL zwrócić w odpowiedzi API flagę
   `{"setup_required": true}`.
3. WHEN plik `data/app_config.json` istnieje lecz nie zawiera pola `setup_complete: true`, THE SetupWizard SHALL zwrócić
   flagę `{"setup_required": true, "setup_state": "in_progress"}`.
4. THE ConfigManager SHALL zablokować dostęp do endpointów logów (action=directories, action=files, action=entries)
   dopóki `setup_complete` nie wynosi `true`, zwracając HTTP 503 z treścią `{"error": "setup_required"}`.
5. WHERE aplikacja jest uruchamiana w trybie Docker, THE ConfigManager SHALL wczytać zmienne środowiskowe z pliku `.env`
   przed sprawdzeniem konfiguracji.

---

### Wymaganie 2: Automatyczne generowanie danych instalacji

**User Story:** Jako nowy użytkownik, chcę aby aplikacja automatycznie wygenerowała unikalny
identyfikator instalacji i klucz szyfrowania, żeby nie musieć samodzielnie tworzyć kryptograficznie
bezpiecznych wartości.

#### Kryteria akceptacji

1. WHEN SetupWizard inicjuje konfigurację, THE ConfigManager SHALL wygenerować `InstallationId` jako UUID v4 przy użyciu
   `random_bytes(16)`.
2. WHEN SetupWizard inicjuje konfigurację, THE ConfigManager SHALL wygenerować `EncryptionKey` jako 32-bajtowy losowy
   ciąg zakodowany jako hex (64 znaki) przy użyciu `random_bytes(32)`.
3. THE ConfigManager SHALL zapisać wygenerowany `EncryptionKey` do pliku `.env` pod kluczem `BACKUP_ENCRYPTION_KEY`,
   nadpisując istniejącą wartość jeśli jest pusta lub nieobecna.
4. THE ConfigManager SHALL zapisać wygenerowany `InstallationId` w polu `installation_id` pliku `data/app_config.json`.
5. IF plik `.env` nie jest zapisywalny, THEN THE ConfigManager SHALL zwrócić błąd
   `{"error": "env_not_writable", "message": "Plik .env nie jest zapisywalny — EncryptionKey nie został zapisany"}` i
   nie przerywać dalszej konfiguracji.
6. THE ConfigManager SHALL ustawić uprawnienia pliku `data/app_config.json` na `0600` po zapisaniu.

---

### Wymaganie 3: Wizard pierwszego uruchomienia — przepływ kroków

**User Story:** Jako nowy użytkownik, chcę przejść przez kolejne kroki konfiguracji w interfejsie
webowym, żebym wiedział co konfiguruję i mógł pominąć elementy których nie potrzebuję.

#### Kryteria akceptacji

1. THE SetupWizard SHALL udostępniać endpoint `?action=setup-status` (GET) zwracający aktualny `SetupState` oraz listę
   kroków z ich statusem (`pending`, `complete`, `skipped`).
2. THE SetupWizard SHALL udostępniać endpoint `?action=setup-step` (POST) przyjmujący
   `{"step": "<nazwa_kroku>", "data": {...}, "skip": false}` i zwracający `{"success": true, "next_step": "<nazwa>"}`.
3. WHEN krok zawiera `"skip": true`, THE SetupWizard SHALL zapisać krok jako `skipped` i zwrócić komunikat ostrzeżenia
   opisujący co użytkownik traci.
4. THE SetupWizard SHALL obsłużyć następujące kroki w kolejności: `generate_keys`, `ssh_config`, `local_directories`,
   `finalize`.
5. WHEN wszystkie kroki mają status `complete` lub `skipped`, THE SetupWizard SHALL ustawić `setup_complete: true` w
   `AppConfig` i zwrócić `{"setup_complete": true}`.
6. IF żądanie do `?action=setup-step` zawiera nieznane pole w `data`, THEN THE SetupWizard SHALL zignorować nieznane
   pole i przetworzyć pozostałe znane pola.

---

### Wymaganie 4: Krok konfiguracji SSH

**User Story:** Jako użytkownik, chcę podać dane połączenia SSH w wizardzie, żeby aplikacja
mogła je zapisać po stronie serwera zamiast trzymać w localStorage przeglądarki.

#### Kryteria akceptacji

1. WHEN krok `ssh_config` jest przesyłany z `"skip": false`, THE SetupWizard SHALL wymagać pól `ssh_host` i `ssh_user` —
   brak któregokolwiek SHALL skutkować HTTP 400 z `{"error": "missing_fields", "fields": ["ssh_host"]}`.
2. WHEN krok `ssh_config` zawiera `ssh_auth_method: "key"` i niepuste `ssh_key_path`, THE SetupWizard SHALL sprawdzić
   czy plik pod podaną ścieżką istnieje w kontenerze i zwrócić ostrzeżenie `{"warning": "key_not_found"}` gdy plik nie
   istnieje (nie blokując zapisu).
3. THE ConfigManager SHALL zapisać `SSHProfile` w tablicy `ssh_profiles` w `data/app_config.json`, nigdy nie zapisując
   pola `ssh_password` ani `ssh_key_passphrase`.
4. WHEN krok `ssh_config` jest przesyłany z `"skip": true`, THE SetupWizard SHALL zapisać `{"ssh_enabled": false}` w
   `AppConfig` i zwrócić
   `{"warning": "ssh_disabled", "message": "Funkcja SSH jest wyłączona. Dostępne są tylko lokalne logi."}`.
5. WHERE `ssh_enabled` wynosi `false`, THE LogController SHALL wykluczyć z odpowiedzi endpointu `?action=directories`
   wszystkie katalogi z typem `ssh`.

---

### Wymaganie 5: Krok konfiguracji klucza szyfrowania backupu

**User Story:** Jako użytkownik, chcę zdecydować czy backup konfiguracji ma być szyfrowany,
żeby świadomie wybrać poziom bezpieczeństwa odpowiedni dla mojego środowiska.

#### Kryteria akceptacji

1. WHEN krok `generate_keys` jest przetwarzany, THE SetupWizard SHALL wyświetlić użytkownikowi wygenerowany
   `EncryptionKey` (hex) jednorazowo w odpowiedzi API — pole `{"encryption_key_display": "<hex>"}`.
2. WHEN krok `generate_keys` jest przesyłany z `"skip": false`, THE ConfigManager SHALL ustawić
   `{"backup_encryption_enabled": true}` w `AppConfig`.
3. WHEN krok `generate_keys` jest przesyłany z `"skip": true`, THE ConfigManager SHALL ustawić
   `{"backup_encryption_enabled": false}` w `AppConfig` i zapisać backup jako otwarty JSON (bez szyfrowania).
4. WHEN `backup_encryption_enabled` wynosi `false`, THE ConfigManager SHALL zapisywać plik `data/logviewer_backup.json`
   jako otwarty JSON z uprawnieniami `0600`.
5. WHEN krok `generate_keys` jest przesyłany z `"skip": true`, THE SetupWizard SHALL zwrócić
   `{"warning": "no_encryption", "message": "Backup konfiguracji nie będzie szyfrowany. Dane SSH (host, użytkownik) będą widoczne w pliku data/logviewer_backup.json."}`.

---

### Wymaganie 6: Persystencja konfiguracji po stronie serwera

**User Story:** Jako użytkownik, chcę aby moja konfiguracja była przechowywana po stronie serwera,
żeby nie ginęła przy czyszczeniu cache przeglądarki.

#### Kryteria akceptacji

1. THE ConfigManager SHALL przechowywać całą konfigurację aplikacji w pliku `data/app_config.json` w formacie JSON z
   wcięciami (JSON_PRETTY_PRINT).
2. THE ConfigManager SHALL udostępniać endpoint `?action=app-config` (GET) zwracający bieżącą konfigurację z
   wykluczeniem pól wrażliwych (`ssh_password`, `ssh_key_passphrase`, `encryption_key_raw`).
3. WHEN ConfigManager zapisuje `data/app_config.json`, THE ConfigManager SHALL walidować wynikowy JSON i wycofać zapis (
   zachować poprzednią wersję) jeśli JSON jest nieprawidłowy.
4. THE ConfigManager SHALL przechowywać `SSHProfile` (bez hasła) w `data/app_config.json`, tak by konfiguracja SSH
   przetrwała restart kontenera.
5. WHEN frontend Vue.js ładuje stronę, THE Frontend SHALL pobrać konfigurację z `?action=app-config` i wypełnić stan
   aplikacji danymi z serwera zamiast z localStorage.
6. THE ConfigManager SHALL udostępniać endpoint `?action=app-config` (POST) przyjmujący częściową aktualizację
   konfiguracji i zwracający `{"success": true}`.

---

### Wymaganie 7: Migracja połączeń SSH z localStorage

**User Story:** Jako dotychczasowy użytkownik, który ma już połączenia SSH zapisane w localStorage
przeglądarki, chcę aby aplikacja automatycznie zaproponowała ich migrację do konfiguracji serwerowej,
żebym nie musiał konfigurować ich od nowa.

#### Kryteria akceptacji

1. THE SetupWizard SHALL udostępniać endpoint `?action=setup-migrate-ssh` (POST) przyjmujący tablicę połączeń SSH z
   localStorage i zapisujący je jako `SSHProfile` w `AppConfig`.
2. WHEN `?action=setup-migrate-ssh` przetwarza połączenie zawierające `keyPath` wskazujące na ścieżkę hosta (np.
   `/home/user/.ssh/id_rsa`), THE SetupWizard SHALL zapisać oryginalne pole jako `ssh_key_path_original` i ustawić pole
   `ssh_key_path_warning: true` sygnalizując że ścieżka może być nieprawidłowa w kontenerze.
3. WHEN migracja SSH zakończy się powodzeniem, THE SetupWizard SHALL zwrócić `{"migrated": <liczba>, "warnings": [...]}`
   gdzie `warnings` zawiera listę połączeń z polem `ssh_key_path_warning: true`.
4. THE Frontend SHALL po pomyślnej migracji usunąć klucz `fplv_ssh_connections` z localStorage.
5. IF tablica wejściowa migracji jest pusta, THEN THE SetupWizard SHALL zwrócić `{"migrated": 0, "warnings": []}` bez
   błędu.

---

### Wymaganie 8: Informowanie użytkownika o konsekwencjach pominięcia kroków

**User Story:** Jako użytkownik, chcę być poinformowany co tracę pomijając dany krok konfiguracji,
żeby podjąć świadomą decyzję.

#### Kryteria akceptacji

1. WHEN użytkownik przesyła krok z `"skip": true`, THE SetupWizard SHALL zawsze zwrócić pole `warning` z komunikatem w
   języku zrozumiałym dla użytkownika.
2. THE SetupWizard SHALL zwrócić następujące ostrzeżenia dla poszczególnych pominiętych kroków:
    - `generate_keys`:
      `"Backup konfiguracji nie będzie szyfrowany — plik logviewer_backup.json będzie czytelny jako plain text."`
    - `ssh_config`:
      `"Funkcja SSH jest wyłączona. Aby przeglądać logi zdalne, skonfiguruj SSH w ustawieniach aplikacji."`
    - `local_directories`:
      `"Brak skonfigurowanych katalogów lokalnych. Aplikacja nie wyświetli żadnych plików logów do czasu ręcznej konfiguracji."`
3. THE Frontend SHALL wyświetlić komunikat ostrzegawczy w interfejsie przed przejściem do następnego kroku gdy
   użytkownik wybrał pominięcie.
4. THE Frontend SHALL wymagać jawnego potwierdzenia pominięcia (kliknięcie przycisku "Rozumiem, pomiń") zamiast
   akceptować pominięcie jednym kliknięciem.

---

### Wymaganie 9: Bezpieczeństwo pliku konfiguracyjnego

**User Story:** Jako administrator systemu, chcę aby plik konfiguracyjny był odpowiednio
zabezpieczony, żeby nieautoryzowane osoby nie miały dostępu do danych SSH.

#### Kryteria akceptacji

1. THE ConfigManager SHALL ustawić uprawnienia `0600` na pliku `data/app_config.json` po każdym zapisie.
2. THE ConfigManager SHALL nigdy nie zapisywać pól `ssh_password` i `ssh_key_passphrase` w `data/app_config.json`.
3. WHEN endpointy API zwracają konfigurację, THE ConfigManager SHALL filtrować pola `ssh_password`, `ssh_key_passphrase`
   i `encryption_key_raw` z odpowiedzi JSON.
4. THE LogController SHALL odrzucić żądanie HTTP ze statusem 403 gdy ścieżka pliku podana w parametrze `file` wskazuje
   poza skonfigurowane katalogi logów, niezależnie od stanu konfiguracji.
5. IF plik `data/app_config.json` ma uprawnienia szersze niż `0640`, THEN THE ConfigManager SHALL zalogować ostrzeżenie
   do `data/php_errors.log` przy każdym wczytaniu konfiguracji.

