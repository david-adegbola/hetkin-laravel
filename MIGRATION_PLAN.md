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
| 2. Routing / multi-tenancy plumbing | **Complete** | ADR-21 |
| 3. Organizations | **Complete** | ADR-25..ADR-28 |
| 4. Metering | **Complete** | ADR-29..ADR-31 |
| 5. Scheduling + portal shell | **Complete** | ADR-32..ADR-36 |
| 6. Optimization & recommendations | **Complete** | ADR-37..ADR-40 |
| 7. Baseline / Savings Assessment / Verification / Billing | **Complete** | ADR-41..ADR-45 |
| 8. Pilot funnel | **Complete** | ADR-46..ADR-50 |
| 9–12 | Not started | |

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

Built in Stage 3 — see ADR-26 for what was actually implemented (the
Eloquent-scope layer; RLS SQL policies remain a separate follow-up, not
delivered here).

### ADR-9: Subdomain proxy deferred to Stage 2

Stage 1's routes are plain paths (`/sign-in`, `/dashboard`, `/admin/sign-in`,
`/admin/pilots`) — exactly how v2 itself behaves in local dev when
`COOKIE_DOMAIN` is unset. This is an independently testable subset of the
full behavior, not a shortcut: the subdomain→path rewrite (`dashboard.*` →
`/dashboard`, `admin.*` → `/admin`) is genuinely separate infrastructure
that touches every future route, so it gets its own stage immediately after
this one rather than being bolted onto the auth work.

Built in Stage 2 — see ADR-21 for what was actually implemented.

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

---

## Stage 2: Routing / Multi-tenancy Plumbing

### ADR-21: `Route::domain()` with explicit dual registration, not a rewrite-before-routing middleware

**The problem**: v2's `src/proxy.ts` intercepts every request *before*
Next.js resolves a route and rewrites `dashboard.hetkin.app/schedule` to
resolve against `/dashboard/schedule` internally — a true rewrite, not a
redirect, so the browser's address bar never changes. Laravel has no direct
equivalent of "mutate the path, then let normal routing continue": by the
time any middleware runs, `Illuminate\Routing\Router` has typically already
resolved which route matched (route-specific middleware only runs for an
already-matched route), so a rewrite-before-routing middleware would mean
fighting the framework's dispatch order — mutating a Symfony `Request`'s
cached `pathInfo` via undocumented internals, not a supported extension
point.

**What Laravel offers natively instead**: `Route::domain()` — host-based
route matching, resolved by the router at the same point path-matching
happens, no rewriting required. `routes/domains.php` registers two such
groups (`dashboard.{app.domain}`, `admin.{app.domain}`), each with its own
bare-path routes reusing the *exact same* controllers as the equivalent
main-domain routes in `routes/web.php` / `routes/admin.php` — e.g.
`portal.home` (bare `/` on `dashboard.*`) and `dashboard` (prefixed
`/dashboard` on the main domain) both render `Portal/DashboardPlaceholder`
via the same closure.

**Trade-off accepted**: this means every future portal/admin page needs a
second `Route::` registration line when it's added (one for the main-domain
prefixed path, one for the subdomain bare path) — real duplication, unlike
v2's fully generic rewrite. Alternatives considered and rejected:
- A shared route-definition closure invoked twice (once under
  `Route::domain()`, once under `Route::prefix()`) — breaks down because
  sign-in is a genuine asymmetry: it needs to be bare on *both* the main
  domain and the subdomain (v2's own explicit `passthrough` carve-out in
  `SUBDOMAIN_RULES`), so it can't cleanly live inside a "mount this file
  under a prefix" abstraction alongside routes that always take the prefix.
- Laravel's `Route::group($attributes, $filePath)` (mounting a route file
  twice with different attributes) — same asymmetry problem, plus route
  *names* would collide across the two mounts without extra bookkeeping
  that erases most of the DRY benefit anyway.

Given the current feature surface is small (one portal page, one admin
page, both placeholders), explicit duplication was judged cheaper *and*
more debuggable than a generic abstraction built ahead of the shapes that
would actually stress-test it. **Revisit if Stage 5/Stage 9 (the real
portal and admin console) make the duplication genuinely painful** — at
that point, a route-name-driven code generator or the `Route::group()` file
trick applied only to the routes that don't include sign-in becomes worth
the complexity.

**Also changed as part of this**: `AuthenticatedSessionController::store()`
(customer) and `Admin\Auth\AuthenticatedSessionController::store()` (admin)
both previously hardcoded their post-login redirect to the main-domain
prefixed route unconditionally — a leftover Stage 1 TODO, since Stage 2
(this rewrite) didn't exist yet. Both now check `$request->getHost()` and
redirect to the subdomain's own bare route when the login happened there,
matching v2's `${adminBasePath}/pilots`-style dynamic redirect exactly
(`src/app/admin/sign-in/page.tsx`). `bootstrap/app.php`'s
`redirectGuestsTo`/`redirectUsersTo` closures got the same host-awareness,
for the same reason: an unauthenticated visit to `dashboard.hetkin.app/`
must bounce to `dashboard.hetkin.app/sign-in`, not `hetkin.app/sign-in` —
silently changing the visible host on a redirect is exactly the failure
mode the subdomain rewrite exists to prevent.

### ADR-22: Shared Inertia prop instead of a Server/Client component split for base-path links

v2's `src/lib/subdomain-server.ts` / `subdomain-client.ts` exist as two
files specifically because Next.js Server Components can read the request's
`Host` header via `headers()` while Client Components can't (and read
`window.location` instead) — a distinction with no Inertia equivalent,
since every Inertia page is a single React component hydrated client-side,
with its initial render already driven by server-computed props. Rather
than port that two-file split mechanically, `HandleInertiaRequests::share()`
computes `isDashboardHost` / `isAdminHost` once per request from
`$request->getHost()` and shares them as an ordinary prop
(`resources/js/lib/subdomain.ts`'s `usePortalBasePath()` /
`useAdminBasePath()` read it via `usePage()`) — correct on the very first
server-rendered response, no `window.location` fallback branch needed for a
pre-hydration state that doesn't exist here the way it does in Next.js.

### ADR-23: `APP_DOMAIN` config, defaulting to `localhost`

Added `config('app.domain')` (env `APP_DOMAIN`, default `"localhost"`) as
the single value `routes/domains.php`'s two `Route::domain()` patterns are
built from — mirrors v2's dedicated `COOKIE_DOMAIN` var rather than trying
to derive a "bare domain" from `APP_URL` (which may carry a scheme/port
that a `Route::domain()` pattern must not include). Modern browsers resolve
`*.localhost` subdomains automatically (RFC 6761) with no `/etc/hosts`
edit required, so local dev works immediately via
`http://dashboard.localhost:8000` / `http://admin.localhost:8000` against
`php artisan serve`. `SESSION_DOMAIN` is deliberately left unset in local
dev (each subdomain gets its own independent session, matching v2's own
documented COOKIE_DOMAIN-unset behavior for local dev) and should be set to
`.hetkin.app` (leading dot) in production so a session cookie is valid
across the apex domain and both subdomains — see the comments added to
`.env` / `.env.example` next to both vars.

### ADR-24: Cross-call subdomain-prop assertions hit the same test-client limitation as ADR-19 — split, don't re-litigate

`tests/Feature/SubdomainRoutingTest.php`'s two "shared subdomain prop"
checks were originally one test issuing `$this->get('http://dashboard.
localhost/')` then `$this->get('/dashboard')` and asserting opposite prop
values from each. The second assertion failed intermittently for the same
structural reason ADR-19 documents for session IDs: Laravel's test client
doesn't guarantee two sequential calls within one test method are as
fully isolated as two real requests. Split into two separate tests
(one call each) immediately, rather than re-investigating — this is
exactly the guidance ADR-19 already left for later stages.

---

## Stage 3: Organizations

### ADR-25: Membership is the only authorization boundary — no OrgRole-based action gating, matching v2's actual current behavior

