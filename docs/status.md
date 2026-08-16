# STATUS — fast-php-log-viewer

Ostatnia aktualizacja: 2026-08-16 (sesja z GLM-5.2, wznowienie — porządki git)

## Drzewo

- Gałąź robocza: `develop` (trackuje `origin/develop`)
- HEAD `develop`: `4a580ca` (merge) — Fix: single source of truth for currentSource (5-280/5-46/5-281)
- HEAD `master`: `d30c83c` — DualLogger: add 'repository:data' default + fix php-errors.ini path
- develop jest **1 commit przed masterem** — czeka na PR `develop → master` (patrz `przepływ-git.md`: master tylko przez PR, nigdy bezpośrednim pushem)
- Drzewo robocze: **czyste** (wszystko zcommitowane i zpushowane na origin/develop)
- Kontener: `fast-php-log-viewer-php-1` — **nie działał** przy wznowieniu (do odpalenia `docker compose up -d --build`)

## Bramki jakości

- **PHPUnit:** 278 testów, 5151 asercji, 6 skipped (opt-in frog), 0 błędów ✅
- **PHPStan:** level 6 + baseline, 0 błędów ✅
- **php-cs-fixer:** 0 do poprawy ✅
- **Grumphp (pre-commit hook):** przechodzi ✅

## Wymagania business-rules (docs/business-rules.md)

| Wym | Status | Uwagi |
|-----|--------|-------|
| 1. Wklejona ścieżka (host/wolumen) | ✅ ZROBIONE | DirectoryController + LogConfig::addDirectory |
| 2. ID kontenera + ścieżka | ✅ ZROBIONE | AllowedContainerController + DockerExecService |
| 3. SSH + prywatna sesja | ✅ ZROBIONE | AuthService/AuthController/AuthMiddleware, LogConfig ssh_connections (per-user), deleteSSHConnection ownership check |
| 4. Domyślne lokalizacje | ✅ ZROBIONE | DefaultLogSources + getDefaultDirectories (ale dwie prawdy — patrz tech debt) |

## Ostatnie commity na develop (od najnowszego)

```
eaf7c0c Merge branch 'fix/duallogger-directory-recursion'
7868217 Docs: add project status snapshot (2026-08-16)
cd38042 Docs: add status.md protocol (start/push lifecycle)
3fb8fdf Docs: update status after git sync (develop 7868217, master d30c83c)
d30c83c DualLogger: add 'repository:data' default + fix php-errors.ini path
62b378e Fix: Docker container check shows 'błąd sprawdzania' instead of 'niedozwolony'
afd65bf Docs: README in English + security guide + technical account
1e3d705 Auth UI + tests (Wymaganie 3, kryterium 3-4)
b411683 Security: deleteSSHConnection must check ownership (Wymaganie 3, kryterium 3)
9730fd4 Auth: per-user SSH connections + login/session (LogConfig-backed)
4484da6 Update docs
```

## Git flow (zgodnie z `przepływ-git.md`)

- Praca na branchach zadaniowych z aktualnego `develop` (nie bezpośrednio na develop, chyba że priorytet `fire`).
- `develop` → merge lokalnie + push do `origin/develop` (bez PR).
- `master` → **tylko przez PR** z `develop`, po zielonym CI. Bezpośredni push zabroniony.
- Commity AI podpisują się własną tożsamością (`--author` + `Co-Authored-By` trailer).

## Protokół tego pliku

- **Start zadania** → dopisz 1-2 linijki pod sekcją „W toku" (co robisz, nad czym pracujesz).
- **Po push** → usuń te linijki (zadanie zcommitowane = nie „w toku"; historia żyje w git, nie w statusie).

## Co jest W TOKU

*(brak — ostatnie zadanie zcommitowane i zpushowane)*

## Zrobione (ostatnia sesja)

### 5-281: loadDirectories() preserves selectedDir
- `loadDirectories()` resetował `selectedDir` na pierwszy default przy każdym wywołaniu — gubił wybór usera po `addAllowedDir`/`deleteDirectoryEntry`/`saveDirectoryShortcut`.
- Fix: tylko pierwsze ustawienie gdy `selectedDir` jest puste.
- Commit `4a580ca` na `fix/current-source-single-truth` → merged to develop.

### 5-280/5-46: Single source of truth for currentSource
- `loadEntries()` ufał `selectedFileContainerId` jako cache — jeśli `selectedDir` się zmienił bez `loadFiles()`, entries szły do złego brancha (docker zamiast host).
- Fix: `currentContainerId()` helper wylicza container id na żywo z `selectedDir` (saved docker shortcut) lub `selectedFileContainerId` (direct-docker mode).
- Commit `4a580ca` (j.w.).

## Zrobione (ostatnia sesja)

### P1.B: Recursive directory scan — `repository:data` ładuje pliki DualLogger
- `LocalDirectoryReader::findAll()` rekursywnie skanuje podkatalogi (data/YYYY/MM/*.log)
- Commit `eaf7c0c` na branch `fix/duallogger-directory-recursion` → merged to develop

### P1.A: Sortowanie + filtr daty działa po naprawie P1.B
- Root cause: `repository:data` nie ładował plików DualLogger (brak rekursji), więc Mariusz testował z `php_errors.log` który ma format `04-May-2026 09:09:37 Europe/Warsaw` — `slice(0,10)` nie pasowało do `YYYY-MM-DD` z inputa.
- Fix: `LogParser::normalizePhpErrorDate()` konwertuje PHP error datetime do ISO `YYYY-MM-DD HH:MM:SS`.
- Sort logic i filter logic w `store.js` były poprawne cały czas — problem był w formacie danych.

## Tech debt (backlog)

1. **„Dwie prawdy" defaults:** `DefaultLogSources::DEFAULTS` vs `LogConfig::getDefaultDirectories()` (hardcoded). Powinno być jedno źródło.
2. **Frontend SSH w localStorage:** nowe endpointy `/api/ssh/connections` istnieją, ale frontend `store-ssh.js` ich nie używa.
3. **Brak UI do zmiany hasła:** admin/jesien26 zmiana tylko przez CLI (README dokumentuje).
4. **Dokumentacja:** `docs/AUDIT_REPORT.md` nieaktualny (pozycje JS 11-17 wykonane).
5. **Refaktor store.js:** wyodrębnienie store-setup.js / store-dirs.js / store-bookmarks.js (5-86).

## Konta / sekrety

- **Konto techniczne:** `admin` / `jesien26` w `data/logviewer.db` (git-ignored). Zmiana hasła przez CLI.
- **Tokeny:** zrotowane (GitHub), Perplexity odłożone. `.mcp.json`, `.mcp/`, `.junie/` w `.gitignore`.
- **BACKUP_ENCRYPTION_KEY:** w `.env` (git-ignored), generowany przez setup wizard.

## Kontener Docker

- `docker compose up -d --build` — rebuild po zmianach w Dockerfile/ini
- `docker compose restart php` — restart po zmianach PHP/JS (hot reload przez bind mount)
- Port: 9123 (mapowany na 0.0.0.0 — patrz README sekcja "Secure the app")
- `php-errors.ini`: `error_log = /var/www/html/logs/php-error/php_errors.log` (naprawione)

## Lista TODO #5 (DevBrain-todo)

Odhaczone: 5-244 (auth), 5-52 (gitignore).
Otwarte (8 dodanych w tej sesji): 5-280 do 5-287.
**Lista #50: NIE TYKAJ** (Mariusz: „nią się nie interesuj jak zrobimy to później").
