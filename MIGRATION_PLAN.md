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
| 1. Foundation | **Complete** | ADR-1..ADR-12 |
| Account Security module | **Complete** | Out-of-band addition, ahead of Stage 2 — ADR-13..ADR-20. Customer portal only. |
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

---

## Account Security Module (customer portal)

Commissioned as a standalone, out-of-band addition — implemented ahead of
Stage 2 at explicit request, not part of the staged migration itself.
**Customer portal (`web` guard) only.** Every Action/Service below is
written guard-agnostic (takes a `User`/generic `Authenticatable`-shaped
model, not a hardcoded guard name) specifically so the admin realm can
adopt the same architecture later without a rewrite — but no admin route,
controller, or UI was touched by this module.

### ADR-13: Real forgot/reset password, superseding the "no such flow" posture of ADR-6

ADR-6 (Stage 1) said no password-setting Form Request needed
`Password::defaults()` because no such flow existed yet in the migrated
app. This module adds one for real — Laravel's own Password Broker
(`Password::sendResetLink()` / `Password::reset()`), not a hand-rolled
token system, restoring the `password_reset_tokens` table and
`passwords.users` broker config that ADR-6 had deliberately removed.
`Password::defaults()` (12 chars, mixed case, number, symbol,
`uncompromised()` HIBP check) is now the single source of truth used by
both reset and change-password. Admin realm untouched — it still has no
self-serve reset flow.

### ADR-14: Back-button/bfcache protection — middleware + client fallback, not a global header

Root cause: the browser's back-forward cache (bfcache) can restore a
previously-rendered authenticated page instantly on Back, without any
network request — server-side `session()->invalidate()` on logout is
already correct, but a page cached under old headers can still be
*repainted* client-side. Fix is two layers:
1. `App\Http\Middleware\PreventBackHistoryCache` sets `Cache-Control:
   no-store, no-cache, must-revalidate, private` + `Pragma: no-cache` +
   `Expires: 0` on every response in an authenticated route group
   (`routes/settings.php` today). Scoped to that group deliberately —
   marketing/docs/static assets must stay cacheable, so this is never
   registered globally.
2. A `pageshow` listener in `resources/js/app.tsx` checks
   `event.persisted` (true when a page *is* restored from bfcache despite
   the headers — a narrow race some browsers still allow) and forces a
   fresh Inertia visit, which re-runs server-side auth immediately.
Headers alone can't retroactively un-cache a page served before this
change shipped — this only prevents *future* caching, which is the
correct and only fix available (there is no way to reach into a user's
already-open browser tab and evict something from its cache).

### ADR-15: Session revocation is direct `sessions`-table row deletion, not `logoutOtherDevices()`

Laravel ships `Auth::guard('web')->logoutOtherDevices($password)`, built on
`Illuminate\Session\Middleware\AuthenticateSession` comparing a
session-stored password hash against the live one — the framework's
"real" mechanism for "log out other browser sessions" (what Jetstream
uses). Not used here as the primary mechanism, for one concrete reason: it
can't target one specific non-current session by ID, which "revoke this
one device" requires, and there's no way to combine two different
invalidation semantics (lazy hash-comparison for "all," immediate
DB-deletion for "one") without the UI lying about how immediate each
action actually is. `RevokeSessionAction` and `LogoutOtherDevicesAction`
both delete `sessions` table rows directly and immediately — itself a
well-established Laravel technique for `SESSION_DRIVER=database`, not a
workaround. `AuthenticateSession` (`auth.session` middleware alias) is
still added to the protected route group as defense-in-depth, catching the
rare case a row survives deletion due to a request-timing race.

### ADR-16: Progressive login lockout is a bespoke service layered on the existing route throttle