v2's `OrgRole` enum (OWNER/SCHEDULER/FINANCE/VIEWER) exists in the schema
and is *displayed* (the portal "team" page badge), but auditing every
portal Server Action in the Next.js app (`grep -rn "requireRole\|hasRole\|
role ===" src/app/\(portal\)`) turns up nothing — no code path there
currently restricts an action by role, only by whether a `Member` row
exists at all. `OrgRole`-based gating (e.g., only OWNER/SCHEDULER can add a
schedule block) is never actually implemented in the source this is a
rewrite of. `OrganizationPolicy`/`MemberPolicy`/`InvitationPolicy` in this
stage therefore check membership only, same as the source — inventing
role-based gates the original doesn't have would be adding behavior, not
porting it, and the mission brief is explicit that this is a rewrite of
behavior, not a redesign. If a later stage's source code turns out to gate
something by role after all, that stage's Policy adds the check then, not
speculatively here. `updateMemberRole` in v2 IS role-gated, but at the
*admin* layer (`requireAdminRole(["ADMIN", "SUPPORT"])`), not here —
Stage 9's concern, not Stage 3's.

### ADR-26: Fail-closed `OrganizationScope`, stricter than v2's currently-unenforced RLS

`App\Domain\Organizations\Scopes\OrganizationScope` (applied via the
`BelongsToOrganization` trait) throws if a query runs against a
tenant-owned model with no `CurrentOrganization` bound, rather than
silently returning unscoped/every-tenant's rows. This is deliberately
**stricter** than v2's own present-day posture: its RLS policies exist as
written SQL but are documented as not yet FORCEd (see ADR-8), so any v2
code path that reaches for the raw, unscoped `db` client instead of
`orgScopedDb()` today silently sees every organization's data with nothing
in the type system to catch the mistake. Failing closed here is judged
worth the small ergonomic cost (a genuinely cross-tenant query has to say
so explicitly via `Model::withoutGlobalScope(OrganizationScope::class)`,
mirroring Laravel's own `SoftDeletingScope` opt-out shape) because it turns
"forgot to scope a query" from a silent data leak into an immediate,
loud failure during development — this strengthens rather than weakens the
existing security model, which the mission brief permits (it only
prohibits weakening it).

`CurrentOrganization` is bound as a plain container singleton
(`App\Providers\AppServiceProvider::register()`), not resolved via a
request-scoped service or middleware yet — nothing in Stage 3 has an HTTP
entry point to call it from (no portal pages exist before Stage 5). A
future stage's portal middleware calls `OrganizationContextResolver::
resolve()` once per request and binds the result; this stage delivers the
mechanism and its full test coverage (`OrganizationScopeTest`,
`OrganizationContextResolverTest`), matching how `AuditLogService` shipped
fully tested in Stage 1 before any domain event used it. Same
container-freshness caveat as `UseSessionRealm` (ADR-12) applies: safe
under standard php-fpm/CGI, needs revisiting before Octane.

### ADR-27: Two irregularities preserved exactly from the Prisma schema, not "fixed"

- `invitations.invited_by_user_id` has **no foreign key constraint**. The
  Prisma schema declares it as a bare scalar (`invitedByUserId String`)
  with no `@relation` — Prisma itself never generated a constraint or a
  relation accessor for it. Matched exactly: a plain `ulid()` column, no
  `Eloquent belongsTo()`. Adding a constraint the original never had would
  be a schema improvement this rewrite doesn't get to make unilaterally.
- `organizations.pilot_application_id` is **absent entirely**. The Prisma
  schema has it as a nullable FK to `PilotApplication`, a table that
  doesn't exist until Stage 8's pilot funnel. An organization doesn't need
  it to function (one can exist without ever having gone through a pilot
  application — e.g., created directly by an admin), so it's added via a
  follow-up migration when Stage 8 builds that table, the same pattern
  already used for `audit_log_entries.organization_id`'s FK in this same
  stage (deferred from Stage 1, added here once the referenced table
  exists).

### ADR-28: Domain-namespaced models need an explicit `newFactory()` override

`Illuminate\Database\Eloquent\Factories\Factory`'s default resolver derives
a model's factory class by stripping an `App\Models\` (or bare `App\`)
prefix and appending the remainder to `Database\Factories\`. Every model
under `App\Domain\*\Models` (all of Organizations here, and `SessionRecord`
before it) fails that guess — it would resolve
`App\Domain\Organizations\Models\Organization` to
`Database\Factories\Domain\Organizations\Models\OrganizationFactory`, not
the flat `Database\Factories\OrganizationFactory` every factory in this
app actually lives at. `SessionRecord` never surfaced this because nothing
gives it a factory (tests write session rows directly). `Organization`,
`Member`, and `Invitation` all need one, so each overrides `newFactory()`
explicitly rather than relying on the guess — the documented, supported
pattern for non-standard namespaces, not a workaround. **Any future Domain
model that wants `HasFactory` needs the same explicit override** — this
is now a standing gotcha for every later stage's Domain models, not just
Organizations'.

---

## Stage 4: Metering

### ADR-29: Only Site gets BelongsToOrganization — MeteringPoint/MeterReading are scoped through it, not directly

The Next.js app's `Site` Prisma model has a direct `organizationId`
column; `MeteringPoint` and `MeterReading` do not — only `siteId` /
`meteringPointId` respectively. Confirmed against v2's own RLS policy
migration (`prisma/migrations/20260724230000_row_level_security_policies`):
its table list (`member`, `invitation`, `organization_note`, `site`,
`production_schedule`, ...) includes `site` but deliberately not
`metering_point` or `meter_reading` — v2 itself never built tenant
isolation for those two tables beyond "go through an already-scoped Site
first." This port matches that boundary exactly rather than "improving" it
by adding a denormalized `organization_id` neither source table has:
`Site` gets `BelongsToOrganization`; `MeteringPoint`/`MeterReading` get
plain `belongsTo` relations only, and their Policies
(`MeteringPointPolicy`) resolve organization membership by querying `Site`
directly rather than relying on ambient scope.

That last point mattered in practice: an early version of
`MeteringPointPolicy` read `$meteringPoint->site->organization_id`, which
lazy-loads `Site` through its *scoped* relation — requiring a
`CurrentOrganization` to already be bound just to answer "is this user
allowed to view this." That's circular (authorization is often what
decides whether to bind one in the first place), so the Policy instead
queries `Site::withoutGlobalScope(OrganizationScope::class)` directly,
mirroring `Member::isMemberOf()`'s own explicit opt-out from Stage 3.

Two more things documented in the source as intended but not actually
built are not ported here either, matching the "rewrite behavior, not
aspiration" principle from ADR-25: **Datahub integration** (the
`MeterDataSource.DATAHUB` enum value exists and is referenced in marketing
copy, but no actual API client exists anywhere in `src/`) and **monthly
partitioning** of `meter_reading` (the Prisma schema's own comment
describes it as coming "via a raw-SQL migration alongside this one," but
no such migration exists anywhere in the repository — confirmed by
searching every `migration.sql` for partition DDL). If either is ever
actually built in the source, port it then, not speculatively now.

### ADR-30: `FingridCsvParser` — two real JS/PHP semantic mismatches caught and fixed during the port

`app/Domain/Metering/Services/FingridCsvParser.php` is a line-for-line port
of `src/lib/meter-csv.ts`'s `parseFingridCsv`, but two spots needed a
genuinely different PHP implementation to produce the *same behavior*, not
just similar-looking code:

1. **Decimal-comma replacement.** JS's `qtyStr.replace(",", ".")` (no `/g`
   flag) replaces only the *first* comma. PHP's `str_replace()` has no
   such mode — its fourth parameter is an output count, not a limit, and
   it always replaces every occurrence. Using it directly would silently
   behave differently on a value containing more than one comma. Fixed
   with `preg_replace('/,/', '.', $value, 1)`, which does support a limit.
2. **Lenient numeric parsing.** JS's `parseFloat("12.5kWh")` returns `12.5`
   — it extracts a leading numeric token and ignores trailing garbage.
   PHP's `is_numeric()` would reject that same string outright (the whole
   string must be numeric), which would turn a value Fingrid's export
   quirks might trail with extra characters into a spurious "Non-numeric
   quantity" error the original parser never raised for that input. Fixed
   with a leading-numeric-token regex (`parseFloatLoose()`) that mimics
   `parseFloat`'s extraction behavior instead of PHP's stricter numeric
   check.

Both were caught by writing the test suite against the *behavior* (JS
semantics) rather than against whatever the first PHP draft happened to
do — worth calling out as a general risk for any future stage porting
more JS business logic: matching syntax is not the same as matching
runtime semantics, and the standard-library functions that "obviously"
correspond to each other (`str_replace`/`.replace()`,
`is_numeric`/`parseFloat`) are exactly where that gap hides.

### ADR-31: Actions given an explicit Organization must bind CurrentOrganization themselves

`UploadMeterReadingsAction` takes an already-resolved `Organization $organization`
parameter rather than reading one from ambient request state (consistent
with every other Action in this app being HTTP/context-agnostic). Its
first draft queried `Site::query()->firstWhere('organization_id', ...)`
directly — which still throws under Stage 3's fail-closed
`OrganizationScope`, because having the organization *available as a
variable* isn't the same as having it *bound* as the request's
`CurrentOrganization`, which is what the scope actually checks.

Fixed by having the Action bind `CurrentOrganization` itself for the
duration of its `execute()` call, restoring whatever was bound before in a
`finally` block rather than assuming nothing was (it might be called from
inside an HTTP request that already bound a — possibly different —
organization, or directly, as in its own tests). **This is now the
standing pattern for any future Action that receives an explicit
Organization and needs to query a `BelongsToOrganization` model**: bind
`CurrentOrganization` at the top of `execute()`, restore the previous
value in a `finally`, same shape every time.

---

## Stage 5: Scheduling + Portal Shell

### ADR-32: `ScheduleBlock` is scoped through `ProductionSchedule`/`Site`, not directly — same shape as ADR-29

`production_schedule` has a direct `organizationId` and is in v2's RLS
policy list; `schedule_block` has neither (only `scheduleId`/`siteId`, and
absent from that same list) — confirmed the same way ADR-29 was for
`metering_point`/`meter_reading`. `ProductionSchedule` gets
`BelongsToOrganization`; `ScheduleBlock` gets plain `belongsTo` relations
only. `SchedulePolicy` follows `OrganizationPolicy`'s membership-only
shape (ADR-25) — no `ScheduleBlockPolicy` was added, since nothing in v2
authorizes a single block independent of its schedule (mirroring the
decision not to add a `MeterReadingPolicy` in Stage 4).

### ADR-33: The portal overview and sidebar nav are an honest subset, not an approximation

v2's dashboard overview (`src/app/(portal)/dashboard/page.tsx`) and full
sidebar (Reports, Billing, Team & Permissions, Support, alongside
Schedule/Data) both depend on domains this port hasn't built yet:
`VerificationRecord`/`RecommendationDecision`/`Recommendation`/`Alert`
(Stages 6, 7, 10) for the stat cards and recommendation/alert panels, and
whole pages (Reports, Billing, Team, Support) that don't exist as routes
in this app at all yet. Two options were considered: approximate those
pieces now (fake zero-state cards, nav links to pages that don't exist),
or scope this stage honestly to what it actually built. The second was
chosen — `App\Http\Controllers\Portal\DashboardController` renders a
plain "get started" card linking to Schedule/Data instead of stat cards
that would show identical zeros whether nothing has happened yet or
something is broken; `PortalLayout`'s nav lists only Dashboard/Schedule/
Data. A nav link to a 404 is worse than v2's actual behavior, not
equivalent to it — extend both as each later stage lands its real page,
the same way `routes/portal.php` itself is meant to grow.

### ADR-34: `routes/portal.php`'s shared closure extends ADR-21's dual-registration pattern

ADR-21 (Stage 2) deliberately rejected a shared-closure/file-mount
approach for `routes/domains.php`'s `dashboard.*`/`admin.*` groups because
sign-in has a real asymmetry (bare on both the main domain and the
subdomain, unlike everything else that's prefix-vs-bare). Portal pages
have no such asymmetry — every route in `routes/portal.php` needs the
exact same prefix-or-bare treatment uniformly — so the trade-off ADR-21
flagged as "revisit if Stage 5 makes the duplication genuinely painful"
resolved in favor of a shared closure this time: one file, `require`'d
(not `require_once` — safe, since it declares no named symbol) from both
`routes/web.php` (`Route::prefix('dashboard')->group(require ...)`,
keeping the legacy bare `dashboard` route name) and `routes/domains.php`
(`Route::domain(...)->name('portal.')->group(require ...)`, giving
`portal.dashboard`/`portal.data`/etc.). This also renamed the Stage 2
placeholder route `portal.home` to `portal.dashboard` for consistency —
updated everywhere it was referenced (`AuthenticatedSessionController`,
`bootstrap/app.php`'s redirect closures, `SubdomainRoutingTest`).

### ADR-35: `NavLink`'s per-link pending spinner, deferred from `resources/js/app.tsx` until this stage

`app.tsx`'s `progress: false` setting has carried a comment since Stage 1
saying the per-link pending-state treatment "is ported as a React
component in a later stage" instead of Inertia's default top-of-page
progress bar — this is that stage. Ports v2's `NavLink.tsx` beta-feedback
reasoning exactly (an instant, exact-tap-location spinner so a click
doesn't look like it silently failed) using Inertia `<Link>`'s
`onStart`/`onFinish` event props rather than Next's `useLinkStatus()`
hook (no equivalent here) — same effect, true only while a visit to that
specific link's href is in flight.

### ADR-36: A live smoke test caught a real bug the automated suite couldn't — the add-block form never cleared after success

`Schedule.tsx`'s `AddBlockForm` originally cleared itself via a
`useEffect` watching Inertia's `wasSuccessful` flag:

```tsx
useEffect(() => {
  if (wasSuccessful) { reset(); formRef.current?.reset(); }
}, [wasSuccessful]);
```

This compiled, typechecked, and every PHP-level test (which only asserts
on the HTTP response/session) passed — but a real browser walkthrough
(this module's established practice since the Account Security module's
ADR-20) showed the form still full of the just-submitted values after a
successful "Add block." Root cause was never fully isolated (something
about `wasSuccessful`'s transition not reliably re-triggering the effect
for this specific same-page `post()` → `back()` redirect round trip), but
the fix is straightforward and more direct regardless: pass an
`onSuccess` callback to `post()` itself rather than reacting to state
after the fact —

```tsx
post(`${base}/schedule`, { preserveScroll: true, onSuccess: () => reset() });
```

— which is also the pattern `DataUpload.tsx` already used and never had
the problem. Standing guidance for future stages: prefer `post()`'s own
`onSuccess`/`onError` callbacks over a `useEffect` keyed on `wasSuccessful`/
`recentlySuccessful` for this kind of "clear the form after a successful
submit" behavior — it's both simpler and, per this incident, more
reliably correct. This is also further evidence for ADR-20's broader
point: PHP-level test coverage passing is necessary but not sufficient for
client-side behavior — the live walkthrough is what actually caught this.

## Stage 6: Optimization & Recommendations

### ADR-37: `OptimizationRun` is scoped; `Recommendation`/`RecommendationDecision`/`ElectricityPrice` are not — same RLS-list method as ADR-29/ADR-32

Checked v2's actual RLS policy table list
(`prisma/migrations/20260724230000_row_level_security_policies/migration.sql`)
rather than guessing from the schema shape alone: `optimization_run` has a
direct `organizationId` and is on that list, so it gets
`BelongsToOrganization`. `recommendation` (keyed off `optimizationRunId`,
optionally `scheduleBlockId`), `recommendation_decision` (keyed off
`recommendationId`), and `electricity_price` (global reference data, no
organization concept at all — it's the same ENTSO-E/Nord Pool price curve
for every tenant in a bidding zone) all have no direct `organizationId`
and are absent from that list — they get plain `belongsTo` relations only,
scoped transitively through `optimizationRun`/`recommendation`. Same
determination method as ADR-29 (Metering) and ADR-32 (Scheduling); by
Stage 6 this RLS-list check is the established default way to answer
"does this model get the trait," not a case-by-case judgment call.

### ADR-38: `RecommendationEngine` bound via the container as v2's `getRecommendationEngine()` factory's Laravel-idiomatic equivalent

v2 selects its recommendation-generation algorithm through a single
factory function, `getRecommendationEngine()`, currently always returning
`ProvisionalWindowShiftStrategy` — one designated swap point for a future
second methodology without touching call sites. The Laravel equivalent
used here is a container binding:
`$this->app->bind(RecommendationEngine::class, ProvisionalWindowShiftStrategy::class)`
in `AppServiceProvider::register()`, with `RunOptimizationAction`
type-hinting the interface rather than the concrete class. Swapping
methodologies later is a one-line change to the binding, same as v2's
factory — this is the idiomatic Laravel shape for "one designated place
decides which implementation," not a reach for DI for its own sake.

`ProvisionalWindowShiftStrategy` itself is a line-for-line port of
`src/lib/optimization/strategies/provisional-window-shift-strategy.ts`,
including its exact constants (`MIN_SAVINGS_CENTS = 100`,
`MIN_IMPROVEMENT_FRACTION = 0.05`) and its UTC-explicit day/hour bucketing
(`->copy()->utc()->format('Y-m-d')` / `format('G')`, mirroring JS's
`toISOString().slice(0,10)` / `getUTCHours()`) so the recommendation
output doesn't silently drift if `config('app.timezone')` is ever set to
something other than UTC. Verified with an 8-test mirror of the original
`provisional-window-shift-strategy.test.ts` suite, test-for-test, down to
exact cents-level savings calculations.

### ADR-39: Eager-loading a scoped relation purely to check organization ownership must explicitly bypass the scope — a recurring pitfall, now a standing pattern

`DecideRecommendationAction` needs to authorize that the recommendation
being decided belongs to the caller's organization. `Recommendation`
itself isn't `BelongsToOrganization`-scoped (ADR-37), but its
`optimizationRun` relation is — and the natural-looking code (bind
`CurrentOrganization` to the caller's org, then
`Recommendation::with('optimizationRun')->find($id)`) is a bug: for a
recommendation belonging to a *different* organization, the fail-closed
scope (ADR-26) doesn't throw during the eager load — it silently returns
`optimizationRun` as `null`, because from the scope's point of view
there's simply no matching row for the *bound* organization. The very
next line's `$recommendation->optimizationRun->organization_id` then
crashes on a null-property access instead of ever reaching the intended
"Recommendation not found" branch. Caught by a real test failure
(`'a recommendation belonging to another organization is not found'`
threw the wrong exception type/message), not by inspection.

Fixed by moving the lookup-and-authorize block to run *before* binding
`CurrentOrganization` at all, and eager-loading the scoped relation with
the scope explicitly removed for that one query:
`->with(['optimizationRun' => fn ($q) => $q->withoutGlobalScope(OrganizationScope::class)])`
— so a cross-organization row comes back as a real (non-null) model that
then fails an explicit `$recommendation->optimizationRun->organization_id
!== $organization->id` equality check, reaching the correct "not found"
exception instead of crashing. This is the same class of bug that hit
`MeteringPointPolicy` in Stage 4 (ADR-29's module) — two independent
occurrences now make it a standing rule for this codebase, not a one-off
fix: **whenever eager-loading a scoped relation for the specific purpose
of checking whether it belongs to the caller's organization, bypass the
scope on that load and check the foreign key explicitly.** Loading it
with the scope active answers a different question ("is there a row for
*my* organization") than the one being asked ("does *this* row belong to
my organization") — the two only coincide when the row happens to match,
which is exactly the case that didn't need the check in the first place.

### ADR-40: Live smoke test confirmed the full optimization → decide → apply flow end-to-end; one display quirk is inherited from v2 unchanged, not a regression

Following this module's established practice (ADR-20, ADR-36), the full
flow was walked through in a real browser against seeded data: adding a
schedule block, running optimization, accepting/rejecting/partially
adopting the resulting recommendations from the dashboard, and applying
accepted decisions into a new schedule version. All of it worked
correctly on the first pass — the Accept/Reject/Partial adopt forms
render and submit right, the pending-decision count and "apply decisions"
button appear/disappear correctly, and the new schedule version reflects
the applied changes.

One thing worth recording so a later stage doesn't "fix" it by mistake:
`RecommendationDecisionButtons`'s "partially adopted — moved to …" text
renders the adjusted time via `new Date(iso).toLocaleString('en-GB')`,
which formats in the *browser's* local timezone, not UTC — so the
displayed hour can differ from the UTC value stored and returned by the
API. This is not a bug introduced by this port: v2's own
`RecommendationDecisionButtons.tsx` does the exact same
`.toLocaleString("en-GB")` call with the same behavior. Ported
unchanged, matching the "rewrite behavior, not redesign" mandate — if
this is ever worth fixing, it should be fixed in both codebases
deliberately, not silently diverge here.

## Stage 7: Baseline / Savings Assessment / Verification / Billing

### ADR-41: All four Stage 7 tables are RLS-listed and scoped — unlike every prior stage's mixed picture

Checked v2's actual RLS policy table list, same method as ADR-29/32/37:
`baseline`, `savings_assessment`, `verification_record`, and `invoice` are
all present (`ARRAY['member', 'invitation', 'organization_note', 'site',
'production_schedule', 'optimization_run', 'baseline',
'savings_assessment', 'verification_record', 'invoice', 'alert']`), each
with a direct `organization_id` column in the source schema. So, unlike
Metering/Scheduling/Optimization (each of which had one scoped "root"
table and one-or-more child tables scoped only transitively), every model
this stage introduces gets `BelongsToOrganization` directly — there was no
"which one gets the trait" judgment call to make this time.

### ADR-42: v2's own dead-code/gaps are ported as-is, not silently "fixed" — flagged explicitly instead

Research into v2's actual behavior (not just its schema/comments) surfaced
three things worth calling out before anyone assumes they're oversights
introduced by this port:

1. **`Baseline.status = SUPERSEDED` is dead code in v2.** The enum value
   exists, the schema comment describes "superseding creates a new row,"
   but no code path anywhere ever sets it. `GenerateSavingsAssessmentAction`
   (ported from `generateSavingsAssessment`) unconditionally creates a
   fresh `DRAFT` `Baseline` on every call, even when a `LOCKED` baseline
   already exists for the org — exactly like v2. `SUPERSEDED` is carried
   forward on the `BaselineStatus` enum for schema fidelity, but nothing
   in this port transitions to it either.
2. **`SavingsAssessmentStatus::Delivered`/`::Accepted` are dead code too**
   — every assessment stays `Draft` forever in both codebases.
3. **Multiple `LOCKED` baselines per organization are possible** — locking
   (`LockBaselineAction`) is idempotent per-row, but nothing prevents
   locking two different `DRAFT` baselines for the same org.
   `RunVerificationAction` resolves this the same way
   `runVerificationJob` does: `orderByDesc('locked_at')->first()` — most-
   recently-locked wins, silently, if it ever happens.

All three are deliberate fidelity choices, not oversights: the mandate is
"rewrite behavior, not redesign," and quietly implementing real
superseding or a single-locked-baseline constraint would be exactly the
kind of behavior change ("if a signed-in customer or admin notices the app
changed, that's a bug") the mission brief rules out. If real superseding
is ever wanted, it should be a deliberate product decision made in v2
first, then ported — not something this migration introduces as a
side-effect of "obviously correct" cleanup.

### ADR-43: Admin-triggered Actions are fully built and tested this stage; their controllers/routes/pages are not — that's Stage 9's job

Roughly half of this stage's Actions are customer-triggered
(`GenerateSavingsAssessmentAction`, reachable from the portal's Reports
page) and half are admin-only in v2 (`LockBaselineAction`,
`ConfirmShadowReviewAction`/`ApproveLiveAction`/`DisputeRecordAction`/
`ReopenDisputedAction`, `GenerateInvoiceAction`/`IssueInvoiceAction`/
`VoidInvoiceAction`/`MarkInvoicePaidAction`, and
`UpdateOrganizationPricingAction`). The dependency graph puts "Admin
console (orgs, baselines, verification, invoices, pilots, team, audit-log
viewer, job monitor)" at Stage 9, deliberately after every domain it
reads/acts on — building it piecemeal per-stage would mean building the
same console twice. So this stage builds and thoroughly tests every
admin-side Action (they're pure business logic, independent of any
controller), but wires up NO admin controller, route, or page for any of
them yet — the same "honest subset" reasoning as ADR-33, extended to admin
territory for the first time. Stage 9 wires these Actions to real
`/admin/baselines`, `/admin/verification`, `/admin/invoices` pages; until
then, exercising them means calling them directly (as this stage's tests,
and the live-smoke-test in ADR-45, both do).

This also introduces a new pattern this codebase hadn't needed before:
**genuinely cross-tenant Actions bypass `OrganizationScope` explicitly
rather than binding `CurrentOrganization`.** Every prior stage's Actions
either received an explicit `Organization` and bound `CurrentOrganization`
to it (ADR-31) or ran inside a request that already had one bound
(portal middleware). An admin action like `LockBaselineAction` or
`RunVerificationAction` has no single "current organization" — it looks up
a `Baseline`/`Invoice`/`VerificationRecord` by ID (or iterates every
organization at once) with no tenant context at all, the same way v2's
`adminScopedDb()` reads/writes across every org unrestricted. The Laravel
equivalent is `Model::withoutGlobalScope(OrganizationScope::class)`,
mirrored on every scoped read/write these Actions perform — a deliberate,
visible opt-out at each call site, never an accidental one.

`GenerateInvoiceAction` hit the same scoped-eager-load pitfall
`DecideRecommendationAction` did in Stage 6 (ADR-39): eager-loading
`VerificationRecord::with('invoice')` in a cross-tenant context with no
`CurrentOrganization` bound throws (fail-closed `OrganizationScope`,
ADR-26) instead of correctly resolving "no invoice yet" as `null` — fixed
the same way, by bypassing the scope explicitly on that specific eager
load too. Two independent hits across two different domains now firmly
establish this as a standing pattern to watch for, not a one-off.

### ADR-44: PDF generation via dompdf (HTML/CSS rendering), not a port of v2's pdf-lib hand-coordinate drawing

v2's `src/lib/pdf/savings-assessment-pdf.ts` hand-draws every page with
`pdf-lib` at fixed pt coordinates — manual rectangles, manual text
placement, a hand-rolled waterfall-chart renderer, and a WinAnsi
character-sanitization pass because `pdf-lib`'s standard fonts don't
support full Unicode. Porting that approach line-for-line would mean
reimplementing a full coordinate-based layout engine in PHP for
comparatively little customer-visible benefit: the actual contract a
customer experiences is "downloading this PDF gives me a correctly
formatted document with the right numbers," not "these exact pixels are
in these exact positions."

This port uses `barryvdh/laravel-dompdf` instead: the same
`SavingsReportViewModelBuilder` view-model (identical to what the web
report page renders, so the two can never drift, exactly matching v2's
own reasoning for splitting `loadSavingsReportData`/
`buildSavingsReportViewModel` from both consumers) feeds a Blade view
(`resources/views/pdf/savings-assessment.blade.php`) rendered to PDF via
dompdf's HTML/CSS engine. Consequences of the swap, all deliberate:

- **No WinAnsi sanitization needed** — dompdf/DejaVu Sans supports full
  UTF-8 natively, so this port's PDF is strictly more permissive on
  characters than v2's, not less.
- **Charts are simplified** (CSS width/height-percentage bars, a colored
  table-cell heatmap, `position: fixed` header/footer with dompdf's
  `{PAGE_NUM}`/`{PAGE_COUNT}` placeholders) rather than hand-tuned SVG-style
  drawing — same data, same sections (all 10: cover, executive summary,
  customer overview, baseline analysis, market analysis, savings
  assessment, schedule, recommendations, methodology, appendix), simpler
  mechanism.
- **The HTTP contract is preserved exactly**: same route shape
  (`GET /reports/{assessment}/pdf`), same regenerate-on-every-request
  behavior (nothing cached, `savings_assessments.report_pdf_url` stays
  permanently `null` — no storage bucket wired up here either), same
  `Cache-Control: no-store`, same `savings_assessment.pdf_downloaded`
  audit-log entry, same `hetkin-savings-assessment-{date}.pdf` filename
  format (date = the assessment's `generated_at`, not "today").

### ADR-45: Live smoke test confirmed the full pipeline end-to-end, including exact golden-dataset figures in a real browser

Following this project's established practice (ADR-20, ADR-36, ADR-40):
seeded a real organization with 5 days of flat 100 kWh/hour meter data
against the same 10→240 EUR/MWh step-10 price curve the unit tests use,
signed in as that org's user, and walked Reports → Generate savings
assessment → report detail page → PDF download → Billing. Every number
matched the hand-derived golden dataset exactly as rendered in the
browser (€109,500.00/yr baseline → €81,906.00/yr optimized, 25.2% cost
reduction, 9.0x ROI, €24,834.60 net customer savings after a 10% revenue
share) — the KPI cards, waterfall bars, load-profile chart, and price
heatmap all rendered correctly against real computed data, not fixtures.
The PDF route returned `200 OK` with a real download.

Since Stage 9 (Admin console) doesn't exist yet, the verification/invoice
half of the flow was exercised by calling `ConfirmShadowReviewAction`→
`ApproveLiveAction`→`GenerateInvoiceAction`→`IssueInvoiceAction` directly
(the same way Stage 9's admin controllers will call them) rather than
through any UI — the Billing page correctly rendered the resulting
invoice (base fee + 10% of verified savings, "issued" status) once
seeded this way. This matches real-world v2 behavior too: a brand-new
pilot organization's Billing page is genuinely empty until an admin has
locked a baseline and walked a verification record through shadow
review — that's not a gap in this port, it's the intended state machine
working as designed.

## Stage 8: Pilot Funnel

### ADR-46: `pilot_application` is NOT organization-scoped — it exists precisely because no Organization exists yet

Confirmed against v2's actual RLS policy table list (same method as every
prior stage's scoping determination): `pilot_application` is absent from
it, and the source Prisma model has no `organizationId` column at all —
structurally, an application precedes any Organization existing for it.
`App\Domain\Pilots\Models\PilotApplication` gets no `BelongsToOrganization`
trait accordingly; `Admin\PilotApplicationController` and the review
Actions read/write it as a plain cross-tenant query, no scope bypass
needed because there's no scope to bypass.

`organizations.pilot_application_id` (nullable, unique) is added via a
follow-up migration, exactly as the Stage 3 organizations migration's own
comment forecast: *"Added via a follow-up migration when Stage 8 builds
that table."* The unique constraint matters later — see ADR-48.

### ADR-47: `invitations.invited_by_user_id` widened from `ulid()` to `string()` — a Stage 3 assumption this stage disproved

Stage 3 typed this column `ulid()` (Postgres `char(26)`) on the reasonable
assumption that it would only ever hold a real `User`'s ULID — true of
every invite created before this stage, since nothing else created one.
v2's own convention for an *admin*-originated invite (as opposed to a
peer-to-peer one) is `invitedByUserId: `admin:${adminUser.id}`` — a
41-character string that doesn't fit in `char(26)` and would fail with a
Postgres "value too long" error on insert. Caught before it ever reached
Postgres in CI, because SQLite (the test suite's default connection) has
no fixed-length `char` enforcement and would have silently accepted the
truncated-looking value — a reminder that this port's dual-connection
test strategy (MIGRATION_PLAN.md ADR-11) doesn't catch every
Postgres-specific type constraint, only the ones a query actually
exercises. Fixed via a follow-up migration (`string()`, i.e. `varchar`)
rather than editing Stage 3's original — the source Prisma schema always
typed this column as a bare, unconstrained `String` in the first place, so
`string()` is what it should have been from the start, not a loosened
constraint.

### ADR-48: v2's own funnel gaps are ported as-is — dead enum states, no application-level idempotency guard, non-collision-checked slugs

Consistent with ADR-42's precedent (Stage 7) of preserving v2's actual
behavior over "obviously correct" cleanup:

- **`PilotApplicationStatus::InReview` and `::Nurture` are dead code.**
  Both are grouped into UI buckets (`InReview` alongside `Pending` in the
  "still open" queue; `Nurture` gets its own badge color) but no action
  anywhere transitions an application into either state — only
  `Pending → Approved` and `Pending → Rejected` are reachable. Ported for
  schema fidelity; not built out further.
- **`pilot_applications.reviewer_notes` is a real column with no reader or
  writer anywhere** — no form field on the admin queue captures it, no
  action sets it. Same treatment as Stage 7's `Baseline.reviewer_notes`-
  style dead columns: kept for schema fidelity, not wired to a UI that
  doesn't exist in v2 either.
- **`ApprovePilotApplicationAction` has no "already decided?" guard**,
  matching `approvePilotApplication` exactly. The actual backstop is
  `organizations.pilot_application_id`'s unique constraint (ADR-46): a
  second approval of the same application throws a raw database exception
  (`QueryException` here, an unhandled Prisma `P2002` in v2) rather than
  failing gracefully or silently no-op'ing. Verified by test — approving
  twice throws, and only one `Organization` row ever exists per
  application.
- **The generated organization slug's random suffix isn't checked for
  collision** against existing slugs before insert — matches `slugify()`
  exactly (`Math.random().toString(36).slice(2,7)`, no retry loop). A
  theoretical collision would surface as a unique-constraint violation on
  `organizations.slug`, the same ungraceful way v2 would fail.

None of these are silently "fixed" here — flagging them explicitly is
the point: a rewrite that quietly hardens behavior v2 never had is
exactly the kind of change the mission brief rules out ("if a signed-in
customer or admin notices the app changed, that's a bug" cuts both
ways — a *stricter* app is still a changed one).

### ADR-49: `EnsureAdminRole` middleware — the first real HTTP-context admin role gate in this port, plus a deliberately minimal `AdminLayout`

Every prior stage's admin-only Actions (Stage 7's baseline/verification/
invoice lifecycle) were built and tested directly, with no controller or
route wired up yet — Stage 9 (Admin console) was to be where that
happened. Stage 8's own scope explicitly includes "admin review" as part
of the pilot funnel, though, so this stage needed a real, enforced
role check for the first time: `App\Http\Middleware\EnsureAdminRole`
(`admin.role:ADMIN,SUPPORT`) is the route-level equivalent of v2's
`requireAdminRole(["ADMIN", "SUPPORT"])` Server Action helper — applied to
`admin.pilots`/`admin.pilots.approve`/`admin.pilots.reject` in
`routes/admin-protected.php`, matching v2's exact role list for this
action (pilot intake being "the sales-adjacent front door of the admin
surface," per the source comment, not a money-moving or
savings-methodology action gated to stricter roles). This establishes
the pattern Stage 9 will reuse for baselines/verification/invoices'
stricter role lists.

`resources/js/Layouts/AdminLayout.tsx` is new too — deliberately minimal,
listing only "Pilots" in its nav rather than v2's full ten-item admin
shell (Overview, Pilots, Organizations, Baselines, Verification, Invoices,
Jobs, Audit Log, Team, Docs). Same "honest subset" reasoning as
`PortalLayout` (ADR-33), applied to the admin realm for the first time — a
nav link to a page that doesn't exist yet would be worse than v2's actual
behavior. Uses `useAdminBasePath()` (built in Stage 2, ADR-21, but unused
until now) rather than Ziggy's `route()` helper for its internal
links/logout — the same subdomain-correctness rule established for
`PortalLayout` in Stage 5/6, now extended to the admin realm's first real
page. `routes/admin.php` and `routes/domains.php`'s admin group both
now `require routes/admin-protected.php` (a new shared-closure file,
same dual-registration pattern as `routes/portal.php`/ADR-34) rather than
each duplicating the pilots routes inline, since this group outgrew the
single-placeholder-route duplication `routes/domains.php` originally
accepted.

### ADR-50: Invite acceptance combines v2's client/server split into one server-side POST; live smoke test confirmed the full funnel

v2's accept-invitation flow is two calls from the browser: the client
component calls Better Auth's `signUp.email()` (falling back to
`signIn.email()` if the account already exists) directly against the auth
API so its `Set-Cookie` response header lands via a real browser fetch,
*then* a separate Server Action (`acceptInvitation`) grants org
membership once that session exists. That split exists purely because a
Next.js Server Action can't forward another endpoint's `Set-Cookie`
header — it's not a behavior a customer perceives.

`App\Http\Controllers\InvitationController::store()` does the equivalent
work in one plain server-side POST: look up (or create) the `User`,
`Auth::guard('web')->login($user)` (which sets the session cookie
natively, no cross-request forwarding problem to route around), then run
`AcceptInvitationAction` (status/expiry/email-match checks, `Member`
creation, `Invitation` update, audit log — all ported verbatim from
`acceptInvitation`, including the "re-defensive even though the page also
checks it — the page's check is for UX, this one is what actually gates
the write" comment). Same customer-visible contract (submit name +
password, land in the new org), simpler mechanism — same reasoning as
ADR-44's dompdf swap.

Following this project's established practice (ADR-20/36/40/45): the
full funnel was walked in a real browser — submitted a pilot application
(ICP score 100, confirmed against the exact formula: 60 for a flexible
process type + 40 for ≥€150k spend), signed in as an admin, approved it
from the review queue (application moved from "0 pending" to "Reviewed"
with an `APPROVED` badge, an `Organization` was provisioned, an
`Invitation` created), followed the resulting invite link, created an
account, and landed on that organization's portal dashboard as its
`OWNER`. Confirmed in the database afterward: `Member.role = OWNER`,
`Invitation.status = accepted`, and all three expected audit-log entries
(`pilot_application.submitted` → `pilot_application.approved` →
`invitation.accepted`) present in the hash chain.

## Stage 9: Admin Console

### ADR-51: RLS-scoping determination for `OrganizationNote` and `JobRun`

Confirmed against v2's own RLS policy list (the established, mandatory
method for this determination — never guessed) and its Prisma schema:

- **`OrganizationNote`** has a direct `organizationId` and is in the RLS
  list, so it gets `BelongsToOrganization` like any other tenant-owned
  row — plus a `authorAdminUserId` belongsTo, the one model in this port
  referencing both a tenant and an internal `AdminUser`.
- **`JobRun`** is NOT org-scoped at all — a job run is a system-wide
  event, not owned by any one tenant. `triggered_by` is a plain
  unconstrained string column (holds either the literal `"cron"` or an
  `AdminUser` id, unprefixed — confirmed against v2's own
  `triggeredBy: string // "cron" or an AdminUser id` schema comment and
  its actual write sites), the same "no `@relation` in the Prisma source,
  so none added here either" reasoning as `invitations.invited_by_user_id`
  (ADR-47).

