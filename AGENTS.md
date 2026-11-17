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

## Environment and setup

- No `.env.testing` is committed and it may be absent. Without it, tests use the `.env` database and `RefreshDatabase` wipes it. Docker's Postgres init creates a `testing` database; create `.env.testing` with `DB_DATABASE=testing` before running tests locally.
- `composer test` clears cached config first. Before a raw `php artisan test` or `vendor/bin/pest`, run `php artisan config:clear`; a cached `bootstrap/cache/config.php` keeps `APP_ENV` and skips the testing env.
- Start `pgsql` before migrations or tests. `rustfs` is needed for uploads because the default disk is S3; `mailpit` catches local mail. The `app` service auto-migrates and seeds, and `rustfs-init` creates the bucket.
- Browser tests need built assets (`npm run build`) and Chromium (`vpx playwright install chromium`; CI installs only `--only-shell`). Run `php artisan optimize:clear` if Livewire assets 404 from a stale cache.
- A fresh non-local install redirects every request to `/setup` until the seeded `config('setup.initial_email')` user creates the root tenant. Local and testing skip the setup flow.
- Versions come from `mise.toml` (PHP 8.4, Node 24). Use npm; `package-lock.json` is committed. Git hooks in `.vite-hooks/` run `vp staged` and commitlint.

## Architecture

- `App\Models\Entity` is the base for `Person` and `Organization`: soft-deleted, route-keyed by `uuid`, with `owner_organization_id` for the tenant that owns the profile. `User` has one `Person` and no role column.
- The current workspace is a session value (`CurrentOrganization::SESSION_KEY`) resolved by `App\Support\CurrentOrganization`. Livewire pages use `HasCurrentOrganization` for `currentOrganization()`, `isOwner` / `isManager`, `requireOrganization()`, and authorization helpers. Scope tenant queries through those; a user can belong to many organizations.
- Membership is an `organization_relationships` row (`type=member`) with a `role` (Owner / Admin / Member) and an optional date window. `OrganizationRelationshipPeriod` plus `RelationshipTimeline` keep the history; use the existing Actions instead of updating the pivot directly. `Organization::activeOwnerCount()` protects the last active owner.
- Organizations form a tenant tree: root tenant (`is_tenant`, `root_organization_id = null`), subsidiaries, and external counterparties (customer / supplier / vendor). Contacts are `OrganizationRelationship` rows, not separate entities. `Person` / `Organization` profiles can be shared across tenants and are only deleted when not shared (`OrganizationRelationship::canDeleteEntityFor`).
- Authorization: contact abilities live in `AuthorizesContacts` (used by `PersonPolicy` / `OrganizationPolicy` via `Gate`); membership and org-settings checks use the `HasCurrentOrganization` helpers. Owner cannot be granted by a normal role change — only an accepted ownership transfer; an admin leaving needs owner approval.
- Ownership transfers and admin leave requests are `ApprovalRequest`s with a 7-day expiry and one pending request per organization. `ApprovalRequestLinkController` serves signed email links; actions are in `app/Actions/Approvals`.
- Domain events extend `AuditableEvent`; the queued `RecordAuditLog` listener writes `audit_logs`, and `php artisan app:audit-prune` prunes them. Notifications are queued and localized.
- Pennant feature flags are scoped per organization. Wrappers live in `app/Support/Feature/*`; deletion flags default to true in `AppServiceProvider`, and managers toggle them at `/org-settings/features`. Public registration is also a root-tenant flag: the Fortify routes exist but return 404 unless enabled; enabling lets a visitor create a customer tenant via `ProvisionPublicTenant`.
- Fortify handles password reset, email verification, 2FA, and gated registration. Auth screens are Blade under `resources/views/livewire/auth/`, configured in `FortifyServiceProvider`.
- Notification polling: `NotificationPollerController` returns the newest unread notification plus pending IDs tracked in the session; `ack` clears them. The inbox is `App\Livewire\Notifications\Index`.
- UI: class-based Livewire components live in `app/Livewire/**` with views in `resources/views/livewire/**`, routed with `Route::livewire`. The first-party kit is `app/Livewire/Ui/**` plus `resources/views/components/ui/**`, previewed at `/ui` in local/testing. Alpine factories are registered from `resources/js/ui` via `app.ts`; pure date/time/listbox helpers live in `resources/js/util` with colocated Vitest tests.
- Addresses use `creasi/laravel-nusa` through its bundled SQLite `nusa` connection, so there is no seed step. The `validAddress()` test helper builds a valid province → regency → district → village chain.
- Uploads use S3 by default. The browser never sees the pre-signed URL; `/uploads/{path}` streams private objects. Tests use `Storage::fake()`.

## Testing

- Pick the lowest layer that proves the behaviour: colocated Vitest for pure JS helpers, `tests/Unit/View/Ui` with `$this->blade()` for Blade markup, `tests/Feature/Ui` with `Livewire::test()` for rendering, and `tests/Browser` only for interaction, Alpine reactivity, computed layout/touch sizes, accessibility, popovers, and uploads.
- Feature and Browser files opt into `RefreshDatabase`; Unit files do so only when they touch the database.
- Reuse the helpers in `tests/Pest.php`: `actingAsMember()`, `browserMember()`, `contactOrganization()`, `contactIndividual()`, `validAddress()`. Browser tests share the `browserMember()` organization; call `assertNoBackendErrors()` after Livewire round-trips so a hidden 500 does not pass.
- Add a feature test for behaviour changes instead of a script or tinker snippet. Browser tests are for critical end-to-end paths (auth, main flows, accessibility, mobile usability).
- `composer test` does not run Vitest. Run `vp test run` separately. CI runs `vp check`, `vp test`, `vp build`, Pint, PHPStan, then `php artisan test` against PostgreSQL 18.

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

- Before non-trivial work, check `.agents/skills/` and load the matching skill; the directory is gitignored and may be absent.
- Laravel Boost MCP is configured in `opencode.json`. Prefer `search-docs` for framework/package APIs, `database-query` / `database-schema` for read-only SQL, and `browser-logs` for frontend errors.
- New PHP files need `declare(strict_types=1)`; Pint also enforces native function invocation and FQCN imports. Models use Laravel 13 attributes (`#[Fillable]`, `#[Hidden]`, `#[RouteKey]`) instead of properties.
- Do not add dependencies without approval. Only create documentation files when explicitly requested.
- If a frontend change does not appear or Vite reports a missing manifest entry, run `npm run build` (or start `composer run dev`).
