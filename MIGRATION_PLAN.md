# Hetkin: Next.js → Laravel Migration Plan

Source of truth for the migration's ordering, architecture decisions, and
per-stage status. See [ARCHITECTURE_ANALYSIS.md](ARCHITECTURE_ANALYSIS.md)
for the full inventory of the existing (`hetkin-v2`) application. This file
is updated at the end of every stage — do not let it drift from what's
actually merged.

This is a rewrite of behavior, not a redesign. See the mission brief in
conversation history for the full constraint set; the short version: if a
signed-in customer or admin notices the app changed, that's a bug.

## Local Verification

```bash
composer install
cp .env.example .env   # first time only — then set DB_* to a real Postgres instance
php artisan key:generate  # first time only
npm install
npm run build              # MUST run before `php artisan test` — see note below
php artisan migrate:fresh --force
php artisan test
```

**Order matters**: `npm run build` has to run before `php artisan test`,
not after. Every GET route that renders an Inertia page requires
`public/build/manifest.json` to exist — `HandleInertiaRequests` reads it via
Laravel's Vite helper on every such request, tests included. Running tests
first against a missing manifest fails every "screen can be rendered" test
with a 500 (`Illuminate\Foundation\ViteManifestNotFoundException`), not a
real application bug — if you see that specific exception, this is why.

## Dependency Graph

```
Stage 1  Foundation — design tokens/UI kit, dual auth realms, audit log infra
Stage 2  Routing / multi-tenancy plumbing (subdomain rewrite)      → needs 1
Stage 3  Organizations (Eloquent + Policies + global scopes)       → needs 1
Stage 4  Metering (sites, metering points, readings)                → needs 3
Stage 5  Scheduling (production schedules, blocks) + portal shell   → needs 3, 4
Stage 6  Optimization & recommendations                             → needs 5
Stage 7  Baseline / Savings Assessment / Verification / Billing     → needs 6, audit log
Stage 8  Pilot funnel (public application → admin review → invite)  → needs 3, admin realm
Stage 9  Admin console (orgs, baselines, verification, invoices,
         pilots, team, audit-log viewer, job monitor)                → needs everything above
Stage 10 Background jobs & scheduler (price ingest, alerts, verify)  → needs 4, 6, 7, 8
Stage 11 External integrations (ENTSO-E, Resend, PostHog, Sentry)    → needs 10 mostly, wire in as each domain needs it
Stage 12 Marketing site + SEO/OG parity                              → no backend dependency, deliberately last
```

Rationale for the order: every later page is guarded by one of the two auth
realms built in Stage 1, so nothing else can be meaningfully tested before
it exists. Organizations comes before Metering/Scheduling/etc. because every
one of those tables has an `organization_id` foreign key and every query in
the app is tenant-scoped. Admin console is last among the "real" domains
because it reads and acts on all of them (audit log, invoices, verification
review) — building it earlier would mean building it twice.

## Stage Status

| Stage | Status | Notes |
|---|---|---|
| 1. Foundation | **In progress** | This document's Architecture Decisions section covers it |
| 2–12 | Not started | |

## Architecture Decisions

### ADR-1: ULID primary keys, not auto-increment or Prisma's cuid

Prisma used `cuid()` string IDs everywhere, including in URLs
(`/admin/organizations/[id]`). Laravel has no native `cuid()` driver.
Options considered: auto-increment bigint (leaks row-count/sequence
information, unlike cuid), UUIDv4 (not sortable, worse index locality),
ULID (sortable, opaque, string, same practical properties as cuid).
**Decision: ULID** (`HasUlids` trait) for every table from Stage 1 onward.
This is a one-time decision — every later stage's foreign keys depend on it,
so it isn't revisited per-domain.

### ADR-2: Drop Better Auth's `Account` / `Verification` tables

`Account` is Better Auth's provider-abstraction table (needed for OAuth).
`Verification` is its generic email-token store. Neither has any exercised
behavior in v2: the only provider configured is `credential`
(email+password), and `requireEmailVerification: false` on both realms
means no code path ever reads `Verification`. These are framework-required
plumbing for Better Auth specifically, not a Hetkin business rule — Laravel
has its own idiomatic equivalent (`password` column + `Hash` facade) that
needs no such indirection. **Decision: do not port these tables.** If OAuth
or email verification is ever turned on, they can be added then, following
Laravel's own conventions rather than Better Auth's.

### ADR-3: `Session.activeOrgId` becomes session-payload state, not a column

Laravel's session payload already serializes arbitrary key/value data.
Storing "which organization is currently active" as `session('active_organization_id')`
is idiomatic and avoids a bespoke column that duplicates what the session
store already does. Set/read starting in Stage 3 (Organizations).

### ADR-4: Two physically separate session realms via middleware, not two apps

Laravel doesn't support multiple named session configurations (cookie +
table) out of the box the way Better Auth's two `betterAuth()` instances did.
`app/Http/Middleware/UseSessionRealm.php` swaps `config('session.cookie')`
and `config('session.table')` before Laravel's `StartSession` middleware
runs, parameterized per route-group (`customer` / `admin`). This reproduces
"two cookies, two tables, no shared state" without two separate Laravel
applications. **This is the highest-risk piece of custom infrastructure in
Stage 1** — a bug here could leak session state across realms. Covered by an
explicit isolation test (`tests/Feature/Auth/SessionRealmIsolationTest.php`).

### ADR-5: Password hashing — bcrypt, not scrypt (documented cutover risk)