### ADR-52: Admin-context cross-tenant reads — every admin controller explicitly bypasses `OrganizationScope`, never binds `CurrentOrganization`

The admin realm has no "current organization" concept — an admin's own
session isn't tied to any one tenant, unlike the portal realm's
`ResolveOrganizationContext` middleware (ADR-31). Every admin controller
built this stage (`OrganizationController`, `BaselineController`,
`VerificationController`, `InvoiceController`, `DashboardController`)
reads `Baseline`/`VerificationRecord`/`Invoice`/`Member`/`Invitation`/
`OrganizationNote` — all `BelongsToOrganization`-scoped — via explicit
`Model::withoutGlobalScope(OrganizationScope::class)` calls, matching the
pattern Stage 7's `LockBaselineAction`/`RunVerificationAction` already
established for the same reason (a genuinely cross-tenant read, not a
single-tenant one with no context bound yet).

This applies recursively to **eager-loaded relations that themselves
point at a scoped model** — not just top-level queries. Two real gotchas
this surfaced, both fixed during Stage 9 build-out (not left as latent
bugs):
- `Invoice::with('verificationRecord')` — `VerificationRecord` is scoped,
  so the eager-load closure needs its own
  `fn ($q) => $q->withoutGlobalScope(OrganizationScope::class)`, or the
  *relation* query throws even though the top-level `Invoice` query
  already opted out.
