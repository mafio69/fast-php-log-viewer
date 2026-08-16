# fast-php-log-viewer

> Standalone log viewer for [fast-php-logger](https://github.com/mafio69/fast-php-logger).
> Part of the **fast-php-\*** suite.

---



## Installation — Composer

Best for: new projects, Docker environments, when you already use Composer.

```sh
composer require mafio69/log-viewer
```

Create a single entry point file (e.g. `log-viewer.php` in your webroot):

```php
<?php
define('LOG_DIR', __DIR__ . '/logs');
require_once __DIR__ . '/vendor/autoload.php';

$app = \Mariusz\LogViewer\Bootstrap\AppBootstrap::create();
$request = \Slim\Psr7\Factory\ServerRequestFactory::createFromGlobals();
$app->run($request);
```

A ready-to-use example file is included at [`example/viewer.php`](example/viewer.php).

---

## Docker

### Quick start

```sh
docker compose up -d
```

Open `http://localhost:9123` — no setup needed.

### Volume mounts

| Host path              | Container path         | Purpose                                  |
|------------------------|------------------------|------------------------------------------|
| `./docker-logs/nginx/` | `/var/log/nginx/`      | Sample nginx logs                        |
| `/var/log`             | `/host/var/log`        | Host system logs                         |
| `~/logs`               | `/host/home/logs`      | User home logs                           |
| `~/.ssh`               | `/home/www-data/.ssh`  | SSH keys (for remote log access)         |
| `/var/run/docker.sock` | `/var/run/docker.sock` | Docker socket (for reading other container logs) |

### Three log access modes

The app supports three log access modes, selected in the sidebar:

| Mode | Description | Example |
|---|---|---|
| **DOCKER** | Logs inside the app container (volume mounts) | `/var/log/nginx/error.log` |
| **HOST** | Host logs mounted via `/host/var/log` | `/host/var/log/nginx/error.log` |
| **CONTAINER** | Logs from another Docker container via `docker exec cat` | provide `container_name` + `/var/log/nginx/error.log` |

### Reading logs from another container

1. In the sidebar, enter the container name (or container ID) in the `container_name` field
2. Enter the path to the file inside that container, e.g. `/var/log/nginx/error.log`
3. Click **LOAD**

The app uses the Docker Engine API via the `/var/run/docker.sock` socket to run
`docker exec cat <path>` inside the indicated container.

**Requirements:**
- `/var/run/docker.sock` must be available and mounted (default in docker-compose)
- The PHP process (www-data) must have permissions to the socket (start.sh configures this automatically)

### Running without Docker Compose

```sh
docker run -d \
  -p 9123:80 \
  -v .:/var/www/html \
  -v ./logs:/var/www/html/logs \
  -v /var/log:/host/var/log \
  -v /var/run/docker.sock:/var/run/docker.sock \
  fast-php-log-viewer
```

---

## SSH — remote logs

The app supports browsing and reading logs on remote servers via SSH.

### Authentication modes

- **Password** — user password authentication
- **SSH key** — private key authentication (RSA, Ed25519, ECDSA) + optional key passphrase

### How it works

1. User configures an SSH connection (host, user, auth)
2. Frontend sends `POST /api/ssh/list-files` with connection data
3. Backend opens an SSH connection (via `ext-ssh2`), scans the remote directory
4. Files are downloaded and cached locally in `data/` for fast access
5. Remote logs are displayed the same way as local ones

### Security notes

- SSH passwords are NEVER saved in config files or the database
- SSH profiles are stored in `data/app_config.json` (0600) without passwords
- Passwords are kept in memory only during the active session

---

## 🔐 Security — read BEFORE you run

> **Search:** `password`, `login`, `admin`, `security`, `secure` — any of these words leads you here.

### Technical account

The app has one predefined technical account for first launch:

| Login  | Password  | Purpose                              |
|--------|-----------|--------------------------------------|
| `admin`| `jesien26`| First login, SSH management          |

> **⚠️ CHANGE THIS PASSWORD IMMEDIATELY after first login.**
> This is not your permanent password — it is a starting point.
> The `admin` account lives in the SQLite database (`data/logviewer.db`),
> which is git-ignored (never committed). Each installation has its own DB.

### Why login at all?

Login exists **solely** to keep your private SSH connections
(host, user, key path) isolated from other people who may use the same
app instance. It is **not** an account system that gates access to logs —
all four log-browsing methods (default directories, pasted path, Docker
container, SSH) work without logging in.

A logged-in user sees:
- Their own private SSH connections (saved in the DB with their `user_id`)
- Global SSH connections (from the setup wizard — shared)

They do NOT see:
- Other users' private SSH connections

---

## 👋 A friend installs the app — complete guide

Scenario: someone heard about the app and wants to run it on their machine.
Here are all the problems they will hit, and their solutions — step by step.

### Step 1: Download and run

```sh
git clone https://github.com/mafio69/fast-php-log-viewer.git
cd fast-php-log-viewer
docker compose up -d
```

Open `http://localhost:9123`. The setup wizard guides you through configuration.

### Step 2: Setup wizard (first run)

| Step           | What it does                        | What to choose                     |
|----------------|-------------------------------------|------------------------------------|
| `generate_keys`| Generates backup encryption key     | Don't skip — needed for SSH       |
| `ssh_config`   | Configures the first SSH connection | Can skip, add later               |
| `local_directories` | Adds a log directory           | Pick `/var/log` or `~/logs`       |
| `finalize`     | Completes setup                     | —                                  |

### Step 3: Change the admin password

1. Click **👤 Login** in the bottom-left corner
2. Log in as `admin` / `jesien26`
3. **You can't change the password via UI (yet)** — do it via CLI:

```sh
docker compose exec app php -r "
require '/var/www/html/vendor/autoload.php';
use Mariusz\LogViewer\Service\AuthService;
session_start();
\$a = new AuthService('/var/www/html/data/logviewer.db');
\$a->logout();
// Delete the old admin and create a new one with your password:
\$db = new PDO('sqlite:/var/www/html/data/logviewer.db');
\$db->exec(\"DELETE FROM users WHERE username='admin'\");
\$a->register('admin', 'YOUR_NEW_STRONG_PASSWORD');
echo \"Password changed.\n\";
"
```

### Step 4: Secure the app (CRITICAL)

The app **does not require login to browse logs** by default.
This is a deliberate design choice — it is a developer tool, not a production app.
But if you expose it beyond `localhost`, you **must** secure access.

#### Option A: Localhost only (simplest, default)

```sh
# docker-compose.yml already maps to localhost:
ports:
  - "127.0.0.1:9123:80"  # ← localhost only, not 0.0.0.0
```

If your ports read `9123:80` (without `127.0.0.1:`), change to `127.0.0.1:9123:80`
and restart. No one outside can connect.

#### Option B: Reverse proxy with auth (nginx + Basic Auth)

```nginx
server {
    listen 443 ssl;
    server_name logs.yourdomain.com;

    ssl_certificate     /etc/ssl/cert.pem;
    ssl_certificate_key /etc/ssl/key.pem;

    auth_basic "Log Viewer";
    auth_basic_user_file /etc/nginx/.htpasswd;

    location / {
        proxy_pass http://localhost:9123;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

Create `.htpasswd`:
```sh
htpasswd -c /etc/nginx/.htpasswd your_user
```

#### Option C: VPN / Tailscale (most secure)

Run the app only inside a VPN. Tailscale (free up to 100 devices) is simplest:

```sh
# On the server:
curl -fsSL https://tailscale.com/install.sh | sh
tailscale up

# App listens on localhost, accessible only via Tailscale:
tailscale serve --bg 9123
```

### Step 5: Secure the Docker socket (IMPORTANT)

The app has access to `/var/run/docker.sock` — this means it can read files
from **any** container on the host. This is not a bug, it's a feature
(reading logs from other containers). But:

- The app has a **container allow-list** — only explicitly added containers
  are readable. Adding requires confirmation in the UI.
- **Never expose the app without auth** (Option B or C above)
  if the Docker socket is mounted.

### Step 6: SSH — password security

| Question | Answer |
|----------|--------|
| Is the SSH password saved in the DB? | **NO.** Never. Only host/user/method/key path. |
| Where does the SSH password go? | Only into memory for the duration of the active browser session. |
| Can other users see my SSH connections? | **NO**, if you are logged in — your connections are private (isolated by `user_id`). |
| Can I use SSH without logging in? | Yes, but then connections are global (visible to everyone on that instance). |

### Step 7: Backup and encryption

The setup wizard generates `BACKUP_ENCRYPTION_KEY` (saved in `.env`).
This key encrypts the config backup in `data/logviewer_backup.json`.

- **Don't lose this key** — without it the backup is unreadable.
- The key is in `.env` (git-ignored, not in the repo).
- If `generate_keys` was skipped, the backup is **unencrypted** (plain JSON).

### Step 8: Common problems

| Problem | Solution |
|---------|----------|
| `no such table: users` | DB doesn't exist. Run the setup wizard or `docker compose restart app`. |
| `SQLSTATE... database is locked` | Someone else is using the DB. SQLite = 1 writer. Wait or restart. |
| SSH: `ssh2 extension not loaded` | `docker compose exec app docker-php-ext-install ssh2 && docker compose restart app` |
| Docker: `permission denied on docker.sock` | `sudo chmod 666 /var/run/docker.sock` (or add www-data to the docker group) |
| Can't change password | See Step 3 above (CLI). Password-change UI is in progress. |
| Port 9123 taken | Change in `docker-compose.yml`: `127.0.0.1:9130:80` |
| `BACKUP_ENCRYPTION_KEY is not set` | Setup wizard was skipped. Run: `docker compose exec app php -r "..."` or delete `data/app_config.json` and restart. |

### Search keywords

If you get stuck, search this file for:
`password`, `login`, `admin`, `security`, `secure`, `docker.sock`,
`ssh`, `backup`, `encryption`, `localhost`, `reverse proxy`, `vpn`,
`tailscale`, `htpasswd`, `friend`, `install`, `first run`

---

## Features

- Listy plikow logow posortowane po dacie (najnowsze pierwsze)
- Parsowanie formatu `fast-php-logger`: `[datetime] [LEVEL] [file:line] message {context}`
- Filtrowanie po poziomie logowania (DEBUG / INFO / NOTICE / WARNING / ERROR / CRITICAL / ALERT / EMERGENCY)
- Wyszukiwanie full-text po wiadomosci i lokalizacji
- Rozwijany kontekst JSON dla kazdego wpisu
- Kolorowane poziomy w stylu retro terminala (CRT)
- Vue 3 + Tailwind CSS — bez build step, bez node_modules
- **Directory selection** — 4 default directories (docker, host, home, repository) + custom
- **Direct path** — quick access to any file by typing its path
- **Docker container reader** — czytanie logow z innych kontenerow przez Docker API
- **SSH** — przegladanie i czytanie logow na zdalnych serwerach
- **Setup wizard** — konfiguracja pierwszego uruchomienia (klucze, SSH, katalogi)
- **Bookmarks** — zakladki do waznych wpisow logow
- **Paginacja** — duze pliki logow z sortowalnymi kolumnami

---

## Log format

```
[2026-05-03 14:25:00] [WARNING] [app/index.php:42] Something off {"user":"jan@example.com"}
```

Also supports nginx error log, nginx access log, syslog, PHP errors, and custom formats.

Compatible with all `fast-php-logger` directory structures:

| `dateStructure` | Path |
|---|---|
| `Y/m` (default) | `logs/2026/05/2026-05-03.log` |
| `Y` | `logs/2026/2026-05-03.log` |
| `Y/m/d` | `logs/2026/05/03/2026-05-03.log` |
| `""` (flat) | `logs/2026-05-03.log` |

---

## API Endpoints

| Endpoint                       | Method | Description                          |
|--------------------------------|--------|--------------------------------------|
| `/api/setup/status`            | GET    | Check setup wizard status            |
| `/api/setup/step`              | POST   | Execute setup step                   |
| `/api/setup/migrate-ssh`       | POST   | Migrate SSH config from localStorage |
| `/api/app-config`              | GET    | Get application config               |
| `/api/app-config`              | POST   | Update application config            |
| `/api/config/default-directories` | GET | Get built-in default directories   |
| `/api/directories`             | GET    | List configured directories          |
| `/api/files`                   | GET    | List log files in directory          |
| `/api/entries`                 | GET    | Get log entries from file            |
| `/api/config/directories`      | POST   | Add allowed directory                |
| `/api/config/directories/{id}` | PUT    | Update directory config              |
| `/api/config/directories/{id}` | DELETE | Remove directory config              |
| `/api/scan/directories`        | GET    | Scan filesystem for log directories  |
| `/api/ssh/test-connection`     | POST   | Test SSH connection                  |
| `/api/ssh/list-files`          | POST   | List files via SSH                   |
| `/api/ssh/read-file`           | POST   | Read file via SSH                    |
| `/api/ssh/download-file`       | POST   | Download file via SSH to local cache |

### Key `/api/entries` parameters

| Parameter       | Description                                               |
|-----------------|-----------------------------------------------------------|
| `file`          | Path to the log file (required)                           |
| `dir`           | Context directory key (for access validation)             |
| `container_id`  | Docker container ID/name to read via Docker API           |
| `level`         | Filter by log level                                       |

If `container_id` is set, `file` is the path INSIDE the container (e.g. `/var/log/nginx/error.log`).

---

## Structure

```
fast-php-log-viewer/
├── public/
│   ├── index.php              ← Entry point (Slim + Vue SPA)
│   ├── css/style.css
│   └── js/
│       ├── app.js             ← Vue 3 bootstrap
│       ├── store.js           ← Reactive state + API layer
│       └── components/
│           ├── VApp.js        ← Root component
│           ├── Sidebar.js     ← Dir/file selector + direct path
│           ├── DataTable.js   ← Log entries table
│           ├── Toolbar.js     ← Filters, search, bookmarks
│           ├── SSHModal.js    ← SSH connection manager
│           └── SetupWizard.js ← First-run wizard
├── src/
│   ├── Bootstrap/             ← Slim app factory, DI, routes
│   ├── Config/
│   │   ├── ConfigManager.php  ← JSON app config I/O
│   │   └── LogConfig.php      ← SQLite directory config
│   ├── Controller/
│   │   ├── LogController.php  ← Core: dirs, files, entries
│   │   ├── DirectoryController.php
│   │   ├── SSHController.php
│   │   ├── SetupController.php
│   │   └── AppConfigController.php
│   ├── Middleware/
│   │   └── SetupMiddleware.php ← Blocks API before setup done
│   ├── Repository/
│   │   ├── LogEntry.php
│   │   ├── LogFile.php
│   │   └── SSHConnection.php
│   ├── Routing/
│   │   └── LegacyRouter.php   ← ?action= → /api/* compat
│   └── Service/
│       ├── DockerExecService.php   ← Docker socket API client
│       ├── LogParser.php           ← Multi-format log parser
│       ├── PathResolver.php        ← Path resolution (~/, rel, colon)
│       ├── FileAccessValidator.php ← Path allowlisting
│       ├── GlobLogFinder.php       ← Local file finder
│       ├── RemoteLogFinder.php     ← SSH remote file finder
│       ├── SSH.php                 ← SSH connection (ext-ssh2)
│       ├── LogScanner.php          ← Filesystem scanner
│       ├── SecurityService.php     ← Input sanitization
│       └── SetupWizard.php         ← Setup wizard logic
├── docs/
│   ├── design.md              ← Architecture design (PL)
│   ├── requirements.md         ← Feature requirements (PL)
│   └── technical.md            ← Technical details (PL)
├── docker/
│   ├── Dockerfile
│   ├── nginx.conf
│   ├── php-errors.ini
│   └── start.sh
├── templates/
│   └── viewer.php              ← SPA HTML shell
├── data/                       ← App data (config, DB, backups)
├── tests/                      ← PHPUnit tests + property-based
├── docker-compose.yml
└── composer.json
```

---

## Requirements

- PHP >= 8.1
- `ext-pdo`, `ext-pdo_sqlite`
- `ext-ssh2` (for SSH remote log support)
- `ext-json`
- Composer
- Docker socket (for Docker container log reading)

---

## fast-php-\* suite

| Package | Description |
|---|---|
| [fast-php-logger](https://github.com/mafio69/fast-php-logger) | PSR-3 file logger |
| [fast-php-log-viewer](https://github.com/mafio69/fast-php-log-viewer) | Log viewer (this package) |
| [docker-fast-logger](https://github.com/mafio69/docker-fast-logger) | Docker dev environment with both pre-installed |

---

## Contributors

- **Mariusz** — author and maintainer
- **OpenCode Go Contributors** — AI-assisted development, refactoring, documentation

---

## License

MIT
