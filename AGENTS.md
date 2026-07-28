# AGENTS.md

## Purpose

This repository is a PHP starter template for Docker / Dev Container based development.
The main branch must stay framework-neutral and focused on reusable foundations.

## Rules

- Prefer simple, practical solutions.
- Do not add framework-specific code to `main`.
- Keep controllers thin and logic explicit.
- Follow SOLID, KISS, DRY, and clean code principles.
- Keep tooling useful, not decorative.
- Prefer small, reviewable changes.

## Quality gates

Before finishing changes, run:

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run --diff
```

## Documentation

Keep README human-oriented.
Keep AGENTS.md short, specific, and authoritative for AI assistants.

## Change policy

- Do not introduce unnecessary dependencies.
- Do not weaken checks just to make CI green.
- Fix root causes before adding workarounds.
- If autoload issues appear, check namespace mapping and run `composer dump-autoload` first.

## DO NOT MAKE CHANGES WITHOUT EXPLICIT PERMISSION
## DO NOT READ, ACCESS, OR EXPOSE SECRETS. DO NOT ASK TO EDIT .ENV* FILES. DO NOT OPEN THEM. DO NOT PRINT THEM. ALWAYS REFER TO THE VARIABLE NAME OR TOKEN NAME, NEVER TO THE SECRET VALUE, TOKEN VALUE, OR PASSWORD. USE DUMMY SECRETS FOR TESTS.
## DO NOT MODIFY ANYTHING INSIDE vendor/

## Branch & merge workflow (anti-divergence)

This repo has been broken by direct commits to `develop`/`master` and by
unsynchronised force-pushes. The following procedure is binding:

1. **Never commit directly on `develop` or `master`.** Always work on a
   branch cut from a freshly fetched `origin/develop`:
   ```sh
   git fetch origin
   git checkout -b feat/<short-topic> origin/develop
   # or fix/, docs/, chore/
   ```
2. **Keep the branch on top of `origin/develop`** — rebase, do not merge
   develop back into the branch:
   ```sh
   git fetch origin
   git rebase origin/develop
   ```
3. **Green before merge.** Before any merge run, in this order:
   ```sh
   composer install
   composer cs-fix        # auto-format
   composer stan          # PHPStan 6
   composer test          # PHPUnit
   # equivalent one-shot:
   composer grumphp
   ```
   If any gate fails, fix the root cause on the branch — never weaken the
   gate or stash the failure onto `develop`.
4. **Fast-forward merge into `develop`, then push:**
   ```sh
   git checkout develop
   git merge --ff-only feat/<short-topic>
   git push origin develop
   git branch -d feat/<short-topic>
   ```
   If `--ff-only` fails, the branch drifted — rebase it on `origin/develop`
   and retry. Do not create merge commits for routine work.
5. **No uncoordinated force-push to `develop` or `master`.** If history must
   be rewritten (e.g. recover from a broken remote), first save a local
   backup branch so nothing is lost:
   ```sh
   git fetch origin <branch>
   git branch backup/<branch>-<YYYYMMDD> origin/<branch>
   # ...rewrite, then:
   git push --force-with-lease=<branch>:origin/<branch> origin <branch>
   ```
   Document the rewrite in the PR description or commit body.
6. **`master` is a promotion of a known-green `develop`.** Do not advance
   `master` by cherry-picking individual commits — merge a tested develop
   (or tag a release) so the two branches never diverge silently. Before
   promoting, confirm `origin/master` is an ancestor of the candidate tip.

The rule behind all of the above: **`develop` must stay linear, green, and
fast-forward-able from `origin/develop`.**

## Technical enforcement (this file alone is not enough)

This file is advice to the model, not a technical guarantee — it can be (and has been)
ignored. The actual barrier is `opencode.json` in this repo: `permission.task = deny`
(subagents disabled — a known bug lets them bypass `deny` on `read`/`grep`),
`permission.external_directory = deny`, and `read`/`edit`/`glob`/`grep` deny anything
dot-prefixed except `.local/`, `.github/`, `.opencode/`. `.local/` is the only place for
AI scratch/task files that must never reach git.

## Commands

```sh
composer test                     # PHPUnit (all tests)
composer stan                     # PHPStan level 6 on src/ only
composer cs-check                 # php-cs-fixer dry-run
composer cs-fix                   # php-cs-fixer apply
composer grumphp                  # pre-commit task suite (stan + phpunit + cs-fixer)
```

Run a single test or class:

```sh
vendor/bin/phpunit --filter testParsesFullLine
vendor/bin/phpunit tests/Controller/LogControllerTest.php
```

Verification order when finishing work: **cs-fix → stan → test** (or just `composer grumphp`).

## PHP & toolchain

- PHP **>= 8.4** (composer.json is the source of truth; README's "8.1" is stale).
- `declare(strict_types=1)` is used in `src/` and `tests/` but **not** enforced by cs-fixer (`declare_strict_types => false`).
- PHPStan level 6 with a baseline (`phpstan-baseline.neon`). Run `vendor/bin/phpstan analyse --generate-baseline` after suppressing new errors intentionally; never commit a growing baseline for real bugs.
- cs-fixer ruleset: `@PSR12` + short arrays, alpha-ordered imports, single quotes, trailing commas in multiline. Covers `src/` and `tests/` only.

## Architecture

- **Slim 4** app, **PHP-DI** container. All service wiring is manual in `src/Bootstrap/container.php` — adding a new service means registering it there, not relying on autowire (only `LogFinderInterface` uses `autowire`).
- Entry point: `public/index.php` → `src/Bootstrap/frontend.php`. It routes `/api/*` and legacy `?action=` requests to Slim, otherwise renders the SPA template (`templates/viewer.php`).
- Library entry point: `src/Bootstrap/AppBootstrap::create()` (used by `example/viewer.php` and consumers).
- Constants `ROOT_DIR`, `DATA_DIR` defined in `container.php`; `LOG_DIR`, `EDITOR_URL`, `CSP_NONCE` in `frontend.php`. Tests override `LOG_DIR` via `phpunit.xml`.
- **Two config stores** in `data/`: `app_config.json` (JSON, via `ConfigManager`) and `logviewer.db` (SQLite, via `LogConfig`). `data/` contents are gitignored.
- `SetupMiddleware` blocks all non-setup API routes until the setup wizard completes.
- Frontend is **Vue 3 via CDN** — no npm/node build step, no `node_modules` for the app itself. Components live in `public/js/components/` as plain `.js` files.

## Tests

- PHPUnit 11 + **eris** (property-based testing) — see `*PropertyTest.php` files.
- `phpunit.xml` sets `APP_ENV=testing`, `LOG_DIR=temp/logs`, and a fixed `BACKUP_ENCRYPTION_KEY`. Tests write to `temp/` (gitignored) — don't rely on `data/` in tests.
- `ext-ssh2` and `ext-pdo_sqlite` are required; SSH tests use short socket timeout (`default_socket_timeout=2`).

## Docker

- `docker compose up -d` → `http://localhost:9123` (compose). `start.sh` uses a standalone `docker run` on port 8123 — different entry, don't mix them up.
- Docker build needs `GIT_ACCES_TOKEN` (private composer dep `mafio69/fast-php-logger`). Set in `.env` or `.config` (both gitignored, see `.env.example`).
- Docker socket mounted at `/var/run/docker.sock` for container log reading.

## Conventions

- Polish is used throughout docs, comments, and commit context. User communication is in Polish.
- `.gitignore` ignores `/node_modules/`, `/playwright-report/`, `/test-results/` (dev/playwright tooling, not the app).