`routes/auth.php`'s `throttle:20,1` (Stage 1, matching the Next.js app's
Better Auth config) is a flat per-IP window — the right tool for basic
abuse protection, not for "progressive delays," which by definition needs
escalating state Laravel's stock `RateLimiter`/`throttle` middleware
doesn't track (a fixed window resets identically whether it's attempt 5 or
attempt 50). `App\Domain\Auth\Services\LoginRateLimiter` layers a
per-account (email+IP) escalating lockout on top — 1/5/15/30 minutes at
5/10/15/20 cumulative failed attempts, persisting for 24h, clearing on
success. Built on the `Cache` facade (not a new dependency), keyed the
same way Laravel Fortify keys its own throttle (email+IP, not IP alone) —
an attacker spraying many accounts from one IP still hits the route-level
IP throttle; this specifically catches one account being hammered from
anywhere.

### ADR-17: `SessionRecord` — a small Eloquent model over Laravel's own `sessions` table

Laravel manages the `sessions` table's schema (Stage 1's users migration)
but doesn't ship an Eloquent model for it — session access normally goes
through the `Session` facade for the *current* request's session, not
through querying *other* rows for a *listing* UI. `App\Domain\Auth\Models\
SessionRecord` exists purely so the active-sessions feature can use
Eloquent (relationships, query scoping, policy binding) instead of the
raw query builder throughout. Named `SessionRecord`, not `Session` —
shadowing Laravel's own facade name in application code is exactly the
kind of ambiguity a security-sensitive module shouldn't introduce.

### ADR-18: No geolocation, no new UA-parsing dependency

The brief asks for "approximate location (if available)" on the active-
sessions list — genuinely not available: no geolocation service exists
anywhere in this codebase, and adding one (a paid API, or shipping a
MaxMind GeoLite2 database) is a real new external dependency this module
isn't taking on unasked. `ActiveSessionData::$location` is typed nullable
and always `null` today, so a resolver can be plugged into
`ActiveSessionFinder` later without touching any caller. Browser/OS
detection (`UserAgentParser`) is similarly a small self-written regex
parser rather than a new Composer package — good enough for "Chrome on
macOS" display, swappable for a real UA-parsing library later without
changing its return shape.

### ADR-19: Laravel's HTTP test client does not carry cookies between separate calls — plan tests around this

Discovered the hard way while testing "is this the session row *making*
the current request": `Illuminate\Foundation\Testing\Concerns\
MakesHttpRequests` has no cookie jar — a `Set-Cookie` from one
`$this->get()/post()/put()/delete()` call is **not** automatically sent on
the next one, unlike a real browser. Two consequences, both confirmed by
direct investigation (not assumption):

1. **Session *identity* does not survive across separate test calls.**
   Each cookie-less call makes `Illuminate\Session\Middleware\
   StartSession::getSession()` pass `null` to `Store::setId()`, which
   (correctly — the format check has no way to know a row exists) falls
   back to a fresh random ID. A row from an earlier call remains in the
   `sessions` table, but it is no longer "this request's session."
2. **Session *data* often appears to survive anyway** — this is what made
   the bug take a long time to isolate. `Illuminate\Support\Manager`
   (which `SessionManager` extends) caches its resolved driver/Store
   instance per container, and PHPUnit/Pest reuse one container for an
   entire test method. `Store::loadSession()` merges via
   `array_replace($this->attributes, $this->readFromHandler())` rather
   than overwriting — so attributes set on that cached Store earlier in
   the test (e.g. `auth.password_confirmed_at`) survive a later call's
   "load from a fresh, nonexistent ID" (which contributes an empty array
   to the merge), even though the ID itself changed. Auth state applied
   via `actingAs()` persists for the same container-caching reason, on top
   of that. A real browser never hits any of this, since it always sends
   its one real cookie back — `$request->session()->getId()` is genuine
   ground truth there.

**Where this actually bit real tests**: anything asserting *which specific
session row* a follow-up request belongs to (see
`tests/Feature/Settings/SessionManagementTest.php`'s header comment and
`tests/Feature/Domain/Auth/ActiveSessionFinderTest.php`, added specifically
because HTTP-level assertions on "is this row flagged current" aren't
reliable across two calls). It did **not** silently invalidate the broader
test suite: single-call tests, and tests only asserting on session *data*
persisting (auth state, flashed validation errors, `password_confirmed_at`),
are unaffected — that's exactly the category of behavior that happens to
survive via the caching quirk above.

