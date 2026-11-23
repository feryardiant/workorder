# Workorder

Laravel 13 / PHP 8.4 multi-tenant CRM for organizations, people/contacts, memberships, approvals, audit history, and a database notification inbox. UI is Blade + Livewire 4 + Flux UI 2 + Tailwind 4; auth is Fortify.

## Commands

| Task | Command |
| --- | --- |
| First-time setup | `docker compose up -d pgsql mailpit rustfs`, then `composer setup` and `npm ci` |
| Dev processes | `composer run dev` (same as `php artisan dev`); `php artisan dev:list` shows the processes |
| Full backend gate | `composer test` — Pint check → PHPStan → clears config → Pest (Unit, Feature, Browser) |
| Feature / unit / browser tests | `composer test:feature`, `composer test:unit`, `composer test:browser` (extra args forwarded) |
| One test file / test | `php artisan test --compact tests/Feature/DashboardTest.php` / `vendor/bin/pest <file> --filter=testName` |
| Backend format / lint | `composer format` / `composer lint`; `vendor/bin/pint --dirty --format agent` formats only changed files |
| Static analysis | `composer check` (Larastan level 7: `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`) |
| Frontend lint / format | `npm run lint` / `npm run format` (Vite+ `vp check`, type-aware) |
| Frontend unit tests / build | `npm test` (`vp test run`) / `npm run build` |

- Before running tests outside `composer test`, run `php artisan config:clear`. A cached `bootstrap/cache/config.php` freezes `APP_ENV`, so `.env.testing` is skipped and `RefreshDatabase` wipes the development database. `composer test` already clears it.
- `.env` and `.env.testing` are gitignored; `.env.testing` sets `DB_DATABASE=testing` and `APP_URL=http://localhost:8080`. `scripts/docker/init-database.sql` already creates the `testing` database on the first `docker compose up` (fresh `pgsql-data` volume), but the env file is not: copy `.env` to `.env.testing` and set `DB_DATABASE=testing` before running any test. Without it, tests use the `.env` connection and `RefreshDatabase` drops the development database.
- Browser tests need built assets and Chromium: `npm run build`, then once per clone `vpx playwright install chromium`. CI installs only the headless shell (`--only-shell`); install the full browser locally for headed debugging.
- Use npm: `package-lock.json` is committed (pnpm/yarn lockfiles are ignored). Versions come from `mise.toml` (PHP 8.4, Node 24).
- Lint, format, and staged-file settings live in `vite.config.ts`; `npm run *` delegates to Vite+ (`vp`). Commitlint is configured in `package.json`; there are no ESLint/Prettier config files.
- Pint enforces `declare(strict_types=1)`, `native_function_invocation`, and FQCN imports. Add strict types to new PHP files and let Pint fix the rest.
- `php artisan db:seed` only seeds when `users` is empty. Locally/testing it creates `owner`, `admin`, and `member1..3` (password `password`); `DemoSeeder` adds demo tenants only in `local`. Non-local seeding creates the `setup.initial_email` placeholder used by `/setup`.
- Log in with the username (`handle`) `owner`, `admin`, or `member1..3`, never the email; `owner@example.com` and the rest are mail addresses only. Fortify's username field is `handle` (`config/fortify.php`), and browser sign-in fills that field (`tests/Browser/AuthTest.php`).

## Worktrees

Zed creates linked worktrees under `../_.Worktrees/{project}/{name}/{project}` and runs `scripts/create-worktree.sh` through the `create_worktree` hook in `.zed/tasks.json`. The script gives each worktree its own runtime identity so an agent can preview and test without touching the main checkout:

- `.env`, `.env.testing`, and `boost.json` are copied once; `.agents/{plans,specs,skills}` are symlinked to the main checkout.
- `.env` gets `APP_PORT`/`SERVER_PORT` (81xx), `DEV_PORT` (52xx), `APP_URL`, `SESSION_COOKIE`, and `DB_DATABASE`; `.env.testing` gets its own `DB_DATABASE`. `php artisan dev` serves on `APP_URL`.
- The dev database is `wt_<worktree>-dev` and tests use `wt_<worktree>-test`, both created in the main compose `pgsql` service. They are migrated on setup (dev is seeded), so migrations and tests never touch the main or `testing` database.
- `composer install` and `npm install` run on every setup (idempotent, so a re-run repairs an interrupted one), and assets are built, so `composer test` runs immediately.
- Re-running `bash scripts/create-worktree.sh` with the Zed variables repairs an interrupted setup and keeps the assigned ports.

