# STATUS — fast-php-log-viewer

Ostatnia aktualizacja: 2026-08-16 (sesja z GLM-5.2, wznowienie — porządki git)

## Drzewo

- Gałąź robocza: `develop` (trackuje `origin/develop`)
- HEAD `develop`: `7868217` — Docs: add project status snapshot (2026-08-16)
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
7868217 Docs: add project status snapshot (2026-08-16)
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

## Co jest W TOKU (przerwane)

### 1. Sortowanie + filtr daty w DataTable NIE DZIAŁAJĄ
- Mariusz zgłosił: „datatable nie działa, nie sortuje, filtr po dacie nic nie puszcza"
- Filtr po poziomie (INFO/WARNING) działa ✅
- Format datetime w entries: `YYYY-MM-DD HH:MM:SS` (z DualLogger/nginx)
- Podejrzenie: `applyFilters()` w `public/js/store.js:~360` lub `tableSortedData` computed
- Nie zdiagnozowano — **przerwano**

### 2. `repository:data` nie ładuje plików przez API
- Dodano `repository:data` do `LogConfig::getDefaultDirectories()` ✅
- Ale `GET /api/files?dir=repository:data` zwraca `{"error":"Katalog nie istnieje."}`
- Root cause: `LogController::getFiles()` z `dir` szuka w bazie, nie w defaults
- Frontend `filesApiUrl()` powinien budować `?path=data/` — **nie zweryfikowano**

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
