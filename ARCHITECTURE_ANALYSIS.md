# Hetkin v2 — Architecture Analysis & Laravel Migration Roadmap

Read-only analysis of the current codebase (`hetkin-v2`, Next.js) produced to
scope a rewrite onto Laravel + Inertia + React + TypeScript + Tailwind + Pest
(`hetkin-laravel`). No source files were modified to produce this document.

## Stack

Next.js 16 (App Router) + React 19 + TypeScript, Prisma 6 + PostgreSQL
(Neon), Better Auth, Tailwind 4, Vitest + Playwright + fast-check
(property-based tests), Sentry, PostHog, Resend, Vercel Cron. No separate
backend service — Route Handlers and Server Actions only.

## Business Domain

Hetkin is a B2B energy demand-response / production-scheduling optimization
SaaS for Finnish industrial sites (cold storage, metal treatment, plastics
molding, wood processing, water treatment, food processing, chemical batch).
It ingests day-ahead electricity prices (ENTSO-E), lets customers submit
production schedules, runs an optimization engine to recommend cheaper time
windows, and bills a **revenue-share on verified savings**, with a heavy
emphasis on auditability: a hash-chained audit log, immutable locked
baselines, and shadow-mode verification before any invoice is generated.

Sub-domains:

1. **Pilot funnel** — public application → admin review/ICP scoring → org
   provisioning → invite.
2. **Metering** — sites, metering points (Datahub or manual CSV upload),
   hourly readings.
3. **Scheduling** — versioned production schedules with blocks, never
   overwritten.
4. **Optimization & recommendations** — engine proposes shifted time
   windows; scheduler accepts/rejects/partially-adopts, with reasons.
5. **Baseline / savings assessment / verification / billing** — locked
   baseline → savings assessment (PDF report) → monthly shadow-then-live
   verification → invoice (never manually entered).
6. **Admin / back-office** — dual-realm staff console: orgs, baselines,
   verification review, invoices, pilots, team, audit log, job monitor.
7. **Audit & alerts** — append-only hash-chained audit log; alert types for
   data gaps, opportunities, adoption trends, variance flags, report-ready.

## Database Schema (Prisma → PostgreSQL)

Two separate auth realms, deliberately isolated tables/cookies/secrets:

- **Customer realm**: `User`, `Session`, `Account`, `Verification`,
  `Organization`, `Member`, `Invitation` (Better Auth + organization plugin).
- **Internal staff realm**: `AdminUser`, `AdminSession`, `AdminAccount`
  (`AdminRole`: ADMIN / VERIFICATION_ANALYST / SUPPORT / AUDITOR).

Domain tables: `PilotApplication`, `Site`, `MeteringPoint`, `MeterReading`
(partitioned via raw SQL migration), `ElectricityPrice`,
`ProductionSchedule`, `ScheduleBlock`, `OptimizationRun`, `Recommendation`,
`RecommendationDecision`, `Baseline`, `SavingsAssessment`,
`VerificationRecord`, `Invoice`, `AuditLogEntry`, `Alert`,
`OrganizationNote`, `JobRun`.

Invariants encoded in schema comments (must be preserved in any rewrite):

- Money is always an integer count of cents (EUR) — never a float.
- A `Baseline` is immutable once `status = LOCKED`; superseding creates a
  new row.
- An `Invoice` is only ever generated from a `VerificationRecord` — there is
  structurally no path from a hand-typed number to a customer bill.
- `AuditLogEntry` is append-only and hash-chained (`hash` covers its own
  content plus `previousHash`); only `src/lib/audit.ts` is permitted to
  write to it.
- Row-level security policies are applied via a hand-written SQL migration
  (`20260724230000_row_level_security_policies` +
  `prisma/manual-migrations/force-row-level-security.sql`), not by Prisma's
  schema DSL.

## Authentication

- Better Auth, email+password only, no public self-serve signup — accounts
  are created only via admin-issued invites.
- Two fully separate `betterAuth()` instances (`src/lib/auth.ts`,
  `src/lib/auth-admin.ts`): different secrets (`BETTER_AUTH_SECRET` vs
  `BETTER_AUTH_ADMIN_SECRET`), different cookie prefixes (`hetkin_customer`
  / `hetkin_admin`), different session lifetimes (30 days vs 8 hours),
  different rate limits (20/min vs 10/min).
