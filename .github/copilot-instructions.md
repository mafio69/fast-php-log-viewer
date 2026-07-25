# Copilot review instructions — fast-php-log-viewer

Kontekst projektu: standalone log viewer (Slim 4 + PHP-DI, PHP >=8.4, SQLite/PDO), bez frontendowego
frameworka (czysty JS w `public/js/`, szablony w zwykłym PHP). Czyta logi z lokalnego filesystemu,
kontenerów Docker (`docker exec`) i zdalnych hostów po SSH (`ext-ssh2`).

## Zasady, których pilnuj przy review

- **Zero prawdziwych sekretów** w kodzie, configu, testach, komentarzach czy komunikatach wyjątków.
  Hasła/klucze SSH i `BACKUP_ENCRYPTION_KEY` mają pochodzić wyłącznie z env (`.env`, sekrety CI).
  Zgłoś jako High każdy string wyglądający jak realne hasło, token czy klucz API — mieliśmy tu już
  realny wyciek hasła SSH zahardkodowanego w teście, które trafiło do historii gita i wymagało
  rotacji na serwerze.
- **Kontrola dostępu do plików/kontenerów jest tu główną powierzchnią ataku.**
  `FileAccessValidator` i `DockerExecService` pilnują allow-listy dozwolonych katalogów/kontenerów
  i prefiksów ścieżek. Każda zmiana w tych klasach (albo w miejscach, które z nich korzystają)
  musi zachować: kanonicalizację ścieżki (`realpath`/normalizacja `..`) PRZED porównaniem z
  allow-listą, oraz fail-closed przy pustej/brakującej konfiguracji (odmowa, nie domyślne
  zezwolenie). Zgłaszaj jako High każde osłabienie tych sprawdzeń.
- **Testy integracyjne SSH na prawdziwy serwer muszą zostać wyłączone domyślnie.** Mają być
  pomijane, chyba że ustawiona jest jawna flaga (np. `RUN_FROG_INTEGRATION_TESTS=1`), a dane
  połączenia (host/user/port/hasło) czytane wyłącznie z env/sekretów CI — nigdy jako wartości
  domyślne w kodzie testu.
- **Cienkie kontrolery.** Logika biznesowa (parsowanie logów, walidacja dostępu, wykonywanie
  poleceń SSH/Docker) należy do `src/Service/`, nie do `src/Controller/`. Zgłaszaj kontrolery
  robiące więcej niż walidację wejścia + delegację + zwrot Response.
- **Bramka jakości (GrumPHP: PHPStan + PHPUnit + PHP-CS-Fixer) musi zostać zielona.** Nie
  proponuj wyłączania/omijania tasków w `grumphp.yml` ani rozszerzania `phpstan-baseline.neon`
  bez uzasadnienia — wpis do baseline bez komentarza wyjaśniającego dlaczego to nie jest błąd,
  to zamiatanie problemu pod dywan.
- **Deklarowany floor PHP to `>=8.4`** (dopasowany do `composer.lock`: GrumPHP i część pakietów
  Symfony w dev-dependencies realnie wymagają >=8.2–8.4.1, co zostało empirycznie potwierdzone
  failującym CI na niższej wersji). Nie sugeruj obniżenia tego floora bez sprawdzenia, że
  `composer install` faktycznie przechodzi na tej wersji.
- **Workflow'y CI mają działać na najmniejszych możliwych uprawnieniach**: `permissions:
  contents: read` domyślnie, `contents: write` tylko tam gdzie realnie potrzebne (merge do
  master), `persist-credentials: false` przy checkout. Auto-merge `develop -> master`
  (`auto-merge-to-master.yml`) jest celowo tylko fast-forward i zatrzymuje się bez auto-resolve
  przy rozjeździe gałęzi, oraz respektuje bezpiecznik `.ci/DEPLOY_PAUSED`. **Nie proponuj**
  obchodzenia branch protection, wymogu code review ani ff-only guard (np. dodawania
  sekretów/PAT-ów do CI albo `git merge` z auto-resolve konfliktów) — to celowe zabezpieczenia,
  nie przeoczenia.
- **Nie dodawaj nowych zależności** (composer/npm) bez wyraźnego uzasadnienia w opisie PR-a.
  Jeśli widzisz nową zależność bez widocznego użycia, zgłoś to jako ryzyko supply-chain.
