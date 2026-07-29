# Hetkin (Laravel)

Hetkin helps manufacturing companies shift flexible production to cheaper
electricity hours. This repository is a from-scratch rewrite of the
original Next.js app (`hetkin-v2`) onto Laravel — same behavior, same
URLs, same product, new stack. See [MIGRATION_PLAN.md](MIGRATION_PLAN.md)
for the full staged plan and the architecture decision record (ADR) log
behind every non-obvious choice made along the way.

**Status:** Stages 1–12 (the full migration) are complete and verified —
487 passing tests, clean `tsc`/ESLint/Pint, a clean production build.
Stage 13 (post-migration technical modernization) has not started.

## Stack

- **Backend:** Laravel 12, PHP 8.3+, PostgreSQL
- **Frontend:** Inertia.js, React 19, TypeScript, Tailwind CSS 4
- **Testing:** Pest
- **Build:** Vite

## Requirements

- PHP 8.3+ with the usual Laravel extensions (`pdo_pgsql`, `mbstring`,
  `openssl`, etc.)
- [Composer](https://getcomposer.org)
- Node 20+ and npm
- PostgreSQL (recommended — matches production) **or** nothing extra at
  all if you use SQLite instead (see below)

No Redis, Memcached, or other services are required — sessions, cache,
and the queue all use the `database` driver locally.

## Setup

```bash
git clone <this-repo>
cd hetkin-laravel
composer install
npm install
cp .env.example .env
php artisan key:generate
```

### Database

`.env.example` defaults to SQLite, which needs no setup at all:

```bash
touch database/database.sqlite
```

To use PostgreSQL instead (what this app actually runs against in every
other environment), edit `.env`:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=hetkin_laravel
DB_USERNAME=<your-user>
DB_PASSWORD=
```

then create the database (`createdb hetkin_laravel`) before migrating.

### Migrate and seed

```bash
php artisan migrate --seed
```

This creates every table and seeds two test accounts you can sign in
with immediately — see [Test accounts](#test-accounts) below. The
seeder never runs automatically outside of you explicitly asking for
`--seed` or running `php artisan db:seed`.

### Build the frontend once

```bash
npm run build
```

(Only needed if you're not running `npm run dev` — see below.)

## Running it

The simplest way to run everything at once (Laravel server, queue
worker, log viewer, and Vite dev server together):

```bash
composer run dev
```

This starts:

| Process | What it does |
|---|---|
| `php artisan serve` | The app itself, at `http://localhost:8000` |
| `php artisan queue:listen` | Processes queued jobs (see [Queue](#queue) below) |
| `php artisan pail` | Live-tails the log |
| `npm run dev` | Vite dev server with HMR |

Open `http://localhost:8000`.

### Subdomains

The customer portal and admin console are also reachable on
`dashboard.localhost:8000` and `admin.localhost:8000` respectively
(bare paths, no `/dashboard` or `/admin` prefix needed) — this mirrors
how the app is actually deployed (`dashboard.hetkin.app` /
`admin.hetkin.app`). Modern browsers resolve any `*.localhost` hostname
to `127.0.0.1` automatically, no `/etc/hosts` edit required. The
main-domain equivalents (`localhost:8000/dashboard/...`,
`localhost:8000/admin/...`) work identically — pick whichever you
prefer, both exercise the same routes.

## Test accounts

Created by `php artisan migrate --seed` (see
[database/seeders/DatabaseSeeder.php](database/seeders/DatabaseSeeder.php)).
Local dev only — never seeded in production.

| Realm | URL | Email | Password |
|---|---|---|---|
| Admin console | `localhost:8000/admin/sign-in` or `admin.localhost:8000/sign-in` | `admin@hetkin.test` | `password` |
| Customer portal | `localhost:8000/sign-in` or `dashboard.localhost:8000/sign-in` | `owner@hetkin.test` | `password` |

The admin account has the full `Admin` role, so every admin-console
page is reachable (Overview, Pilots, Organizations, Baselines,
Verification, Invoices, Jobs, Audit Log, Team, Docs). The customer
account owns one seeded organization ("Acme Manufacturing Oy",
`ACTIVE` status) with no data yet — the dashboard, schedule, and data
pages all show empty states by design (a brand-new account looking
empty is correct, documented behavior, not a bug). Upload a CSV, add a
schedule block, and run optimization through the UI to populate it
for real.

## What to look at

- **Marketing site** (`/`, `/pricing`, `/faq`, `/how-it-works`,
  `/trust`, `/methodology`, `/pilot-program`, `/beta`, `/docs`,
  `/legal/*`) — fully public, no login needed.
- **Pilot application** (`/pilot-program/apply`) — submits a real
  application into the admin's Pilot Applications review queue.
- **Customer portal** — sign in as `owner@hetkin.test`: Dashboard,
  Schedule, Data (CSV upload), Reports, Billing, Team.
- **Admin console** — sign in as `admin@hetkin.test`: review the pilot
  application you just submitted, approve it, watch it become an
  organization.
- **Account security** — password reset, password change, active
  session list/revoke — under the customer portal's Settings.

## Running tests

```bash
composer test
# or directly:
php artisan test
```

487 tests, ~1,500 assertions, using an in-memory SQLite database (see
`phpunit.xml`) regardless of what `.env` is configured with — the test
suite never touches your dev database.

Frontend checks:

```bash
npx tsc --noEmit          # type-check
npm run lint              # ESLint, --fix
./vendor/bin/pint         # PHP formatting (add --test to check without fixing)
```

## Queue

Every notification in this app sends synchronously (inline, in the
same request) **except** the four Account Security notifications
(password reset requested/completed, password changed, logged out of
all devices), which are genuinely queued. `composer run dev` runs
`queue:listen` for you automatically; if you're running `php artisan
serve` on its own instead, start a worker separately or those four
emails will sit in the `jobs` table and never send:

```bash
php artisan queue:listen --tries=1 --timeout=0
```

All outgoing mail is logged to `storage/logs/laravel.log` rather than
actually sent (`MAIL_MAILER=log`) until `RESEND_API_KEY` is set.

## Optional integrations

None of these are required to run or test the app locally — every one
of them is a documented no-op when unconfigured:

| Integration | Env vars | Behavior when unset |
|---|---|---|
| Resend (email) | `RESEND_API_KEY` | Logs to `storage/logs/laravel.log` instead of sending |
| PostHog (analytics) | `POSTHOG_KEY`, `VITE_POSTHOG_KEY` | No-op, no network calls |
| Sentry (error tracking) | `SENTRY_LARAVEL_DSN`, `VITE_SENTRY_DSN` | No-op, no network calls |
| ENTSO-E (day-ahead prices) | `ENTSO_E_TOKEN` | Price-ingestion job fails closed with a clear error, rather than silently doing nothing |

## Project structure

Domain-driven, under `app/Domain/{Analytics,Alerts,Pricing,Auth,Audit,
Organizations,Metering,Scheduling,Optimization,Billing,Pilots,Jobs}/
{Actions,Models,Enums,DTOs,Services,Contracts,Providers,Exceptions,
Strategies}`. Inertia pages live in `resources/js/Pages`, organized by
the same realms as the routes (`Portal/`, `Admin/`, `Marketing/`,
`PilotProgram/`).

For *why* something is built the way it is, check
[MIGRATION_PLAN.md](MIGRATION_PLAN.md) — every non-obvious decision has
a numbered ADR explaining the reasoning and, where relevant, the v2
behavior it's matching.