- Bulk `withCount`/relation-based aggregates (e.g. "member count per org"
  on the Organizations index) can't use Eloquent's `withCount('members')`
  at all in admin context — the count subquery still runs through
  `Member`'s own global scope. Replaced with an explicit grouped bulk
  query (`Member::withoutGlobalScope(...)->selectRaw('organization_id,
  count(*) as aggregate')->groupBy('organization_id')`), same shape as
  `PilotApplicationController`'s pre-existing pending-invitations
  grouping (Stage 8).

### ADR-53: Member/Invitation sub-resources resolved manually, not via implicit route-model binding — a deliberate IDOR guard, not just a scope workaround

`routes/admin-protected.php`'s organization-detail mutation routes
(`organizations/{organization}/members/{member}/role`, `.../invitations/
{invitation}/resend`, etc.) take `{member}`/`{invitation}` as **plain
string route parameters**, not type-hinted Eloquent models. Laravel's
implicit route-model binding resolves every bindable parameter on a route
in one pass (via `SubstituteBindings` middleware) before any custom
middleware between them can run — there's no way to bind `{organization}`
first, then have a middleware set `CurrentOrganization`, then have
`{member}` resolve correctly scoped to it, all within one request. So
`OrganizationController` resolves them itself:
`Member::withoutGlobalScope(OrganizationScope::class)->where
('organization_id', $organization->id)->findOrFail($member)`.