Never run `docker compose` from a worktree: the project name is pinned, so it would reconfigure the main stack. The main stack must be running (`docker compose up -d pgsql mailpit rustfs`) because worktrees share it through `127.0.0.1`.

## Architecture

- `routes/web.php` is the whole surface: `/setup`; `/` → `/dashboard`; `auth` + `verified` for dashboard, members, contacts, `org-settings/*`, and notifications; `auth` only for profile and stored uploads; preferences plus settings organizations/security also require `verified`, and the last two require `password.confirm`. `routes/ui` registers the component gallery only in `local`/`testing`.
- Tenancy: an `Organization` is both a tenant and a contact. `is_tenant` + `root_organization_id` mark tenants (one root tenant, enforced by a PostgreSQL partial unique index); `owner_organization_id` marks the tenant that owns a row. The active workspace is `App\Support\CurrentOrganization`, stored in the session; Livewire components use `HasCurrentOrganization::currentOrganization()` and must scope queries through it.
- Domain model: `Entity` is the base for `Organization` and `Person` (UUID route key, soft deletes, `owner_organization_id`). `OrganizationRelationship` is the pivot for every relationship: Member/Subsidiary are internal, Customer/Supplier/Vendor are external contacts. Membership role lives on the `member` pivot. Actions that change these relationships use `RelationshipTimeline` to open/close `organization_relationship_periods`.
- Authorization: `App\Enums\Role` is int-backed (`Owner=0`, `Admin=1`, `Member=2`). Livewire uses the `HasCurrentOrganization` helpers (`isOwner`, `isManager`, `authorizeManager`, `authorizeOwner`, `authorizeTarget`); contact abilities are in `OrganizationPolicy`/`PersonPolicy` through `AuthorizesContacts`. Deletion is also gated by Pennant features. A single owner per organization is enforced by `activeOwnerCount()` and a partial index, not just convention.
- Approvals: ownership transfer and an admin's self-leave are two-step `ApprovalRequest`s (7-day expiry, one pending per organization) handled by `App\Actions\Approvals\*`; signed email links hit `ApprovalRequestLinkController` and open `settings/organizations?approval=...`.
- Audit: audited events extend `App\Events\AuditableEvent`; `AppServiceProvider` maps the events to the queued `RecordAuditLog` listener. Add every new auditable event to that list. `app:audit-prune --keep=30 [--dry-run]` trims history.
- Feature flags: per-organization Pennant flags live in `App\Support\Feature\*` and are toggled on `org-settings/features`. `ContactDeletion` and `MemberDeletion` default to true (defined in `AppServiceProvider`); the others default to false.
- Auth: Fortify's username is `handle` (not email) and the home path is `/dashboard`; enabled features are password reset, registration, email verification, and 2FA. Registration is real but `EnsureRegistrationEnabled` returns 404 for the register routes unless the root tenant enables the `registration` flag; `ProvisionPublicTenant` turns a public registration into a sub-tenant owned by that person, not a member of the root tenant.
- Invites and setup: adding a member with an email sends `VerifyEmailForNewUser`; the signed link verifies the address and redirects to password reset so the invitee sets a password. Fresh non-local installs redirect to `/setup` while the root tenant is missing and the seeded placeholder user exists; `ProvisionTenant` converts that user into the root owner (local/testing skip setup).
- Notifications: the inbox is `App\Livewire\Notifications\Index`. `NotificationPollerController` returns the newest unread notification plus a pending list, tracks seen/pending IDs in the session, and `ack` clears them.
- UI: Livewire 4 components are class-based (`app/Livewire/**` + `resources/views/livewire/**`), routed by class via `Route::livewire()`. Shared Blade components are under `resources/views/components/{ui,page,...}`; the custom UI kit also lives in `app/Livewire/Ui` and `resources/js/ui`, with the local-only `/ui` gallery. Layouts are in `resources/views/layouts/`; local Flux overrides are in `resources/views/flux/`.
- `User` uses Laravel 13 attributes (`#[Fillable]`, `#[Hidden]`, `#[RouteKey('handle')]`); update the attributes when adding fields. `resources/js/app.ts` starts the notification poller and registers the Alpine UI kit; styles are in `resources/css/app.css` (Tailwind 4 `@theme` plus the Flux import).

## Database and tests

