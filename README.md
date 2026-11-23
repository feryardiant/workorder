[![License](https://img.shields.io/github/license/feryardiant/workorder?style=flat-square)](https://github.com/feryardiant/workorder/blob/main/LICENSE)
[![Actions Status](https://img.shields.io/github/actions/workflow/status/feryardiant/workorder/tests.yml?branch=main&style=flat-square)](https://github.com/feryardiant/workorder/actions)
[![Coverage Status](https://img.shields.io/coveralls/github/feryardiant/workorder/main?style=flat-square&logo=coveralls)](https://coveralls.io/github/feryardiant/workorder)

# Workorder

Multi-tenant CRM for organizations, people, memberships, approvals and project work.

## Features

- **Tenants and members** — a root tenant, subsidiaries, and memberships with
  `Owner` / `Admin` / `Member` roles. The last active owner is protected in the
  database and an ownership transfer needs an approved request.
- **Contacts** — organizations and individuals as customers, suppliers or
  vendors, with relationship periods kept as history.
- **Projects** — project kinds, tasks and subtasks, assignments, postponement,
  attachments and a per-project activity timeline.
- **Audit and notifications** — an audit log for audited domain events and a
  database-backed notification inbox.
- **Per-organization feature flags** — including the flags that gate contact and
  member deletion and public registration.
- **Auth** — Fortify with a `handle` login field, email verification, password
  reset, 2FA, and registration that stays disabled unless the root tenant enables
  it.

The UI is Blade + Livewire 4 + Flux UI 2, styled with Tailwind CSS 4. The default
locale is Indonesian with English fallback.

## Stack

| Layer    | Choice                                              |
| -------- | --------------------------------------------------- |
| Backend  | PHP 8.4, Laravel 13, PostgreSQL 18                  |
| Frontend | Livewire 4, Flux UI 2, Tailwind CSS 4, TypeScript   |
| Auth     | Laravel Fortify, Pennant feature flags              |
| Tooling  | Vite+ (`vp`), Pest 5 + Pest Browser, Larastan, Pint |

## Requirements

- Docker (PostgreSQL, Mailpit and RustFS run from `docker-compose.yaml`)
- PHP 8.4 and Composer
- Node.js 24 and npm

`mise install` installs the PHP and Node versions pinned in `mise.toml`.

## Getting started

### Docker (app and services)

Prepare the environment and PHP dependencies once, then start the stack:

```sh
cp -n .env.example .env
composer install
php artisan key:generate
docker compose up -d --build
```

The `app` service runs `npm ci`, starts Vite, creates the S3 bucket, and runs
migrations and the seeder on boot. Open the app at `APP_URL` (default
<http://localhost:8080>) and sign in with `owner` and password `password`. The
local seeder also creates `admin` and `member1` through `member3` with the same
password. Non-local environments redirect to `/setup` until the root tenant
exists.

### PHP and Node on the host

Alternatively, start only the backing services and run the app on the host:

```sh
docker compose up -d pgsql mailpit rustfs
docker compose run --rm rustfs-init # creates the S3 bucket, once per volume
composer setup
npm ci
php artisan db:seed
composer run dev
```

`composer run dev` runs `php artisan dev`, which starts the HTTP server, queue
worker, log tail and Vite together.

### Services

Every port is overridable in `.env`.

| Service        | Default                 | Env           |
| -------------- | ----------------------- | ------------- |
| App            | <http://localhost:8080> | `APP_PORT`    |
| Vite           | 5173                    | `DEV_PORT`    |
| PostgreSQL     | 5432                    | `DB_PORT`     |
| Mailpit (SMTP) | 1025                    | `MAIL_PORT`   |
| RustFS (S3)    | 9000                    | `AWS_S3_PORT` |

Uploads use the `s3` disk by default, served by RustFS in development. The
component gallery is available at `/ui` in `local` and `testing`.

## Commands

| Task                                | Command                                                                |
| ----------------------------------- | ---------------------------------------------------------------------- |
| Dev processes                       | `composer run dev` (`php artisan dev:list` lists them)                 |
| Full backend gate                   | `composer test` — Pint, PHPStan, Pest (Unit, Feature, Browser)         |
| Feature / unit / browser tests      | `composer test:feature`, `composer test:unit`, `composer test:browser` |
| One test file                       | `php artisan test --compact tests/Feature/DashboardTest.php`           |
| PHP format / lint / static analysis | `composer format` / `composer lint` / `composer check`                 |
| Frontend lint / format              | `npm run lint` / `npm run format`                                      |
| Frontend unit tests / build         | `npm test` / `npm run build`                                           |

See [AGENTS.md](AGENTS.md) for the database, tenancy and testing details.

### Tests

Copy `.env` to `.env.testing` and set `DB_DATABASE=testing`; a fresh PostgreSQL
volume creates the `testing` database automatically. Run `php artisan config:clear`
before a raw `php artisan test` or `vendor/bin/pest`. `composer test` already
clears it.

Browser tests need built assets (`npm run build`) and Chromium
(`vpx playwright install chromium`). CI installs only the headless shell.

## Worktrees

Zed creates linked worktrees and runs `scripts/create-worktree.sh`, giving each
one its own ports, session cookie and PostgreSQL databases while sharing the
main compose services. Re-running the script repairs an interrupted setup and
keeps the assigned ports. See [AGENTS.md](AGENTS.md#worktrees).

## CI and images

- `.github/workflows/tests.yml` runs the quality gate on pushes and pull requests
  to `main`: Vite+ check/test/build, Pint, PHPStan and Pest against PostgreSQL 18
  with headless Chromium.
- `.github/workflows/dist.yml` builds and publishes
  `ghcr.io/feryardiant/workorder` after a successful test run.
- `.github/workflows/release.yml` publishes a release from `v*.*.*` tags.
- `.github/workflows/prune-images.yml` cleans up old PR preview images.

To smoke-test the staging image locally, use
`docker compose -f docker-compose.staging.yaml up -d --build`.

## License

Workorder is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
