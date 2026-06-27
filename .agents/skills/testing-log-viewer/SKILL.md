---
name: testing-fast-php-log-viewer
description: Test the fast-php-log-viewer app end-to-end. Use when verifying Setup Wizard, sidebar UI, SSH modal, or log viewing changes.
---

# Testing fast-php-log-viewer

## Running the App

```bash
cd /home/ubuntu/repos/fast-php-log-viewer
docker compose up -d
# App runs at http://localhost:9123
```

## Docker Permissions Issue

The `data/` directory inside the container may not be writable by www-data on first run. Fix with:

```bash
docker exec fast-php-log-viewer-php-1 chmod -R 777 /var/www/html/data/
```

This is needed for the Setup Wizard to write `app_config.json`. Without it, the `/api/setup/step` endpoint returns HTML errors instead of JSON.

## Resetting Setup State

To test the Setup Wizard from scratch, delete the config file inside the container:

```bash
docker exec fast-php-log-viewer-php-1 rm -f /var/www/html/data/app_config.json
```

Then reload the browser. The wizard overlay will appear automatically.

## Setup Wizard Flow

The wizard has 4 steps accessed via `/api/setup/status` and `/api/setup/step`:

1. **generate_keys** — Generates encryption key. Key should be displayed to user before advancing.
2. **ssh_config** — SSH connection form (can be skipped with "Pomin" button).
3. **local_directories** — Local log directory paths (pre-filled with `/var/log`).
4. **finalize** — Shows summary of completed/skipped steps, "Zakoncz setup" button.

After finalize, the wizard overlay disappears and `initApp()` loads directories and files.

## Key API Endpoints

- `GET /api/setup/status` — Returns `setup_required: true/false` and step statuses
- `POST /api/setup/step` — Body: `{step, data, skip}`. Returns `{success, next_step, ...}`
- `GET /api/directories` — Returns configured directories (blocked by SetupMiddleware if setup incomplete)
- `GET /api/files?dir=<key>` — Returns files in a directory
- `GET /api/entries?file=<path>` — Returns parsed log entries

## UI Architecture

- **Frontend:** Vue.js 3 (global CDN build), Tailwind CSS v4 (CDN)
- **Backend:** PHP 8.4, Slim 4
- **Theme:** CRT terminal aesthetic (green on black, `#00ff00`)
- **Main files:**
  - `public/index.php` — HTML template with Vue directives
  - `public/js/app.js` — Vue app logic (setup(), reactive state, API calls)
  - `public/css/style.css` — CRT theme styles

## Common Test Scenarios

### Setup Wizard
1. Reset state (delete app_config.json)
2. Reload page — wizard overlay should appear
3. Click "Dalej" on generate_keys — encryption key should display and persist on screen
4. Click "Dalej" again — should advance to ssh_config
5. Click "Pomin" — should skip to local_directories
6. Click "Dalej" with default /var/log — should advance to finalize
7. Click "Zakoncz setup" — wizard disappears, app loads with file list

### Sidebar Spacing
- Buttons should have visible gaps (inline styles + CSS `aside button + button { margin-top: 2px }`)
- Button labels should be Polish ASCII: "Czysc duplikaty", "Czysc nazwy allowed_*"

### SSH Modal
- Click "SSH Connections" button in sidebar
- Modal should show "Add New SSH Connection" header (editingIndex = -1)
- When editing, header should show "Edit SSH Connection"

## Devin Secrets Needed

No secrets required. The app runs locally in Docker with no external auth.
