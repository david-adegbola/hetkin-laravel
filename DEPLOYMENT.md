# Deployment

A practical checklist for taking Hetkin live: what to set, where each
credential comes from, and the recommended hosting path. This is the
immediate, get-it-running checklist — Stage 15 of
[MIGRATION_PLAN.md](MIGRATION_PLAN.md) ("Production Readiness") covers the
broader operational-excellence work (load testing, security review,
accessibility audit, etc.) that comes after this.

## A hard architectural constraint, read this first

This app must run under a **standard PHP-FPM-style deployment, one fresh
process per request** — not Laravel Octane, Swoole, RoadRunner, or any
other long-running-worker runtime. `App\Http\Middleware\UseSessionRealm`
(the mechanism that keeps the customer and admin auth realms on separate
session tables/cookies) mutates shared config at the start of every
request; under a persistent worker that config can leak between requests
served by the same worker, letting one realm's session bleed into the
other's. See [MIGRATION_PLAN.md](MIGRATION_PLAN.md)'s ADR-12 for the full
explanation. **Whatever host you choose, do not enable Octane (or any
"Octane as runtime" toggle) unless ADR-12 is revisited first.**

## Where to host it

### Recommended: Laravel Cloud

[Laravel Cloud](https://cloud.laravel.com) is the official managed
platform and the best fit here for two concrete reasons, not just "it's
official":

1. **Its default runtime is standard per-request PHP-FPM-style
   execution** — Octane is an explicit opt-in toggle you leave off. This
   satisfies the constraint above with zero configuration, rather than
   requiring you to remember not to break it.
2. **It natively solves both of this app's real infrastructure gaps**
   (see below) — queue workers and the scheduler are managed services
   you switch on, not something you provision and babysit yourself.

Rough path: connect the GitHub repo → Laravel Cloud detects it's a
Laravel app and provisions a managed Postgres database → set the
environment variables below in its dashboard → it builds
(`composer install`, `npm run build`) and deploys automatically on every
push to your production branch → add a queue worker and enable the
scheduler as separate toggles in the same dashboard (see
[Queue worker](#queue-worker-non-negotiable) and
[Scheduler](#scheduler) below). Zero-downtime deploys, SSL, and load
balancing come with the platform.

### Alternative: Laravel Forge + a VPS

If you'd rather own the server (cheaper at small scale, more control,
more of your own time spent on ops): [Laravel Forge](https://forge.laravel.com)
manages a VPS you provision yourself (DigitalOcean, Hetzner, AWS EC2,
etc.) — it installs PHP-FPM, Nginx, and Postgres, issues SSL via Let's
Encrypt, and gives you one-click toggles for a queue worker daemon and
the scheduler's cron entry (both covered below). Forge's default
provisioning is plain PHP-FPM, not Octane, so it satisfies the
constraint above by default too — just don't enable Forge's separate
Octane option.

Either path works. Cloud trades a usage-based platform fee for near-zero
ops work; Forge trades a flat ~$12–20/month subscription (plus your own
server cost, as low as ~$6/month on Hetzner for a small app) for you
managing the actual box.

### Not recommended

- **Vercel, Netlify, or other Next.js-oriented platforms** — this is a
  PHP application now; nothing about v2's original Vercel deployment
  carries over.
- **Generic serverless-function platforms** unless you've independently
  confirmed they give you a real persistent process for the queue worker
  and scheduler — a stateless function-per-request platform with no
  worker story will leave those two gaps unsolved (see below), and won't
  give you a way to run `queue:work` or `schedule:run` continuously at all.

## The two gaps no `.env` file can fix

Both of these are already fully built into the app (Stage 10's job
commands, Stage 11's queued Account Security notifications) — they just
need a process actually running them.

### Queue worker (non-negotiable)

Four notifications (`PasswordResetRequested`, `PasswordResetCompleted`,
`PasswordChanged`, `LoggedOutAllDevices`) are genuinely queued
(`ShouldQueue`). `QUEUE_CONNECTION=database` only inserts a row into the
`jobs` table — nothing sends until something processes that table.
Without a running worker, every one of those four emails silently never
arrives, in a way that's easy to miss in manual testing (the request
still succeeds; the email just never shows up).

- **Laravel Cloud:** add a Worker in the same dashboard as your web
  service, pointed at the same codebase. Scales independently, costs
  nothing while idle.
- **Forge:** Server → your site → **Queue** tab → add a new queue
  worker (`database` connection, sensible default retry/timeout). Forge
  keeps it running via Supervisor automatically.
- **Bare VPS, no Forge:** run `php artisan queue:work` under Supervisor
  or systemd yourself so it restarts on crash/reboot — never run
  `queue:work` in a plain terminal/tmux session for production traffic.

### Scheduler

`routes/console.php` defines three real jobs: `jobs:ingest-prices`
(daily 05:00 UTC), `jobs:generate-alerts` (daily 06:00 UTC), and
`jobs:run-verification` (monthly, 1st at 03:00 UTC). Laravel's scheduler
only fires them if something calls `php artisan schedule:run` every
minute.

- **Laravel Cloud:** toggle "Scheduler" on for the app — it runs
  natively, no cron entry needed.
- **Forge:** scheduler is on by default for every site (it adds the
  `* * * * * php artisan schedule:run` cron entry for you).
- **Bare VPS:** add that exact crontab line yourself.

## Environment variables: what to set and where to get each one

Copy `.env.example` to `.env` as a starting point (your host's dashboard
env-var UI replaces the file itself in practice, but the same keys
apply). Everything below that isn't marked "optional" is required for
the app to behave correctly in production; optional integrations are
real no-ops when left blank (documented in each `.env.example` comment
and in [README.md](README.md#optional-integrations)) — the app runs
fine without them, just with that feature dark.

### Core (required, no external account needed)

| Variable | Value | Where it comes from |
|---|---|---|
| `APP_KEY` | — | Run `php artisan key:generate` **on the production environment**, once. Never copy your local key. |
| `APP_ENV` | `production` | — |
| `APP_DEBUG` | `false` | — (leaving this `true` in production leaks stack traces/env values to anyone who triggers an error) |
| `APP_URL` | `https://hetkin.app` | Your real domain |
| `APP_DOMAIN` | `hetkin.app` | Same domain, no scheme — drives the `dashboard.`/`admin.` subdomain routing (ADR-21) |
| `SESSION_DOMAIN` | `.hetkin.app` | Leading dot — shares the session cookie across the apex and both subdomains |
| `SESSION_SECURE_COOKIE` | `true` | — (only correct once `trustProxies` — already wired in `bootstrap/app.php` — is actually sitting behind a real TLS-terminating proxy) |
| `LOG_LEVEL` | `warning` (or `error`) | — `debug`'s local-dev default is too noisy for production logs |

### Database (required)

| Variable | Where it comes from |
|---|---|
| `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | **Laravel Cloud:** provisioned automatically, connection details injected for you. **Forge:** Server → Database, or point at a separate managed Postgres (DigitalOcean/AWS RDS) and enter its credentials here. |

### DNS-dependent (required once your domain is live)

`APP_URL`/`APP_DOMAIN`/`SESSION_DOMAIN` above all assume your domain's
DNS already points at your host — set that up first (Laravel
Cloud/Forge both walk you through the A/CNAME records to add at your
registrar).

### Email — Resend (strongly recommended; app works without it, but every email silently just logs instead of sending)

1. Sign up at [resend.com](https://resend.com), go to **Domains → Add
   Domain**, enter `hetkin.app`.
2. Add the DNS records Resend shows you (SPF + DKIM, both TXT/CNAME) at
   your domain registrar, then click **Verify** in Resend's dashboard.
   Until verified, Resend silently refuses to deliver to anyone except
   your own account's address — this is the exact gap `.env.example`'s
   own comment on `MAIL_FROM_ADDRESS` already warns about.
3. **API Keys → Create API Key**, "Sending access" scope is enough →
   set as `RESEND_API_KEY`.
4. Set `MAIL_MAILER=resend` (it's `log` by default, which is correct
   for local dev but must change here).
5. `CONTACT_EMAIL` — a real inbox you control (e.g. `hello@hetkin.app`);
   this is where new pilot-application alerts get sent.

### Day-ahead electricity prices — ENTSO-E (required for the pricing/optimization features to have real data; the app runs without it, but price ingestion fails closed with a clear error)

1. Register an account at the [ENTSO-E Transparency
   Platform](https://transparency.entsoe.eu).
2. Email `transparency@entsoe.eu` with subject **"Restful API access"**,
   body stating the email address you registered with. ENTSO-E's
   helpdesk typically responds within a few business days.
3. Once granted, log in → **My Account Settings** → generate your
   security token.
4. Set as `ENTSO_E_TOKEN`.

### Analytics — PostHog (optional)

1. Sign up at [posthog.com](https://posthog.com), create a project
   (pick the EU or US data region — this app's default host,
   `POSTHOG_HOST`/`VITE_POSTHOG_HOST`, is already set to
   `https://eu.i.posthog.com`; change it if you picked US).
2. **Project Settings → Project API Key** → set as both `POSTHOG_KEY`
   (server) and `VITE_POSTHOG_KEY` (client — same value, just also
   needs the `VITE_` prefix so Vite bundles it into the browser build).

### Progressive Web App — `APP_VERSION` (optional, but recommended)

Set `APP_VERSION` to your CI's git commit SHA on every deploy. It's
purely informational (a "which build is this" value) — the app runs
identically without it, falling back to the literal string `dev` — but
setting it is what makes that value actually meaningful in production.
See [PWA.md](PWA.md) for the full reasoning and why it's deliberately
**not** wired into update detection itself.

### Error tracking — Sentry (optional)

1. Sign up at [sentry.io](https://sentry.io), create a project (or one
   project covering both — Sentry lets a single DSN receive events from
   multiple platforms).
2. **Settings → Projects → (your project) → Client Keys (DSN)** → copy
   the DSN, set as both `SENTRY_LARAVEL_DSN` (server) and
   `VITE_SENTRY_DSN` (client — same value again, `VITE_` prefix for the
   same reason as PostHog above).

## Deploy-time build steps

Whichever host you use, these need to happen on every deploy (Laravel
Cloud and Forge both do this automatically once configured; a
from-scratch setup needs a script running these in order):

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## First-deploy checklist

- [ ] DNS for the apex domain and `dashboard.`/`admin.` subdomains
      points at your host
- [ ] `APP_KEY` generated fresh on the production environment
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_URL`, `APP_DOMAIN`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`
      all set per the table above
- [ ] Postgres provisioned and `DB_*` vars set; `php artisan migrate --force` run
- [ ] Queue worker running (see [Queue worker](#queue-worker-non-negotiable)) —
      verify by triggering a password reset and confirming the email
      actually arrives, not just that the request succeeds
- [ ] Scheduler running (see [Scheduler](#scheduler)) — verify the next
      day by checking `admin/jobs` shows a recent `ingest-prices` run
- [ ] Resend domain verified, `RESEND_API_KEY` + `MAIL_MAILER=resend` set
- [ ] `ENTSO_E_TOKEN` set (or knowingly deferred — price ingestion fails
      closed, not silently, until it is)
- [ ] Confirm `bootstrap/app.php`'s `trustProxies(at: '*')` is actually
      sitting behind a real proxy — sign in and confirm the session
      cookie is flagged `Secure` in the browser's dev tools
- [ ] Octane is **not** enabled on whichever host you chose (see the
      constraint at the top of this document)
- [ ] Sign in as both a customer and an admin account on the live URL
      end to end, same as the [README.md](README.md#test-accounts)
      local checklist
- [ ] `APP_VERSION` set to this deploy's commit SHA (see
      [PWA.md](PWA.md#versioning--deliberately-not-what-drives-update-detection))
- [ ] Signed into the portal, confirm the browser offers to install it
      (or Chrome DevTools → Application → Manifest shows no errors); repeat
      for the admin console — confirm the two install as separate apps,
      not one covering both, and that the marketing site never does