This is not just a mechanical workaround — it's a real IDOR guard, and a
better one than implicit binding would have given: a `{member}` id that
belongs to a *different* organization than the route's `{organization}`
now 404s, rather than either resolving successfully across tenants (via
`Member::withoutGlobalScope` alone) or throwing an opaque "not authorized"
error. Covered by a dedicated test (`AdminConsoleControllersTest.php`,
"a member id from a different org 404s on the org-scoped member route").

### ADR-54: `EnsureAdminActive` middleware — the "already-authenticated" half of the deactivation rule Stage 1's `LoginRequest` anticipated

v2 enforces `AdminUser.active` in two independent, redundant places on
every request: `(protected)/layout.tsx` (page-level, re-checks on every
navigation) and `requireAdminSession()` (Server-Action-level, re-checks
on every mutation) — both needed because Better Auth's session
`cookieCache` can serve a stale `active: true` for up to 30 seconds
without hitting the database. Laravel's session guard has no equivalent
cache — `Auth::user()` resolves the `AdminUser` row fresh from the
database on every request already — so one middleware
(`App\Http\Middleware\EnsureAdminActive`, aliased `admin.active`, applied
to every route in `routes/admin-protected.php` except `logout`) gives the
same request-level guarantee v2 gets from its two call sites, without
needing to duplicate the check. A deactivated admin is logged out
outright (session invalidated) rather than merely blocked — an
invalidated session is a more honest state than a technically-still-
authenticated one every route silently rejects.