**Guidance for later stages**: when a test needs to know "the ID of the
session my next call will use," don't try to predict it from an earlier
call. Either keep it to one real request (works whenever the endpoint
under test isn't behind something like `password.confirm` that needs
earlier state), or invoke the controller/action directly with a
hand-constructed `Illuminate\Session\Store` whose ID you set yourself (see
`SessionManagementTest.php`'s "cannot revoke their own current session"
test for a worked example). Manually replaying a captured `Set-Cookie`
value via `withCookie()` was tried at length and does **not** reliably
work in this codebase's Laravel/PHP version combination — the underlying
`EncryptCookies` decryption did not visibly apply to a cookie injected
this way, for reasons that resisted further isolation after checking
route middleware composition, cache staleness, `AuthenticateSession`,
SQLite-vs-real-Postgres, and manual decryption of the exact captured
value (which decrypted correctly in isolation, ruling out a corrupted
value). Don't re-attempt that path without new information.

### ADR-20: Two real bugs found by live browser smoke testing, not by the automated suite

The 86/86 (now 88/88) automated suite passing did not mean the feature was
bug-free end to end — a manual browser walkthrough against a real
PostgreSQL-backed `php artisan serve` (not the `array`-driver, no-real-cookies
test environment ADR-19 describes) surfaced two defects the suite's
assertions were structurally unable to catch:

1. **`PasswordController::update()` abandoned rather than destroyed the
   pre-change session.** `$request->session()->regenerate()` defaults to
   `$destroy = false`, so the still-fully-authenticated pre-change session
   row was left alive in `sessions` instead of being invalidated — visible
   in the UI as a phantom second "active session" for the same browser
   immediately after every password change, and, more importantly, a live
   authenticated session ID nobody was supposed to be able to use anymore.
   Fixed by changing the call to `regenerate(destroy: true)`, matching the
   pattern `AuthenticatedSessionController::destroy()` already uses via
   `invalidate()`. `tests/Feature/Settings/ChangePasswordTest.php`'s
   `beforeEach`-free existing test only inserted and checked a manually
   fabricated *other* session row, which could never have exercised this
   path; the new regression test switches the file to the real `database`
   session driver for that one test and asserts the row count for the user
   stays at exactly 1 across a password change, which holds regardless of
   which specific row the test client's request happens to treat as
   "current" (see ADR-19).
2. **`PasswordResetLinkController::create()` never forwarded the flashed
   `status` message into Inertia props.** `store()` correctly does
   `back()->with('status', ...)`, but unlike
   `AuthenticatedSessionController::create()` (which does pass
   `session('status')` through for the Login page), `create()` rendered the
   page with no props at all — so the generic "if an account exists..."
   confirmation the spec requires was computed and flashed server-side but
   never actually reached the user. `assertSessionHas('status')`, used
   throughout `PasswordResetTest.php`, only proves the flash was set; it
   never inspects what the next rendered page receives, which is exactly
   the gap that let this ship with full test coverage green. Fixed by
   adding `'status' => session('status')` to `create()`, plus a new test
   that does a real follow-up `GET` and asserts on the Inertia page's
   `status` prop directly rather than on session state.

**Guidance for later stages**: a flash-message-into-next-page-props wire-up
is easy to get right on one controller and silently skip on its sibling —
grep for every `Inertia::render` paired with a `back()->with(...)` elsewhere
in the same feature and confirm each `create()`/`show()` actually forwards
what its `store()`/`update()` flashes, rather than assuming the pattern was
copied correctly. More generally: `assertSessionHas()` is not a substitute
for `assertInertia()` when the thing being verified is "does the user see
this" — the two test different layers, and a suite that only exercises the
first will pass with the feature partly broken. Automated coverage passing
is necessary but was not sufficient here; the real browser walkthrough is
what actually caught both of these, which is why it stayed in the
process even with 88 tests green.