Better Auth hashes with scrypt by default. Laravel hashes with bcrypt.
There is no live production database being migrated by this repository, so
this isn't blocking today, but it is a real future cutover concern: existing
users' password hashes will not verify against `Hash::check()` after a
real data migration. **Recommended mitigation when a real cutover happens**:
either (a) force a password-reset flow for all users on cutover, or (b)
implement a custom Laravel hash driver that can verify a legacy scrypt hash
once and transparently re-hash to bcrypt on that user's next successful
login. Not implemented in Stage 1 — no real users exist yet to migrate.

### ADR-6: `minPasswordLength` enforcement moves to the invitation-acceptance flow

v2 has no self-serve signup for either realm — the only place a password is
ever set is `src/app/invite/[invitationId]/actions.ts` (customer) and
whatever action creates an `AdminUser` (Stage 9, Team domain, admin-creates-admin).
`minPasswordLength` (12 customer / 16 admin) is therefore enforced there,
not in the Stage 1 `LoginRequest` — login only ever checks a password that
already exists.

### ADR-7: Audit log is its own bounded context (`app/Domain/Audit`)

Matches the Prisma schema's own structure (audit log & alerts is its own
schema section, not part of any single domain). `AuditLogService::record()`
reproduces the SHA-256 hash-chain logic field-for-field, called inside the
caller's `DB::transaction()` — exactly the "same transaction as the domain
write" invariant the original enforces. One hardening beyond the original:
the "read the latest hash" query uses `lockForUpdate()`, closing a race two
concurrent writers could hit in the Node version. This is a concurrency
correctness fix, not a behavior change — the hash algorithm and chain
semantics are unchanged. The Laravel chain starts fresh; it does not need to
be byte-for-byte hash-compatible with any existing Node-computed chain,
since there is no live chain being migrated.

### ADR-8: Row-level security is deferred, matching v2's own current state

v2's Postgres RLS policies exist as a manual SQL migration but are **not
yet enforced** (`force-row-level-security.sql` hasn't been run against a
non-owner DB role). Same posture here: RLS policies get ported as raw-SQL
migrations once Organizations (Stage 3) exists, application-layer tenant
scoping via Eloquent global scopes is the primary enforcement mechanism
(per the mission brief's explicit instruction to use Eloquent + Policies +
global scopes for Organizations), and RLS is a defense-in-depth layer added
on top later, not a blocker for Stage 3.

### ADR-9: Subdomain proxy deferred to Stage 2

Stage 1's routes are plain paths (`/sign-in`, `/dashboard`, `/admin/sign-in`,
`/admin/pilots`) — exactly how v2 itself behaves in local dev when
`COOKIE_DOMAIN` is unset. This is an independently testable subset of the
full behavior, not a shortcut: the subdomain→path rewrite (`dashboard.*` →
`/dashboard`, `admin.*` → `/admin`) is genuinely separate infrastructure
that touches every future route, so it gets its own stage immediately after
this one rather than being bolted onto the auth work.

### ADR-10: Post-login destinations are placeholders in Stage 1 only

`/dashboard` (customer) and `/admin/pilots` (admin) exist in this stage only
to prove the guard + redirect behavior end-to-end, matching v2's exact
`router.push(...)` targets. They are replaced by the real portal (Stage 5)
and real admin pilots page (Stage 9) — clearly marked `TODO` in code, not
silently left as fake content.

### ADR-11: Enum-like columns are `string` + PHP backed enum casts, not Postgres native `ENUM` types

Prisma's `AdminRole`/`AuditActorType`/etc. compiled to Postgres native enum
types. Laravel has no first-class migration DSL for those (`DB::statement`
raw SQL only), and a native Postgres enum type can't be created against the
sqlite in-memory connection the test suite uses by default. Using a plain
`string` column with a PHP 8.1+ backed enum (`App\Domain\Auth\Enums\AdminRole`,
cast via the model's `casts()`) keeps the same validity guarantee at the
application boundary, works identically on both connections, and is the
idiomatic modern-Laravel pattern. **Trade-off accepted**: no DB-level CHECK
constraint enforcing the value set on Postgres — acceptable since every
write path goes through Eloquent, same as every other application-level
invariant in this codebase (mirrors v2's own posture: RLS as defense in
depth, not the only enforcement layer).

### ADR-12: Session-realm swap is process-per-request dependent

`UseSessionRealm` (Stage 1) mutates `config('session.*')` before Laravel's
`StartSession` middleware resolves the session store. This is correct and
widely-used under a standard php-fpm/CGI deployment, where each HTTP request
gets a fresh application container (so the `SessionManager`'s internal
per-driver-name instance cache never survives across requests). **It is
NOT safe as-is under Laravel Octane or any other long-running-worker
deployment mode**, where the container (and therefore the session manager's
cached driver instance) persists across requests — a worker that served an
admin request could serve the next customer request with the admin table
still bound. Not a concern for Stage 1 (no Octane in use), but must be
revisited (explicit container reset between requests, or a different
mechanism entirely) before ever enabling Octane.

Confirmed empirically, not just theoretically: PHPUnit/Pest boot one
application container per test *method*, so a single test making two
sequential HTTP calls (customer login, then admin login) hits this exact
caching and sees the first call's cookie name on both — see the comment atop
tests/Feature/Auth/SessionRealmIsolationTest.php, split into two
single-request tests specifically because of this. That failure mode is a
test-harness artifact (real requests each get a fresh container), but it's
also the clearest possible evidence of why Octane needs a different answer.
