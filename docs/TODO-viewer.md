# TODO: app.local/logs — log-viewer

## Co robimy

Refaktor / rozwój przeglądarki logów serwowanej pod `http://app.local/logs`.  
Entry point: `viewer/index.php` → deleguje do `vendor/mafio69/log-viewer`.

## Obecny stan

- `viewer/index.php` — 10 linii, definiuje `LOG_DIR`, ładuje autoloader, routuje `?action=` do API albo renderuje UI
- API: `vendor/mafio69/log-viewer/src/api.php` (patch w `patches/api.php`)
- UI: `vendor/mafio69/log-viewer/index.php`
- Dev override volume: `../PhpstormProjects/log-viewer` montowany w docker-compose

## Z audytu — problemy do rozwiązania

1. Viewer dostępny bez auth (OK na dev, ale warto mieć opcję)
2. Patch w `patches/api.php` — czy jest aktualny vs vendor?
3. Volume override do lokalnego katalogu PhpStorm — przenieść do `docker-compose.override.yml`
4. Sprawdzić czy UI renderuje logi `.php` (niestandardowe rozszerzenie)

5. Sprawdzić czy `setup.sh` po sklonowaniu repo automatycznie konfiguruje domeny `.local` (lub `.test`) — czy viewer
   jest od razu dostępny pod `app.local/logs`
6. Dodać w README opis jak dodać kolejny serwis na nowym porcie (np. `localhost:3467`) — krok po kroku: docker-compose,
   proxy, /etc/hosts
7. **Ficzer: skrypt `add_http_mask`** — podajesz `localhost:3467 phpadmin.local` i skrypt sam:
    - dodaje wpis do `/etc/hosts`
    - konfiguruje nginx-proxy (VIRTUAL_HOST)
    - opcjonalnie dodaje serwis do docker-compose
    - Przykład: `add_http_mask localhost:8081 phpadmin.local`

## Kontekst

- `app/logs.php` — ZROBIONE (09.05) — osobny prosty viewer w app/
- Ten task dotyczy oficjalnego pakietu `log-viewer`