- Customer realm adds the `organization` plugin (multi-tenant, membership,
  invitations); roles via `OrgRole` (OWNER / SCHEDULER / FINANCE / VIEWER).
- Admin realm has no org plugin; flat `AdminRole`.
- `cookieCache` used on both realms to avoid a DB round-trip on every
  navigation.
- Cross-subdomain cookie sharing is gated by the `COOKIE_DOMAIN` env var.

## Routing / Multi-tenancy Plumbing

- `src/proxy.ts` (Next 16's renamed `middleware.ts`) rewrites `dashboard.*`
  → `/dashboard` and `admin.*` → `/admin` by hostname prefix, with
  passthrough rules for sign-in pages and metadata routes (favicon,
  manifest, robots.txt, etc.).
- `src/lib/subdomain-server.ts` / `subdomain-client.ts` generate
  subdomain-correct links dynamically instead of hardcoding `/dashboard` or
  `/admin` prefixes.

## Pages (App Router)

**Public marketing/legal**: `/`, `/pricing`, `/how-it-works`, `/methodology`,
`/faq`, `/trust`, `/docs`, `/beta`, `/legal/{terms,privacy,cookies}`,
`/pilot-program`, `/pilot-program/apply`.

**Auth**: `/sign-in`, `/admin/sign-in`, `/invite/[invitationId]`.

**Customer portal** (`(portal)/dashboard/...`): overview, `billing`, `data`,
`data/upload`, `reports`, `reports/[assessmentId]` (+ PDF route),
`schedule`, `schedule/history`, `support`, `team`.

**Admin** (`admin/(protected)/...`): overview, `organizations`,
`organizations/[id]`, `baselines`, `verification`, `invoices`, `pilots`,
`team`, `audit-log`, `jobs`, `docs`.

## Components

Small, hand-rolled UI kit: `ui/button`, `card`, `badge`, `input`,
`spinner`, `pending-link`. Shell/layout: `SiteHeader`, `SiteFooter`, `Logo`,
`Eyebrow`, `FaqAccordion`, `LegalPlaceholder`, `PostHogProvider`.
Portal-specific: `PortalShell`, `PortalPageHeader`, `NavLink`.
Admin-specific: `AdminNavLink`, `SignOutButton`, `CreateAdminUserForm`,
`BackfillPricesForm`. Feature widgets colocated with their route:
`RecommendationDecisionButtons`, `AddBlockForm`, `ApplyDecisionsButton`,
`RunOptimizationButton`, `UploadForm`, `GenerateAssessmentButton`.

## API Routes (Route Handlers)

- `/api/auth/[...all]` — Better Auth customer realm catch-all.
- `/api/admin-auth/[...all]` — Better Auth admin realm catch-all.
- `/api/internal/ingest-prices`, `/api/internal/generate-alerts`,
  `/api/internal/run-verification` — cron-triggered job endpoints, guarded
  by `verify-internal-job-request.ts` (`CRON_SECRET` / `INTERNAL_JOB_SECRET`).
- `/dashboard/reports/[assessmentId]/pdf` — streams a generated PDF.

## Server Actions

Portal: dashboard decisions, data upload, report generation, schedule (add
block / run optimization / apply decisions). Admin: baselines (lock),
invoices (mark paid), jobs (manual run), organizations (notes/edit), pilots
(review/invite), team (create admin user), verification (review/approve).
Plus invite acceptance and pilot-program application submission.

## Background Jobs

`src/lib/jobs/`: `ingest-prices.ts`, `backfill-prices.ts`,
`run-verification.ts`, `generate-alerts.ts`, all wrapped by
`job-run-tracker.ts` (writes `JobRun` rows) and gated by
`verify-internal-job-request.ts`. Triggered by Vercel Cron or an admin's
manual "Run now" in `/admin/jobs`.

## Scheduled Tasks (`vercel.json`)

| Schedule       | Endpoint                          | Cadence           |
|----------------|------------------------------------|--------------------|
| `0 5 * * *`    | `/api/internal/ingest-prices`      | Daily, 05:00       |
| `0 6 * * *`    | `/api/internal/generate-alerts`    | Daily, 06:00       |
| `0 3 1 * *`    | `/api/internal/run-verification`   | Monthly, 1st 03:00 |

## External Integrations

- **ENTSO-E** — day-ahead electricity price feed
  (`src/lib/entsoe.ts`, `src/lib/prices/providers/entsoe.ts`),
  `ENTSO_E_TOKEN`. `NORDPOOL` exists as a schema enum value but has no
  provider implementation.
- **Resend** — transactional email (invites, admin provisioning),
  `RESEND_API_KEY` / `RESEND_FROM_ADDRESS`; falls back to console logging
  when unset.
- **PostHog** — product analytics, client (`PostHogProvider`) + server
  (`lib/analytics/server.ts`).
- **Sentry** — error monitoring, client/server/edge configs plus
  instrumentation hooks.
- **Stripe** — schema has `stripeCustomerId` / `stripeInvoiceId` fields but
  **no live integration**: no webhook handler, no Stripe SDK call anywhere
  in `src`. Invoices are marked paid manually by an admin (`paidAt`,
  `paymentNote`). Treat as unimplemented/placeholder, not a dependency to
  port.
- **pdf-lib** — self-hosted savings-assessment PDF generation, not a
  third-party API.

## Migration Roadmap → Laravel (Inertia + React + TS + Tailwind + Pest)

1. **Foundation parity**
   - Recreate the schema as Laravel migrations. Preserve the cents-as-integer
     money convention and enum-equivalents (Postgres enums or Laravel enum
     casts). Preserve the hash-chained, append-only audit log (DB trigger or
     model guard) and the immutable-once-locked `Baseline` invariant.
   - Port the row-level security SQL policies as-is — Postgres RLS is
     DB-level and portable regardless of ORM.
   - Stand up two Laravel auth guards (`web` for customers, `admin` for
     staff) mirroring Better Auth's realm separation: separate session
     cookies/config per guard, not a shared table with a role flag.

2. **Multi-tenancy & routing**
   - Reimplement the subdomain rewrite logic (`proxy.ts`) as Laravel route
     groups + middleware keyed on the request host, or via
     `Route::domain()`.
   - Reimplement the organization/member/invitation model to match `OrgRole`
     semantics (OWNER / SCHEDULER / FINANCE / VIEWER).

3. **Core domain models & business logic**
   - Port `lib/optimization`, `lib/savings`, `lib/verification` — currently
     a strategy pattern versioned by `methodologyVersion` — into Laravel
     service classes/Actions. This is the highest-value, most bespoke logic;
     write Pest coverage before UI work starts. The existing
     `*.property.test.ts` files are good source material for Pest datasets.
   - Port `lib/audit.ts` as a dedicated service; preserve "only this class
     writes this table."

4. **Background jobs & scheduling**
   - Convert `lib/jobs/*` to Laravel Queue Jobs; replace the three Vercel
     Cron entries with Laravel's scheduler in `routes/console.php` (same
     cadences: daily 05:00 ingest-prices, daily 06:00 generate-alerts,
     monthly 1st 03:00 run-verification).
   - Reimplement `JobRun` tracking as a job middleware/listener.

