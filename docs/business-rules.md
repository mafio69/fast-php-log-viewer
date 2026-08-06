# Dokument wymagań biznesowych: Sposoby wskazywania logów do przeglądania

## Wprowadzenie

Wcześniejsze ustalenia dotyczące wymagań biznesowych aplikacji się rozjechały (za dużo naraz).
Poniżej ZAWĘŻONY, ostateczny zakres ustalony 2026-08-05: cztery niezależne, równoległe metody
wskazania aplikacji jakie logi ma przeglądać. Ten dokument jest źródłem prawdy dla wymagań — audyt
stanu (co zrobione / co brakuje) jest częścią każdego wymagania, żeby dokument nie stał się kolejną
listą pobożnych życzeń oderwaną od kodu.

## Słownik

- **Host path**: ścieżka na hoście widoczna wewnątrz kontenera aplikacji przez zamapowany wolumen.
- **Allowed container**: kontener Docker jawnie dodany do białej listy, którego pliki można czytać
  przez `docker exec` bez mapowania wolumenu.
- **SSH profile**: zestaw parametrów połączenia SSH (host, user, metoda auth, ścieżka klucza)
  zapisany jako źródło logów.
- **Prywatna sesja**: (jeszcze nie istnieje) sesja użytkownika po zalogowaniu, w ramach której jego
  dane SSH są odizolowane od innych użytkowników tej samej instancji.

---

## Wymaganie 1: Wklejenie ścieżki (host, wolumen)

**User Story:** Jako użytkownik, chcę wkleić ścieżkę do pliku/katalogu w widocznym polu, żeby
aplikacja od razu zaczęła go przeglądać — pod warunkiem że ta ścieżka jest zamapowana jako wolumen
do kontenera aplikacji. To podstawowy wymóg, ale trudny: przed tym rozwiązaniem nie wymyślono nic
poza samym mapowaniem wolumenu do kontenera.

### Kryteria akceptacji

1. WHEN użytkownik wkleja ścieżkę w polu dodawania katalogu, THE `DirectoryController` SHALL
   zwalidować i zapisać ją przez `LogConfig::addDirectory`.
2. IF ścieżka nie istnieje wewnątrz kontenera (bo nie jest zamapowana jako wolumen), THEN aplikacja
   SHALL zwrócić czytelny błąd tłumaczący że trzeba dodać wolumen w `docker-compose.yml`, a nie
   generyczny błąd systemowy.

### Status: ZROBIONE

`DirectoryController::add/update/delete` + `LogConfig::addDirectory` już to obsługują. Ograniczenie
"musi być w wolumenie kontenera" jest świadome, nie jest błędem.

---

## Wymaganie 2: ID/nazwa kontenera + ścieżka wewnątrz niego

**User Story:** Jako użytkownik, chcę podać ID albo nazwę innego kontenera Docker oraz ścieżkę do
pliku na nim, żeby przeglądać logi bez konieczności mapowania wolumenu — aplikacja sama odczyta
plik przez `docker exec`.

### Kryteria akceptacji

1. WHEN użytkownik dodaje kontener przez `AllowedContainerController::add`, THE system SHALL
   zwalidować identyfikator regexem i zapisać go jako dozwolony.
2. WHEN żądany jest odczyt pliku z dozwolonego kontenera, THE `DockerLogSourceCollector` SHALL użyć
   `DockerExecService` do odczytu bez wymogu zamapowanego wolumenu.

### Status: ZROBIONE

`AllowedContainerController`, `DockerDirectoryReader`, `DockerLogSourceCollector`,
`DockerExecService` — kompletna ścieżka od walidacji ID kontenera do odczytu pliku.

---

## Wymaganie 3: SSH — połączenie zdalne, zapis parametrów, opcjonalna sesja prywatna

**User Story:** Jako użytkownik, chcę podać parametry połączenia SSH do zdalnego hosta, żeby
przeglądać tam logi. Parametry mają być zapisywane do bazy. Jeśli się zaloguję, chcę mieć prywatną
sesję, w której moje dane SSH nie są widoczne dla innych użytkowników tej samej instancji aplikacji
— i aplikacja ma mi jasno powiedzieć, że to logowanie służy wyłącznie do prywatnego przechowania
danych SSH, nie jest ogólnym systemem kont.

### Kryteria akceptacji

1. WHEN użytkownik testuje połączenie SSH, THE `SSHController::testConnection` SHALL zweryfikować
   dostęp bez trwałego zapisu hasła w bazie.
2. WHEN parametry SSH są zapisywane jako źródło logów, THE system SHALL przechować
   host/user/metodę auth/ścieżkę klucza w `log_directories` — BEZ hasła w tej tabeli.
3. IF użytkownik jest zalogowany (ma prywatną sesję), THEN jego konfiguracje SSH SHALL być widoczne
   wyłącznie jemu, odizolowane od innych sesji/użytkowników.
4. WHEN prezentowany jest formularz logowania, THE UI SHALL jawnie poinformować użytkownika, że
   login służy wyłącznie do prywatnego przechowywania danych SSH, nie jest ogólnym systemem kont.

### Status: CZĘŚCIOWO ZROBIONE — tu jest największa luka

Samo połączenie/odczyt SSH działa: `SSHController`, `SshDirectoryReader`, `SshFileReader`,
`SshLogSourceCollector`. Hasło faktycznie NIE trafia do `log_directories` (zweryfikowane w schemacie
— tabela ma `ssh_host`, `ssh_user`, `ssh_auth_method`, `ssh_key_path`, brak kolumny na hasło).

BRAKUJE CAŁKOWICIE: jakikolwiek system logowania/sesji użytkownika. Baza SQLite jest jedna,
globalna, współdzielona przez wszystkich — bez `user_id`, bez izolacji sesji. Kryteria 3 i 4 nie są
spełnione, bo nie ma z czego ich spełnić (brak koncepcji "zalogowany użytkownik" w całej aplikacji).

---

## Wymaganie 4: Domyślne lokalizacje

**User Story:** Jako użytkownik, chcę żeby aplikacja od razu, bez żadnej konfiguracji, pokazywała
pliki z kilku znanych, bezpiecznych domyślnych lokalizacji.

### Kryteria akceptacji

1. THE `DefaultLogSources::DEFAULTS` SHALL zawierać dokładnie: `/var/log` (na razie tylko pliki
   bezpośrednio w katalogu, bez rekursji), `~/logs` (katalog `logs` w HOME), `./logs` (katalog
   `logs` w rootcie repo/aplikacji).
2. THE system SHALL NOT domyślnie skanować `/tmp` ani innych lokalizacji spoza tej listy.

### Status: ZROBIONE

`DefaultLogSources::DEFAULTS = ['/var/log', '~/logs', './logs']`, potwierdzone testami
(`DefaultLogSourcesTest` — w tym jawny test że `/tmp` NIE jest w defaultach).

---

## Podsumowanie audytu

3 z 4 metod są kompletne i przetestowane. Brakuje wyłącznie systemu logowania + prywatnych sesji
(Wymaganie 3, kryteria 3–4) — to jedyna realna luka względem tego dokumentu, reszta to dopracowanie
istniejących mechanizmów, nie nowa funkcjonalność od zera.