### ADR-55: Admin Overview's `/overview` path, not bare `/` — a real route collision, not a stylistic choice

v2's admin root (`src/app/admin/(protected)/page.tsx`) *is* reachable at
bare `/admin` on the main domain — but on the `admin.*` subdomain, v2's
own proxy layer (`src/proxy.ts`) unconditionally rewrites bare `/` to
`/admin/sign-in`, regardless of auth state (confirmed by
`SubdomainRoutingTest.php`'s pre-existing, passing test: an
*unauthenticated* request to `admin.localhost/` renders the sign-in
page — that test predates this stage and was not changed). Registering
the new Overview route at bare `/` in the shared `routes/admin-protected.
php` closure would have silently replaced `routes/domains.php`'s existing
`admin.subdomain.home` entry in Laravel's route collection (same
method+URI+domain key) — caught by that same pre-existing test failing
(guest hitting `admin.localhost/` got redirected into the newly-registered
authenticated route instead of seeing sign-in). Fixed by giving Overview
its own path segment (`overview`) on both mount points, consistent with
every other admin page, rather than bare `/` on either — a deliberate,
minor divergence from v2's own literal href (whose bare-`/` link on the
subdomain is itself a latent dead-link into the same proxy rewrite,
per v2's own architecture, not a behavior worth reproducing exactly).