5. **External integrations**
   - ENTSO-E client → Laravel HTTP client service, same token-based auth.
   - Resend → Resend has a first-party Laravel mailer, or keep the SDK via
     HTTP.
   - PostHog/Sentry → official Laravel SDKs exist for both.
   - Stripe → since it's unimplemented in v2, decide up front whether to
     carry the placeholder fields forward or treat this as a clean-slate
     opportunity to build real billing.

6. **Frontend/UI**
   - Since the target stack is Inertia + React + TS + Tailwind, most React
     components (`ui/*`, portal/admin widgets) are directly portable with
     routing adjustments — replace Next `Link`/`useRouter` with Inertia's
     `Link`/`router`, replace Server Actions with Inertia form submissions
     to Laravel controllers.
   - PDF generation (`pdf-lib`) — either keep a small Node service for this
     one concern, or move to a PHP library (dompdf/Snappy) and re-validate
     report layout against the existing report.

7. **Testing**
   - Convert Vitest unit tests and Playwright e2e specs to Pest. The
     existing strategy-pattern tests translate cleanly to Pest test cases
     per strategy.

8. **Suggested cutover sequencing**
   1. Auth + org/tenant model
   2. Metering/schedule CRUD
   3. Optimization/savings/verification engine
   4. Billing/invoices
   5. Admin console
   6. Jobs/cron
   7. Integrations swap-in
   8. PDF/report parity
   9. Full e2e parity check against the existing Playwright specs in `e2e/`