- CI (`.github/workflows/tests.yml`) runs `vp check`, `vp test`, `vp build`, then Pint, PHPStan, and `php artisan test` against PostgreSQL 18 with headless Chromium. Tests must keep portability in mind: framework tables (`sessions`, `cache`, `jobs`) live in the separate `system` schema, and `create_system_schema` is PostgreSQL-only.
- Migrations use PostgreSQL partial indexes (`organizations_root_tenant_unique`, `org_relationship_*_unique`) for the single root tenant, single owner, and single subsidiary relation. Don't drop those guards when changing the schema.
- `phpunit.xml` deliberately leaves DB env vars unset, so tests use `.env.testing` (or `.env`). Most Feature/Browser tests and database-touching Unit tests opt into `RefreshDatabase` with `uses(RefreshDatabase::class)`; a local run wipes that database, so never point tests at data you need.
- Fortify feature tests use `$this->skipUnlessFortifyHas(...)` from `tests/TestCase.php` instead of asserting on disabled features.
- `tests/Pest.php` has the shared fixtures: `actingAsMember`, `contactOrganization`, `contactIndividual`, `browserMember`, `validAddress`, and `assertNoBackendErrors`. Browser tests live in `tests/Browser` (Pest Browser + Playwright) and run inside `composer test`; sign in with `actingAs($user)` from `Pest\Laravel` and open pages with `visit()`. Run `php artisan optimize:clear` when Livewire assets 404 on a stale cache.
- Pick the lowest test layer that proves the behavior:
  - pure JS helpers → a colocated Vitest file (`vite-plus/test`, no DOM), for example `resources/js/util/date.test.ts`;
  - Blade markup and server-rendered attributes → `tests/Unit/View/Ui` with `$this->blade()`;
  - Livewire rendering → `tests/Feature/Ui` with `Livewire::test()`;
  - `tests/Browser` only for interaction, Alpine reactivity, computed layout/touch sizes, accessibility audits, popovers, and uploads.
- `composer test` does not run Vitest; run `vp test run` separately.
- Add a feature test for behavior changes instead of writing a verification script or tinker snippet; reserve browser tests for critical end-to-end paths.

## Boost MCP

- `opencode.json` and `.zed/settings.json` start `php artisan boost:mcp`. Prefer Boost tools over shell or manual guesses: `search-docs` for Laravel/Livewire/Flux APIs, `database-query` for read-only SQL, `database-schema` before schema changes, `browser-logs` for frontend errors, and `get-absolute-url` before sharing a URL.

## Localization

- Locale defaults to `id` with `en` fallback. `SetUserLocale` applies the user's locale and the date locale; notifications use the recipient's preferred locale, dates are stored in UTC and rendered in the user's timezone.
- Write app strings in English with `__('English text')`; the exact text is the key in `resources/lang/id.json`. Update the matching key when changing the English text.
- UI kit strings live in `resources/lang/{id,en}/ui.php` as `__('ui.{component}.{part}')` with kebab-case segments. Framework groups (`auth`, `passwords`, `pagination`, `validation`) live in `resources/lang/id/*.php`; do not add other English translation files outside `resources/lang/en/ui.php`.
- Preserve placeholders such as `:name`, `:role`, and `:count`. Tests asserting translated output should use Indonesian values and set the locale explicitly when needed.

## Specs and plans

- Specs live in `.agents/specs/`, plans in `.agents/plans/`. One file per document.
- Name: `NNN_dashed-slug-spec.md` or `NNN_dashed-slug-plan.md` — the next free three-digit number in that directory (starting `001`), an underscore, the dashed slug, then the `-spec` / `-plan` suffix.
- Every document starts with frontmatter holding `status` and `summary`:

```yaml
---
status: draft
summary: One or two sentences a reviewer can scan.
---
```

## Conventions

- Create every new file under `app/` or `database/` with the matching `php artisan make:*` command, including `make:class` when no specific generator exists; do not create these files by hand.
- Before starting any task, check `.agents/skills/` and read the skill whose description matches the work. That directory is gitignored, so it may be absent; never assume this file names every available skill. When present, `.agents/specs` and `.agents/plans` hold the design notes for entities, tenancy, contacts, and UI.
- Git hooks in `.vite-hooks/` run `vp staged` before commits (auto-formats staged files) and commitlint on the message; use Conventional Commits.
- Clickable controls use `pointer` cursors and popover containers use `default` (base rules in `resources/css/app.css`). Put touch-only affordances behind `[@media(hover:none)]`; `->on()->mobile()` emulates `hover: none` and `pointer: coarse`.
- Do not add dependencies without approval.
- Only create documentation files when explicitly requested.
- If a frontend change does not appear or Vite reports a missing manifest entry, run `npm run build` (or start `npm run dev` / `composer run dev`).