### ADR-56: Two real, Postgres-only bugs found via a live smoke test — neither reproducible against the sqlite test suite

Following this project's established practice (ADR-20/36/40/45/50), the
full admin console was walked in a real browser against this port's
actual Postgres database (not sqlite) — sign in, every nav destination,
org-detail mutations (status/pricing/note/member-role/GDPR-erasure),
invoice lifecycle (mark paid), admin creation (temp-password flash), and
a manual job trigger. This surfaced two genuine bugs that the full
396-test Pest suite (run against sqlite per `phpunit.xml`) could not have
caught, in either case because sqlite lacks the specific Postgres
behavior the bug depended on:

1. **`audit_log_entries.metadata` was `jsonb`, not `json`.** Postgres's
   `jsonb` type decomposes a JSON value into a binary form and
   reconstitutes it on read — canonicalizing whitespace *and reordering
   object keys*. `AuditLogService::computeHash()` re-serializes
   `metadata` via `json_encode()` using the original key order every
   time; at write time that matches what's about to be stored, but at
   verify time (`AuditChainIntegrityChecker::verify()`, reading metadata
   back out of the now-reordered `jsonb` column) it no longer does — so
   every audit entry with non-empty metadata failed integrity
   verification despite nothing being tampered with. sqlite has no native
   `jsonb` type, so `jsonb()` migrations there just produce a plain TEXT
   column that round-trips bytes exactly, masking the bug completely
   under the full test suite. Fixed via a follow-up migration narrowing
   the column to plain `json` (which stores input text verbatim) — same
   "the stored representation must byte-for-byte match what produced the
   hash" reasoning the creating migration already documents for
   `created_at`'s microsecond precision, just for a different column.
   Already-written rows before the fix remain permanently unverifiable
   (the migration changes the type going forward, it can't retroactively
   recover a jsonb-reordered value's original byte form) — a genuinely
   destructive discovery, not a latent risk, which is exactly why a live
   smoke test against the real database engine matters here.

2. **Postgres session timezone wasn't pinned to UTC.** `config('app.
   timezone')` is `UTC`, but nothing told the `pgsql` connection to match
   — so `JobRun.started_at`'s DB-side `useCurrent()` default was
   evaluated in the Postgres server's own configured timezone (observed:
   `Europe/Helsinki`, UTC+3 in summer) while `completed_at` (set via PHP
   `now()` in `JobRunTracker`) was genuinely UTC, producing a negative
   job duration on the admin Jobs page (a run that took milliseconds
   displayed as `-10800000ms`). Fixed with `'timezone' => 'UTC'` on the
   `pgsql` connection in `config/database.php` (Laravel's
   `PostgresConnector` runs `SET TIME ZONE` with this value on connect),
   collapsing the skew from 3 hours to sub-second ordinary clock drift
   between the DB and app processes — then eliminated entirely by having
   `JobRunTracker` set `started_at` explicitly via PHP `now()` instead of
   relying on the DB default, so both timestamps on a `JobRun` row always
   come from the same clock. `RunVerificationAction` also had a real,
   independent bug the same smoke test caught: `MeterReading::query()
   ->whereHas('meteringPoint.site', fn ($q) => $q->where(...))` never
   bypassed `Site`'s own `OrganizationScope` inside the `whereHas`
   closure — invisible under the existing Pest suite because every
   existing test for this Action binds `CurrentOrganization` first
   (masking exactly the cross-tenant, no-context-bound path this Action's
   real callers — a future cron command, and this stage's own admin
   "Run now" trigger — actually use). Fixed, and closed for good with a
   new regression test that calls the Action with no `CurrentOrganization`
   bound at all, verified to fail against the pre-fix code before
   confirming it passes against the fix.

Both fixes are narrow and additive — no behavior questions, just
infrastructure the app's actual guarantees (a verifiable hash chain, a
non-negative job duration, a working cross-tenant admin trigger) depend
on being correct.

### ADR-57: `ingest-prices`/`generate-alerts`/`backfill-prices` deliberately unwired — real v2 jobs, not placeholders, blocked on Stage 10/11 dependencies

v2's admin Jobs page has three "Run now"-triggerable job cards plus a
separate backfill form — all four are fully implemented in v2 (not
placeholder UI), confirmed via direct source inspection: `runIngestPrices
Job()` calls a real ENTSO-E price-provider integration, `generateAlerts
Job()` creates real `Alert` rows after checking metering-point health.
Neither dependency (`Alert` model, ENTSO-E integration) has been built in
this port yet — both are explicitly out of scope until Stage 10/11. This
port's `App\Http\Controllers\Admin\JobController` and `App\Domain\Jobs\
Actions\TriggerRunVerificationAction` wire up only `run-verification`
(whose full dependency chain — `RunVerificationAction`,
`VerificationCalculationService` — has existed since Stage 7); the other
two job cards render with their description text but no working button
(`Not wired up in this port yet.`), and the backfill form doesn't exist
at all. Same "honest subset" pattern as every prior stage (ADR-33 and
its many descendants) — a nav link or button pointing at nothing would be
worse than the gap being visibly absent. `resources/js/Pages/Admin/
Docs.tsx` — the one page in this stage that's pure static content, ported
content-for-content from v2's real staff guide rather than stubbed, since
unlike the job cards the *content itself* has no unbuilt dependency — was
adjusted in one place to describe this honestly (v2's own copy claims
"three background jobs run on a schedule and can also be triggered
manually," which would be misleading here).

---

# Post-Migration

Stages 1–12 are the migration proper: parity with `hetkin-v2`, end to end.
Everything below is post-migration work — it starts only once parity is
verified and signed off, and it operates under a different mandate.
Migration work optimizes for one thing: *matching v2's observable
behavior exactly*, ADR by ADR. Post-migration work optimizes for the
Laravel codebase's own long-term health — architecture, performance,
security, and operability — while treating that same behavior as the
baseline to protect, not the thing being rewritten. Stage 13 makes this
distinction operational: every ADR from Stages 1–12 gets reviewed and
classified before anything changes, specifically so a refactor and a
product decision are never the same commit.

---

## Stage 13: Technical Modernization

### Objective

Now that feature parity has been achieved, perform a technical
modernization of the Laravel codebase.

This stage is **not** about adding new product features or redesigning
workflows.

Its purpose is to improve maintainability, architecture, performance,
security, developer experience, and long-term scalability while
preserving the existing user experience.

The application should continue behaving exactly as users expect.

Any observable product behaviour changes must come from explicit product
decisions, not technical refactoring.

### Guiding Principles

Use the ADRs generated throughout the migration as the primary input for
this stage.

Every ADR should be reviewed and classified before implementation.

Follow this decision framework:

#### 1. Architecture Improvement

Improve internal implementation where there is no externally observable
behaviour change.

Examples:

- Better Laravel conventions
- Cleaner domain boundaries
- Improved dependency injection
- Better service abstraction
- Better Actions
- Better Policies
- Better Form Requests
- Better Eloquent relationships
- Cleaner folder structure

#### 2. Product Behaviour Preservation

Behaviour intentionally implemented during migration should remain
unchanged.

Do not redesign workflows.

Do not change permissions.

Do not alter business rules.

Do not introduce UX changes.

#### 3. Legacy Product Debt

If an ADR documents legacy behaviour that appears incomplete, unused, or
questionable:

- do not silently "fix" it
- review it deliberately
- determine whether it should remain
- if changed, document the decision with a new ADR
- ensure the change is an intentional product decision

#### 4. Security & Data Integrity

Security improvements always take priority.

Examples:

- session security
- authorization
- audit logging
- transactions
- race conditions
- CSRF
- XSS
- secure headers
- cookie configuration
- data integrity

These improvements may be implemented provided they do not intentionally
alter business behaviour.

### Technical Modernization Scope

Review and improve:

#### Architecture

- remove migration compatibility layers
- eliminate duplicate logic
- improve service boundaries
- improve dependency injection
- simplify abstractions
- improve maintainability

#### Performance

- eliminate N+1 queries
- optimize eager loading
- improve indexing
- optimize queries
- cache expensive operations
- improve queue usage
- optimize asset loading

#### Code Quality

- remove dead code
- improve naming consistency
- improve type safety
- improve documentation
- improve readability
- improve test coverage

#### Security

Perform a complete security review.

Review:

- authentication
- authorization
- session management
- headers
- CSP compatibility
- rate limiting
- audit logging
- secure cookies

#### Developer Experience

Improve:

- documentation
- static analysis
- CI/CD
- testing
- factories
- seeders
- local development experience

### Completion Criteria

Stage 13 is complete when:

- all ADRs have been reviewed
- technical debt has been addressed where appropriate
- architecture reflects Laravel best practices
- performance improvements have been implemented
- security review has been completed
- codebase is ready for long-term maintenance

---

## Stage 14: Progressive Web Application (PWA)

### Objective

Transform Hetkin into a production-grade Progressive Web Application
without changing existing workflows or user experience.

This stage should build upon the stable Laravel application completed in
Stages 1–13.

### Scope

Implement:

- installable desktop and mobile application
- Web App Manifest
- Service Worker
- secure caching strategy
- offline application shell
- application versioning
- automatic cache invalidation
- build versioning
- deployment integration
- update detection
- update notifications
- install prompt
- browser compatibility
- future push notification architecture
- documentation

Ensure:

- existing authentication remains secure
- browser back-button protection remains intact
- protected content is never cached
- updates never interrupt active workflows
- users can refresh when convenient
- deployments automatically generate application versions
- frontend and backend versioning remain synchronized

### Completion Criteria

Stage 14 is complete when:

- Hetkin is installable on supported browsers
- update detection works correctly
- versioning is automated
- deployment integration is complete
- offline experience is implemented
- security guarantees remain unchanged
- documentation is complete

---

## Stage 15: Production Readiness

### Objective

Prepare Hetkin for production deployment.

This stage focuses on operational excellence rather than feature
development.

### Scope

Complete:

#### Performance

- application profiling
- slow query review
- queue performance
- memory usage
- asset optimization

#### Security

- penetration review
- dependency audit
- authentication review
- authorization review
- headers review

#### Accessibility

- WCAG review
- keyboard navigation
- screen reader testing

#### Quality Assurance

- cross-browser testing
- responsive testing
- end-to-end workflow validation
- regression testing

#### Observability

- error monitoring
- health checks
- logging
- metrics
- deployment diagnostics

#### Deployment

- production configuration review
- backup verification
- rollback testing
- migration verification
- deployment checklist

### Completion Criteria

Stage 15 is complete when:

- the application is production-ready
- monitoring is configured
- deployment process has been validated
- rollback procedures have been verified
- critical workflows have been tested
- performance targets have been achieved
- security review has been completed
- the platform is ready for public release
