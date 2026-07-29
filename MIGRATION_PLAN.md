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
| 9. Admin Console | **Complete** | ADR-51..ADR-57 |
| 10. Background Jobs & Scheduler | **Complete** | ADR-58..ADR-63 |
| 11. External integrations (ENTSO-E, Resend, PostHog, Sentry) | **Complete** | ADR-64..ADR-69. ENTSO-E itself was pulled forward into Stage 10. |
| 12. Marketing site + SEO/OG parity | **Complete** | ADR-70..ADR-77 |
| Post-Stage-12: Nav & Account Security parity restoration | **Complete** | ADR-78..ADR-82. Billing icon fix, Team & Permissions + Support restored (deferred by ADR-33), Security integrated into PortalLayout + nav. |
| Post-Stage-12: Enterprise dashboard UX review | **Complete** | ADR-83..ADR-85. 14 of 16 proposed items rejected with reasoning (domain mismatch or premature); Portal Dashboard's v2 stat cards + onboarding checklist (ADR-33's last open gap) closed. |
| New Feature: Labour-Aware Shiftable Load Classification | **Complete** | ADR-86..ADR-91. New product scope (no v2 equivalent) — `labour_classification` on ScheduleBlock, conservative default, filters the recommendation pipeline only. |
| Platform audit: Labour Classification consistency | **Complete** | ADR-92..ADR-95. Marketing/docs copy corrected in 4 places; every Recommendation-derived surface confirmed already correct by construction. |
| 13. Technical Modernization | **Complete** | ADR-96..ADR-102. bfcache middleware extended to portal/admin, baseline security headers, PHPStan+baseline, unused `laravel/breeze` removed, 27 pages gained `<Head title>`, performance/architecture reviewed with no gaps found. |
| 14. Progressive Web Application (PWA) | **Complete** | ADR-103..ADR-109. Customer portal and admin console each install as independent PWAs (own manifest/scope/icons/cache); hand-rolled classic-script Service Worker allow-lists only static assets, never protected content; install eligibility gated server-side (`PwaSurface`) and kept correct across in-app SPA navigation. Three bugs caught by design review before implementation, a fourth (SPA-navigation sync) found via live browser testing — see [PWA.md](PWA.md). |
| Post-Stage-14: Customer portal sign-out control | **Complete** | ADR-110..ADR-111. User-raised question surfaced the customer portal had no sign-out link at all (added one; found and fixed a pre-existing nested-`<button>` bug that silently broke the admin console's own "Sign out" too), then that pressing back afterward still restored the stale authenticated page — traced to Inertia's own client-side history cache (a third mechanism, distinct from ADR-14's HTTP headers), fixed via `Inertia::clearHistory()` + `EncryptHistoryMiddleware`. |

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

## Stage 10: Background Jobs & Scheduler

### ADR-58: Alerts domain — 20-hour dedup window, exact verbatim copy at all four real call sites, `ADOPTION_TREND` ported but deliberately never emitted

`Alert`/`AlertType`/`AlertSeverity` existed in the Stage 9 schema (ADR-51's
neighbor) but nothing ever wrote a row — this stage is the wiring, not a
new data model, same relationship Stage 9 had to `OrganizationNote`/
`JobRun`. `App\Domain\Alerts\Actions\CreateAlertAction` ports
`createAlert()` exactly: skip creating a new alert if an unread
(`read_at IS NULL`) one of the same type already exists for the org from
within a 20-hour window — long enough to cover a daily job's cadence and a
same-day burst of user actions, without silencing a genuinely new day's
alert. `read_at` is never set anywhere in this app, in v2 or here — there
is no "mark as read" feature, a deliberate permanent-unread design, not a
gap.

Confirmed via direct grep against v2 (not the Explore agent's summary
alone) that `createAlert()` has exactly four call sites, and copied each
one's title/body text verbatim rather than paraphrasing:

- `App\Domain\Optimization\Actions\RunOptimizationAction` —
  `NEW_OPPORTUNITY`/`OPPORTUNITY`, only when `RunOptimizationAction`
  produces at least one recommendation, fired *after* the write
  transaction commits (same reasoning as `CreateAlertAction` itself not
  enforcing a transaction — see its docblock).
- `App\Domain\Billing\Actions\GenerateSavingsAssessmentAction` —
  `REPORT_READY`/`INFO`, unconditional on every successful generation.
- `App\Domain\Billing\Actions\RunVerificationAction` —
  `VARIANCE_FLAG`/`WARNING`, only when the verification calculation
  flags variance, per org, inside the existing per-org loop.
- `App\Domain\Alerts\Actions\GenerateAlertsAction` (this stage) —
  `DATA_GAP`/`WARNING`, the one alert type with no single triggering
  event — see ADR-60.

`ADOPTION_TREND` is a real enum case, ported for schema fidelity, but v2
itself never emits it — designing that threshold well needs real usage
data neither codebase has from a single design partner yet. Left out here
for the identical reason, not an oversight.

Research via the Explore agent corrected an initial assumption: alerts are
not admin-only in v2. The customer portal dashboard has its own "active
alerts" stat and list. `App\Http\Controllers\Portal\DashboardController`
now surfaces both — but deliberately *not* the rest of v2's 4-stat
dashboard grid (verified savings MTD/YTD, adoption rate). Those three
depend on a `VerificationRecord` aggregate view this port hasn't built,
a separate, un-blocked gap outside Stage 10's charter; only "active
alerts" is genuinely part of background-jobs/alerts work, and that
scope line is documented in both the controller and page docblocks.

### ADR-59: Pricing infra — a `PriceProvider` abstraction over a hand-parsed ENTSO-E XML client, mirroring v2's own regex-based parser rather than adding an XML library

`App\Domain\Pricing\Contracts\PriceProvider` (`source()`, `biddingZone()`,
`fetchPrices()`) and `App\Domain\Pricing\Services\PriceProviderRegistry`
port v2's `getPriceProvider(source)` factory exactly — `PriceSource::
Nordpool` resolves to a clear `RuntimeException` ("not implemented yet"),
not a silent no-op, matching v2's own reserved-but-unbuilt second source.

`App\Domain\Pricing\Services\EntsoeClient` hand-parses the ENTSO-E
Transparency Platform's XML response via regex (`Period`/`start`+
`resolution`, `Point`/`position`+`price.amount`) rather than pulling in an
XML parsing library — v2's own `src/lib/entsoe.ts` does the identical
regex-based parse, and matching that exactly (including its resolution
handling: `PT60M`→3600s, `PT30M`→1800s, `PT15M`→900s, unknown→3600s
default) was judged more valuable than a "better" implementation that
would parse differently at the margins. Error detection also matches
exactly: non-2xx OR a body containing `Acknowledgement_MarketDocument`
(ENTSO-E's own error-response envelope) OR zero parsed entries, each
raising `App\Domain\Pricing\Exceptions\EntsoeFetchException` with the
extracted `<text>` reason where available.

`App\Domain\Pricing\Services\PriceEntryUpserter` ports
`upsertPriceEntries()`'s chunked (2,000-row batches) Postgres
`INSERT ... ON CONFLICT (bidding_zone, hour_start, source) DO UPDATE`
via Laravel's `upsert()`, updating only `eur_per_mwh` — the original
`created_at` is never touched by a re-ingest of the same hour, matching
v2's identical column-list restriction.

### ADR-60: `ingest-prices` and `generate-alerts` graduate from ADR-57's placeholder cards to fully wired jobs — the honest-subset gap closes on schedule

ADR-57 (Stage 9) left two of the admin Jobs page's four job cards
deliberately unwired, blocked on exactly the two dependencies this stage
builds: the ENTSO-E price provider (ADR-59) and the `Alert` model
(ADR-58). `App\Domain\Pricing\Actions\IngestPricesAction` ports
`runIngestPricesJob()` exactly — a UTC-midnight-anchored 48-hour window
("today + tomorrow"), wrapping any `EntsoeFetchException` in its own
message and any other error in a generic "Unexpected ingestion error",
matching v2's `err instanceof EntsoeFetchError ? err.message : "..."`
branch precisely. `App\Domain\Alerts\Actions\GenerateAlertsAction` ports
`generateAlertsJob()` exactly — iterates every `PILOT`/`ACTIVE`
organization (cross-tenant, `OrganizationScope` bypassed throughout, same
pattern as every other admin-context cross-tenant read since ADR-52), and
per org: no metering point at all is skipped (an onboarding-stage gap
already surfaced by the dashboard's own checklist, not duplicated here);
otherwise flags `DATA_GAP` when the most recent `last_reading_at` across
all the org's metering points is over 7 days old (`DATA_GAP_THRESHOLD_
DAYS`, generous on purpose — Datahub auto-ingestion isn't wired up in v2
either, manual upload is the only path today) or any metering point is
`connection_healthy = false`, with v2's exact title/body branching
between the two conditions. One subtle behavior preserved exactly:
`alertsCreated`/`results[].alertCreated` reflect whether the *condition*
was met, not whether `CreateAlertAction` actually inserted a row — v2's
`createAlert()` returns `void`, so `generateAlertsJob()` can only ever
know it *tried*, and this port's return type (a `bool` from
`CreateAlertAction::execute()`, unlike v2) is deliberately ignored at this
call site to keep that same observable behavior, covered by a dedicated
test.

Both jobs are now wired the same way `run-verification` already was:
`App\Domain\Jobs\Actions\TriggerIngestPricesAction`/
`TriggerGenerateAlertsAction` mirror `TriggerRunVerificationAction`
exactly (swallow-and-log on failure, `admin.role:ADMIN` only), and
`App\Http\Controllers\Admin\JobController`'s three known jobs are now all
`triggerable: true`. `resources/js/Pages/Admin/Jobs.tsx`'s "Not wired up
in this port yet." fallback text has no cards left to render for.

### ADR-61: `BackfillPricesAction` ported exactly, with one deliberate portability adaptation — no raw `date_trunc(...)` SQL, since this app's test suite runs sqlite while production runs Postgres

`App\Domain\Pricing\Actions\BackfillPricesAction` ports
`backfillPrices()`'s full design: chunked (30-day default), idempotent
(re-using `PriceEntryUpserter`'s upsert), skips a chunk already covered by
≥20 rows/day for every day in its range (`MIN_ROWS_PER_COVERED_DAY`,
resolution-agnostic — clears both hourly and 15-minute resolutions
comfortably), retries a transient failure with `1000ms × 2^attempt`
backoff up to 3 attempts by default, fails a chunk immediately (no retry)
on a non-retryable config error (`EntsoeFetchException` whose message
contains "not configured"), and never lets one bad chunk abort the run —
failures are recorded per-range and returned.

One implementation detail intentionally does **not** match v2 line-for-
line: v2's `isChunkAlreadyCovered()` is a raw
`SELECT date_trunc('day', "hourStart") ... GROUP BY ... HAVING count(*) >= ?`
query, valid only against Postgres. This port's test suite runs against
sqlite (`phpunit.xml`), which has no `date_trunc` function, and no other
file in this codebase uses driver-specific raw SQL — every other query,
including `PriceEntryUpserter`'s upsert, goes through Laravel's portable
query builder. Rather than branching on `DB::connection()->getDriverName()`
(a pattern with zero precedent here) or skipping this method's tests
entirely, `isChunkAlreadyCovered()` was reimplemented as a plain Eloquent
fetch-and-group-in-PHP: pull `hour_start` for the chunk's rows, group by
calendar day (`Carbon::toDateString()`), count. Behaviorally identical —
a chunk is at most 30 days, so pulling its timestamps into memory to group
them is negligible — and portable across both connections without a
driver check.

The admin "Run backfill" form (`App\Domain\Jobs\Actions\
TriggerBackfillPricesAction`, wired into `App\Http\Controllers\Admin\
JobController::triggerBackfillPrices` + `App\Http\Requests\Admin\
BackfillPricesRequest`) ports `triggerBackfillPrices()`'s Server Action
exactly, including the one structural difference v2 itself has for this
job: it does **not** go through `JobRunTracker` like the other three
triggers — it creates the `JobRun` row up front and updates it live via
`BackfillPricesAction`'s `onProgress` callback after every chunk, so
refreshing `/admin/jobs` mid-run shows real progress on a `RUNNING` row,
not just a final result. The 400-day cap
(`BackfillPricesRequest::MAX_ADMIN_BACKFILL_DAYS`) matches v2's
`MAX_ADMIN_BACKFILL_DAYS` exactly, with the same reasoning: an admin web
request has an execution-time ceiling a multi-year backfill can exceed.
`php artisan prices:backfill --start=... --end=...` (`App\Console\
Commands\BackfillPricesCommand`) ports `scripts/backfill-prices.ts`
exactly for that larger-range case, with no such ceiling — env vars became
Artisan options (`--source`, `--chunk-days`) for a more idiomatic CLI, the
only interface-level (not behavioral) change.

### ADR-62: Laravel scheduler replaces Vercel Cron HTTP endpoints — no `CRON_SECRET`/`INTERNAL_JOB_SECRET` ported, because there is no HTTP surface left to protect

v2 runs its three background jobs as Vercel Cron HTTP endpoints
(`vercel.json`'s `crons` array calling `/api/internal/{ingest-prices,
generate-alerts,run-verification}`), each gated by
`verifyInternalJobRequest()`'s shared-secret check — necessary there
because Vercel Cron calls the *deployed app* over the public internet, and
without that check the endpoint would be an open door for anyone who
found the URL. `routes/console.php` registers the direct Laravel
equivalent instead: `Schedule::command('jobs:ingest-prices')->dailyAt
('05:00')`, `Schedule::command('jobs:generate-alerts')->dailyAt('06:00')`,
`Schedule::command('jobs:run-verification')->monthlyOn(1, '03:00')`, each
`->timezone('UTC')` explicitly (matching `config('app.timezone')`, already
`'UTC'`, but self-documenting against `vercel.json`'s cron expressions,
which are UTC by Vercel Cron's own contract). All three times match
`vercel.json` exactly (`0 5 * * *`, `0 6 * * *`, `0 3 1 * *`), confirmed
via `php artisan schedule:list`.

Laravel's scheduler has no HTTP surface at all: `schedule:run` executes
`App\Console\Commands\{IngestPricesCommand,GenerateAlertsCommand,
RunVerificationCommand}` in-process, on the same box running the app —
there is nothing here for a shared secret to protect, and porting one over
would just be unused config with no attacker it defends against. Each
command is a thin wrapper: call the same job body the admin's manual
"Run now" trigger calls, through the same `JobRunTracker`, with
`triggered_by: 'cron'` — identical to v2's own route handlers, which call
`withJobRunTracking(name, "cron", jobBody)`. Unlike v2's route handlers,
which also call `Sentry.captureException()` on failure, these commands
log via `Log::error()` only — this port has no Sentry integration wired
up anywhere else either, so adding one here alone would be new
infrastructure, not a port; `Log::error()` matches the logging-only half
of v2's own error path (`console.error`) and the `JobRun.error_message`
row is already the durable record either way.

### ADR-63: Live smoke test confirmed the full background-jobs pipeline end-to-end — no regressions found, including the real ENTSO-E failure path

Following this project's established practice (ADR-20/36/40/45/50/56):
`migrate:fresh` against real Postgres, then a live browser session against
`php artisan serve`, both realms.

- **Admin** (`/admin/jobs`): all three job cards now show a working
  "Run now" (ADR-60 closing ADR-57's gap). Clicked all three for real,
  with no `ENTSO_E_TOKEN` configured in this local environment —
  `ingest-prices` failed exactly as designed
  (`ENTSO_E_TOKEN not configured`, recorded as a `FAILED` `JobRun`
  row and shown in Recent runs), `generate-alerts` succeeded against zero
  organizations. The backfill form (ADR-61) was submitted for a real
  4-day range with the same missing token: the chunk failed but the
  overall run still reports `succeeded` with `chunksFailed: 1` in its
  summary and the flash message correctly renders "Done with 1 failed
  chunk(s)…" — confirmed live, matching v2's behavior of never letting
  one bad chunk fail the whole backfill.
- **Portal** (`/dashboard`): seeded one real `Alert` row for a signed-in
  member's organization — the "1 active alert" badge and the Alerts card
  (severity dot, title, body, date) both rendered correctly (ADR-58).

No bugs found this time — unlike ADR-56's Stage 9 smoke test, which
caught two real Postgres-only issues, this stage's live run matched its
sqlite-backed test suite's expectations exactly. Test data was cleared
via a second `migrate:fresh` immediately after.

## Stage 11: External Integrations

### ADR-64: Resend — already functionally wired by earlier stages; Stage 11 closes the remaining gap and fixes one real behavior mismatch

Confirmed via direct inspection that every notification this stage's
scope covers was already built in earlier stages
(`App\Notifications\{InvitationResent,PilotApplicationApproved,
AdminAccountCreated,PilotApplicationSubmitted}`, `config/services.php`'s
`resend.key`, `config/mail.php`'s `pilot_notification_address`) — each
one's own docblock said outright "wiring a real transport is Stage 11
scope." What was actually missing: `resend/resend-php` was referenced in
`composer.lock` but never actually required (`composer show` returned
"not found"), so Laravel's `resend` mailer transport would have failed at
runtime despite looking configured. `composer require resend/resend-php`
fixes that; `.env.example` now documents `RESEND_API_KEY`, `CONTACT_EMAIL`,
and production `MAIL_MAILER=resend`/`MAIL_FROM_ADDRESS` — mirroring v2's
own `.env.example`'s "Email (Resend)" section, including its exact
domain-verification caveat (an unverified Resend domain rejects sends to
any recipient but the account owner's own address).

One real behavior mismatch found and fixed: v2's `notifyTeam()` (the
internal new-pilot-application alert) hardcodes
`from: "Hetkin pilot applications <onboarding@resend.dev>"` rather than
using the shared `RESEND_FROM_ADDRESS` every other email uses — safe only
for that one email because it always sends to `CONTACT_EMAIL` (the
account owner's own inbox), so it was never blocked by domain
verification and never needed fixing. This port's `PilotApplicationSubmitted`
notification had no `->from()` override, so once `MAIL_FROM_ADDRESS` gets
set to a real verified address in production, that internal alert would
have silently started sending from the wrong address. Fixed by adding the
identical hardcoded `->from('onboarding@resend.dev', 'Hetkin pilot
applications')` override, covered by a dedicated test asserting the
`from` field.

### ADR-65: PostHog server-side capture — `App\Domain\Analytics\Services\PostHogService`, all nine real v2 call sites wired with exact event names, distinctIds, and properties

Ports `src/lib/analytics/server.ts`'s `captureServerEvent()` exactly:
constructs a `PostHog\Client` directly (not the package's static facade,
for constructor-injection testability matching every other Action
dependency in this codebase) and explicitly calls `flush()` after every
`capture()` — the same trade v2's `posthog-node` client makes via
`flushAt: 1, flushInterval: 0`, for the identical reason: a short-lived
process (Vercel's serverless functions there, a single PHP-FPM request
here) can end before a delayed batch ever sends, silently losing events.
A missing/unset `POSTHOG_KEY` makes `capture()` a silent no-op, never
throwing into its caller — same posture as every other optional
integration in this app.

All nine real `captureServerEvent()` call sites were found via direct
grep against v2 (not assumed from the domain), each ported with v2's
exact event name, `distinctId` choice, and property payload:

| Event | Action | distinctId |
|---|---|---|
| `pilot_application_submitted` | `SubmitPilotApplicationAction` | contact email |
| `pilot_application_approved` | `ApprovePilotApplicationAction` | contact email (same as above, chains the funnel) |
| `onboarding_completed` | `AcceptInvitationAction` | the new user's id, plus a PostHog `$set: {email}` |
| `meter_data_uploaded` | `UploadMeterReadingsAction` | uploading user's id |
| `recommendation_decision` | `DecideRecommendationAction` | deciding user's id |
| `schedule_version_applied` | `ApplyDecisionsToNewScheduleVersionAction` | applying user's id |
| `savings_assessment_generated` | `GenerateSavingsAssessmentAction` | generating user's id |
| `verification_computed` | `RunVerificationAction` | the organization id (cron, no user session) |
| `invoice_issued` | `IssueInvoiceAction` | the organization id (admin action, org-scoped) |

Every call site fires after its domain transaction commits (never inside
one — an analytics call has no business holding a database lock), and
every no-op guard clause v2 has (a non-DRAFT invoice, an
already-a-member invitation acceptance) is preserved exactly: the event
simply never fires on those paths, covered by dedicated tests for both.
`onboarding_completed`'s PostHog `$set` property (updating the person
profile's email, switching PostHog's identity from the pre-signup email
distinctId to the real user id) is passed through as a literal
`'$set' => [...]` array key — PostHog's own wire protocol convention, not
Laravel-specific.

### ADR-66: PostHog client-side — one `initPostHog()` call, not a `<PostHogProvider>` component; `VITE_`-prefixed env vars split from v2's single `NEXT_PUBLIC_` pair

Ports `src/components/PostHogProvider.tsx`'s init call exactly
(`capture_pageview: true`, `person_profiles: 'identified_only'`) from
`resources/js/lib/analytics.ts`, called once in `resources/js/app.tsx`'s
bootstrap. Deliberately not wrapped as a context-providing
`<PostHogProvider>` component the way v2 does: nothing in this port calls
posthog-js's `usePostHog()` hook (every meaningful event is captured
server-side, per ADR-65), so there's no component tree that needs the
context v2's wrapper provided — a plain init call is the same observable
behavior with less machinery. Inertia navigations use the History API
(`pushState`), the same mechanism a Next.js App Router client-side
transition uses, so posthog-js's `capture_pageview` autocapture already
listens for exactly that with no extra Inertia router-event wiring
needed.

`POSTHOG_KEY`/`POSTHOG_HOST` (server, read directly by PHP) and
`VITE_POSTHOG_KEY`/`VITE_POSTHOG_HOST` (client, bundled into the browser
build) are two separate env-var pairs set to the same real PostHog
project key/host in production — split only because Vite exposes
`VITE_`-prefixed vars to client code and nothing else, unlike v2's single
`NEXT_PUBLIC_POSTHOG_KEY` which served both a server-side Node process and
the browser bundle from the identical Next.js-specific prefix convention.
v2's own cookies-policy page admits a cookie-consent banner "is being
rolled out" and doesn't exist yet — so none was added here either,
matching v2's actual (not aspirational) behavior per this project's
standing "port v2's real gaps, don't silently fix them" principle
(ADR-42/48 and others).

### ADR-67: Sentry server-side — `sentry/sentry-laravel`'s `Integration::handles()` hook is the direct equivalent of v2's `onRequestError`; wired into all three Stage 10 cron commands, revisiting ADR-62's explicit deferral

`bootstrap/app.php`'s `->withExceptions()` callback now calls
`Sentry\Laravel\Integration::handles($exceptions)` — every exception
Laravel's own handler sees gets reported, with no per-call-site
`captureException()` needed, the exact same shape as v2's
`instrumentation.ts` `export const onRequestError = Sentry.
captureRequestError`. `config/sentry.php` (published from the package)
keeps every stock operational default (breadcrumbs, SQL/cache/queue
tracing toggles) — v2 configures none of that, but none of it is
customer/admin-observable product behavior either, so there was nothing
to match by removing it; only `traces_sample_rate` was edited to
replicate v2's actual `NODE_ENV === "development" ? 1.0 : 0.1` split
(`local` being this app's own `APP_ENV` equivalent of that check), since
the package's own stock default is `null` (tracing off entirely).

Stage 10's ADR-62 deliberately deferred Sentry reporting in
`IngestPricesCommand`/`GenerateAlertsCommand`/`RunVerificationCommand`'s
catch blocks, reasoning that adding Sentry for just those three call
sites — before the app had any Sentry integration at all — would be new
infrastructure, not a port. Now that the integration exists, all three
commands call `Sentry\withScope()`/`captureException()` with a `job` tag,
matching v2's own job-route catch blocks exactly (`Sentry.
captureException(err, { tags: { job: "ingest-prices" } })`), and
`generate-alerts`/`run-verification` each got a new failure-path test
(only `ingest-prices` had one from Stage 10) to actually exercise the new
code, not just trust the pattern.

A real bug surfaced and was fixed while wiring `traces_sample_rate`:
calling `app()->environment('local')` directly inside `config/sentry.php`
crashed the entire application on boot (`php artisan list` itself failed
with a confusing "Target class [env] does not exist" error) — config
files are evaluated during Laravel's `LoadConfiguration` bootstrap phase,
before the container is guaranteed to hold a fully-capable `Application`
instance, so calling into `app()` from inside one is unsafe. Fixed by
reading `env('APP_ENV', 'production')` directly instead — config files
should be side-effect-free and derived only from `env()`, never from
`app()`-dependent logic, which this fix now also matches everywhere else
in this codebase's config files.

### ADR-68: Sentry client-side — `@sentry/react` + one `ErrorBoundary` consolidating v2's four route-segment error boundaries

Ports `src/instrumentation-client.ts`'s init exactly (`dsn` with no
fallback string — a genuinely no-op SDK when `VITE_SENTRY_DSN` is unset,
`environment`, and the identical dev/prod `tracesSampleRate` split via
Vite's `import.meta.env.DEV`) from `resources/js/lib/sentry.ts`, called
once alongside `initPostHog()` in `resources/js/app.tsx`.

v2 has four separate error boundaries — `src/app/error.tsx` (marketing),
`global-error.tsx` (root, catches what escapes every other boundary),
`(portal)/error.tsx`, and `admin/(protected)/error.tsx` — because the App
Router's file-based routing gives each nested route segment its own
boundary automatically. Inertia has no equivalent per-segment convention
to hang four files off of, so `resources/js/Components/ErrorBoundary.tsx`
consolidates all four into one class component wrapped around the whole
app once, picking its fallback copy and "go back" destination at render
time via a `detectRealm()` helper that mirrors v2's own client-side
`isDashboardHost`/`isAdminHost` hostname-prefix check
(`src/lib/subdomain-client.ts`) plus the bare-path form (`/dashboard`,
`/admin`) this port's non-subdomain routes also need — deliberately
reading `window.location` directly rather than Inertia's `subdomain`
shared prop, for the same reason v2's own client-side helper avoids
`headers()`: the boundary has to keep working even when the render that
threw happened without a reliable page context. Reports via
`Sentry.captureException(error, { contexts: { react: { componentStack
}}})` in `componentDidCatch`, matching all four of v2's boundaries.

Adding `@sentry/react` and `posthog-js` together grew the client bundle
from ~345 kB to ~610 kB gzipped-equivalent total (Vite now code-splits
Sentry into its own ~366 kB chunk) — a real, measurable cost with no
product-behavior downside, noted here rather than addressed now since
bundle-size optimization (dynamic imports, code-splitting) is explicitly
Stage 13 (Technical Modernization) territory, not a migration-parity
concern.

### ADR-69: Live smoke test caught a real, systemic bug — every `Notification` class in this app was `ShouldQueue`, and no queue worker exists anywhere to process them

Following this project's established practice (ADR-20/36/40/45/50/56/63):
`migrate:fresh` against real Postgres, then a live browser session
against `php artisan serve`, with no `RESEND_API_KEY`/`POSTHOG_KEY`/
`SENTRY_LARAVEL_DSN` configured — deliberately, since every one of these
three integrations' actual design point is graceful degradation without
credentials, and that degraded path is exactly what's verifiable without
real third-party accounts.

Submitting a real pilot application via the public form persisted the
`PilotApplication` correctly, but the internal notification email never
appeared in `storage/logs/laravel.log` — `MAIL_MAILER=log` should have
written it there immediately. Investigation found the actual cause:
`DB::table('jobs')->count()` returned 1. `PilotApplicationSubmitted`
(and every other `App\Notifications\*` class in this app —
`InvitationResent`, `PilotApplicationApproved`, `AdminAccountCreated`,
`PasswordResetRequested`, `PasswordResetCompleted`, `PasswordChanged`,
`LoggedOutAllDevices`) `implements ShouldQueue`. With
`QUEUE_CONNECTION=database` (this app's own local default) and no
`php artisan queue:work` process running anywhere — not in
`.claude/launch.json`, not in any Supervisor/systemd config, not
mentioned in any ADR — a queued notification only ever gets a row
inserted into the `jobs` table. It never actually sends. This is a real,
systemic behavior regression from v2, which has no queue system at all:
every email in v2 is awaited inline, synchronously, as part of the same
Server Action that triggers it — the customer or admin either gets the
email (or, with no Resend key, sees it logged) before the response
returns, full stop.

Fixed by removing `ShouldQueue`/`Queueable` from the four notifications
that are genuine v2 ports with no documented reason to queue —
`PilotApplicationSubmitted`, `PilotApplicationApproved`,
`InvitationResent`, `AdminAccountCreated` — restoring the synchronous
send v2 always does. The other four —
`PasswordResetRequested`/`PasswordResetCompleted`/`PasswordChanged`/
`LoggedOutAllDevices` — were deliberately left `ShouldQueue`:
`tests/Feature/Notifications/AccountSecurityNotificationsTest.php`
already has a test, `'every account-security notification is queued'`,
enforcing this as a tested requirement from the Account Security
module's own brief ("Queued email notification") — Account Security was
an out-of-band addition ahead of Stage 2, not a v2 port, so there's no
v2 behavior to match here in the first place, and overturning a
deliberate, tested decision on the strength of a different bug's root
cause would have been the wrong fix. The now-confirmed consequence —
these four notifications also never actually send in this app's current
deployment, for exactly the same reason — is a real gap, but the correct
fix is standing up real queue-worker infrastructure (Supervisor, a
systemd unit, or equivalent, with monitoring for stuck jobs), which is
Stage 15 (Production Readiness) territory, not something to paper over
by silently dropping a tested requirement.

Re-verified after the fix: resubmitting the pilot application produced
`DB::table('jobs')->count() === 0` and the full internal-notification
email (subject, company, contact, ICP score) appeared in
`storage/logs/laravel.log` immediately. Approving the application from
the admin console produced the same result for
`PilotApplicationApproved`. `PostHogService`'s and Sentry's client/server
no-op guards were confirmed separately: no error surfaced anywhere in
either flow, and the browser console showed no errors on a normal page
load with both SDKs initialized and no keys configured — the same shape
of confirmation Stage 10's ADR-63 already established for this project's
other "safe no-op without a key" integrations (ENTSO-E). `ErrorBoundary`
was verified via `tsc`/`vite build` passing cleanly rather than a forced
live render-phase crash — React error boundaries only catch errors
during rendering/lifecycle/constructors, not from a browser console
injection, so safely forcing one from outside the app wasn't practical;
its logic was reviewed directly instead. Test data (including the two
smoke-test admin/pilot rows and the stray queued job) was cleared via a
second `migrate:fresh` immediately after.

---

## Stage 12: Marketing Site + SEO/OG Parity

### ADR-70: The marketing site is closure routes rendering static Inertia pages, not a controller per page — same precedent as `Admin/Docs.tsx`

v2's marketing site (`src/app/{page,pricing,faq,how-it-works,trust,
methodology,beta,docs}/page.tsx`, `src/app/legal/*`,
`src/app/pilot-program/page.tsx`) is entirely static content — no page
reads from a model or accepts route parameters. `routes/marketing.php`
registers each as a one-line `Route::get(...)->name(...)` closure calling
`Inertia::render(...)`, the same pattern already established for
`Admin/Docs.tsx` in `routes/admin-protected.php`. A controller class per
page would be pure ceremony here: nothing to inject, nothing to
authorize, nothing to validate. The one exception is the pilot-program
landing page (`PilotProgram/Index.tsx`), registered in `routes/pilots.php`
alongside the existing `pilot-program.apply` controller routes rather
than in `routes/marketing.php`, keeping the whole `pilot-program/*` URL
family in one file. `routes/web.php` no longer needs its Stage-1-era
`TODO(Stage 12 — Marketing site)` comment reserving `'/'` — that
placeholder is now `home`.

### ADR-71: Every new page ports v2's exact Tailwind class names verbatim, including two of v2's own undefined/no-op classes (`text-ink-400`, `bg-surface-50`)

The "Legacy alias" CSS token system (`resources/css/app.css`, ADR-42/48)
exists precisely so pages can be ported by copying v2's className strings
unchanged — `bg-navy-900`, `text-ink-500`, `border-line-100`, etc. all
resolve through `var()` to this port's canonical brand tokens. Two classes
that appear in v2's own source — `text-ink-400` (`Trust.tsx`'s security-
contact line) and `bg-surface-50` (`legal/cookies/page.tsx`'s essential-
cookies table header) — were checked directly against v2's own
`globals.css` and are **not defined there either**: v2's token system only
goes to `ink-500` and `surface`/`surface-0`, so both classes are silent,
harmless no-ops in the original app too, not omissions in this port. Per
the standing principle (ADR-42/48: port v2's real gaps as-is rather than
"fixing" them into a new alias), both were left verbatim.

### ADR-72: `PilotProgram/Apply.tsx` gains `SiteHeader`/`SiteFooter` — a real, deliberate visual change from Stage 8, not new content

Stage 8 shipped `Apply.tsx` standalone (just a centered `Logo`, no site
chrome) because Stage 12 — the marketing site the header/footer link
back into — didn't exist yet; its own docblock said so explicitly.
v2's `pilot-program/apply/page.tsx` has always wrapped the form in
`SiteHeader`/`SiteFooter`, including around the post-submit success
card. This stage closes that gap: both the form state and the
`application-submitted` success state now render inside the shared
marketing chrome, matching v2 exactly. No Action, validation, or
controller logic changed — `SubmitPilotApplicationAction` and its Stage 8
test coverage are untouched; this was a template-only change, confirmed
by the full Pest suite passing unchanged (487/487) after the edit.

### ADR-73: `Beta.tsx` and `Docs.tsx` are `noindex, nofollow` via a literal `<meta>` inside Inertia's `<Head>`, not a typed `robots` prop — because Inertia's `<Head>` has no such API

v2 sets `metadata = { robots: { index: false, follow: false } }` on both
pages — Next's typed metadata object, which Next itself lowers to a
`<meta name="robots" ...>` tag at render time. `@inertiajs/react`'s
`<Head>` has no equivalent typed metadata API; it's a thin portal for
arbitrary `<head>` children. The direct, honest equivalent is writing the
resulting tag literally: `<Head title="..."><meta name="robots"
content="noindex, nofollow" /></Head>`. Verified live: both pages'
rendered `<head>` contains the tag (confirmed via
`document.querySelector('meta[name=robots]').content` in the smoke test
below). Both pages remain deliberately unlinked from `SiteHeader`'s nav
and `SiteFooter`, matching v2 — reachable by direct URL only, same
"unlisted, not access-controlled" posture v2 chose (there's no
sensitive data on either page).

### ADR-74: The 404 page is `Route::fallback()` rendering an Inertia page at a real 404 status, not a 200-status "not found" view

Next.js renders `not-found.tsx` automatically for any path that doesn't
match a route (or an explicit `notFound()` call), at a genuine 404 HTTP
status. Laravel's closest built-in equivalent is `Route::fallback()`,
registered last in `routes/web.php` (after every `require`, including
the new `marketing.php`) so it only ever catches a request nothing else
matched. It renders `Inertia::render('NotFound')` and explicitly forces
the status code — `->toResponse(request())->setStatusCode(404)` — since
`Inertia::render()` defaults to 200 and getting the status code right
matters for anything that respects it (crawlers, monitoring, the
`noindex`-adjacent instinct of not serving "missing page" content as if
it were real). `NotFound.tsx` ports v2's `not-found.tsx` verbatim:
no `SiteHeader`/`SiteFooter`, just a centered heading and a link back to
`home`. Confirmed live at a nonsense path: page renders, and
`read_network_requests` showed the response as `404 Not Found`.

### ADR-75: The app-wide missing-per-page-`<Head title>` gap (23+ pre-Stage-12 pages) is explicitly deferred, not silently dropped

While researching this stage it became clear that no page anywhere in
this app prior to Stage 12 — across all 11 previous stages, 23+ pages —
ever calls `<Head title="...">`; every one of them silently falls back
to `resources/js/app.tsx`'s `DEFAULT_TITLE`. This predates Stage 12
entirely and isn't in its scope (marketing/SEO parity for the *new*
public-facing pages), so it wasn't fixed here. It's recorded here rather
than left implicit so it isn't mistaken for an oversight: every one of
Stage 12's own 11 new pages *does* set a page-specific `<Head title>`
(or deliberately omits it, per ADR-73's homepage case — v2's own
homepage has no `metadata` export either), so this stage is fully
parity-correct for its own pages. Retrofitting titles onto the
pre-existing 23+ pages is left as a candidate for Stage 13's post-
migration cleanup, where product-neutral polish like this belongs.

### ADR-76: `<Analytics />` (Vercel Analytics) is a deliberate, unported omission — no Laravel/Vercel-neutral equivalent exists

v2's root `layout.tsx` renders `<Analytics />` from `@vercel/analytics/
react`, a Vercel-hosting-specific pageview beacon, alongside PostHog
(already fully ported in Stage 11). This port doesn't deploy on Vercel,
so there is no equivalent service to wire it to, and inventing one would
be new infrastructure, not a rewrite of existing behavior. PostHog's
`capture_pageview: true` (Stage 11, ADR-66) already provides the
pageview-analytics behavior this app actually needs; `<Analytics />`'s
omission is a deployment-target difference, not a feature gap.

### ADR-77: Live smoke test — every new marketing/legal page render-verified at `localhost:8123`, plus the 404 fallback and `Apply.tsx`'s new chrome

Ran the full verification loop before this smoke test: `tsc --noEmit`
clean, `eslint --fix` (only prettier formatting deltas, no logic
changes), `pint --test` passed, `vite build` succeeded, and
`php artisan test` passed 487/487 (1,545 assertions) unchanged. Then,
against the running dev server: `/` (homepage, nav links confirmed
present in the DOM — responsively hidden below the `md` breakpoint in a
narrow viewport, not missing), `/pilot-program` (timeline + sample
savings-assessment card), `/beta` and `/docs` (both confirmed
`noindex, nofollow` via `document.querySelector`), `/legal/privacy` and
`/legal/cookies` (including the essential-cookies table), a nonsense
path (confirmed real `404 Not Found` via network inspection, not just
visual), and `/pilot-program/apply` (confirmed `SiteHeader`/`SiteFooter`
now wrap the form, per ADR-72). All rendered correctly against the
brand tokens with no console errors. `route:list` was cross-checked
against every route name referenced by `SiteHeader.tsx`, `SiteFooter.tsx`,
and `FaqAccordion.tsx` to confirm none were left dangling.

---

## Post-Stage-12: Navigation & Account Security Parity Restoration

### Objective

A review of the completed migration flagged four items as missing
against the intended product: the Billing nav icon, the Team &
Permissions module, the Support module, and a discoverable Security
section. Investigated each against `MIGRATION_PLAN.md`'s own ADRs, v2's
source, and the current codebase before writing any code — findings and
fixes below.

### ADR-78: Billing's nav icon was an unintentional near-miss, not a documented decision — `Receipt` → `CreditCard`

`PortalLayout.tsx` used lucide-react's `Receipt` for the Billing nav
item; v2's `PortalShell.tsx` (`getNavItems()`) uses `CreditCard`. No ADR
discusses a deliberate substitution — the two icons are visually similar
enough that this was a near-miss during the Stage 7 port, not a choice.
Fixed by import swap; no other change needed since `NavLink` just
renders whatever icon node it's given.

### ADR-79: Team & Permissions and Support restored — deferred by ADR-33 (Stage 5), never revisited by any later stage, backend already built for them

ADR-33 deliberately scoped Stage 5's portal nav down to an "honest
subset" (no nav link to a page that didn't exist yet), with both
`PortalLayout.tsx` and `routes/portal.php` carrying docblocks promising
"extend this list as later stages add real pages." No stage between 6
and 12 did. Meanwhile `MemberPolicy::view()` and `InvitationPolicy::view()`
(Stage 3) were already written anticipating this exact page — both
docblocks say, verbatim, "matches the portal 'team' page (a later
stage)" — and both are registered via `Gate::policy()`. This was a
genuine loose end, not a deferred-and-abandoned decision.

Restored as a straight port of v2's two pages
(`src/app/(portal)/dashboard/{team,support}/page.tsx`):
`App\Http\Controllers\Portal\TeamController` (members + pending
invitations, read-only — v2 itself never built an invite-member form on
this page either, so this port doesn't invent one) and
`App\Http\Controllers\Portal\SupportController` (static content, no
query, matching v2's own "no `requirePortalContext()` call here"
comment). Both registered in `routes/portal.php` alongside the existing
portal routes (so both mount points — main-domain-prefixed and the
`dashboard.` subdomain — get them for free, same as every other portal
route). `PortalLayout`'s nav now lists all seven of v2's original items;
ADR-33's "honest subset" caveat no longer applies since nothing here
points at a nonexistent page anymore.

### ADR-80: Security — not a v2 feature (ADR-13..20's out-of-band addition), fully built since Stage 1, but never wired to `PortalLayout` or linked from anywhere

Unlike Team/Support, this has no v2 page to restore parity against —
it's this port's own addition. The backend and page logic
(`Settings/Security.tsx`: password change, active-session list/revoke,
logout-other-devices, staleness recommendations) were complete and
tested by Stage 1. The gap was pure integration: the page's own docblock
said it used temporary placeholder chrome "rather than inventing
settings-page chrome that will just be replaced" once `PortalLayout`
existed — Stage 5 built that layout and never came back to do the
replacement, and grepping the entire frontend turned up zero links to
`route('settings.security')` anywhere. Fully functional, completely
undiscoverable.

Fixed by rewrapping `Security.tsx` in `PortalLayout` (`Security.layout =
...`, matching every other portal page) and adding a nav entry — appended
after the v2-mirrored items rather than interleaved with them, since
there's no v2 position to match against; uses `route('settings.security')`
directly rather than `${base}/...` string-concatenation like every other
item, because that route (`routes/settings.php`) was never inside the
portal's `dashboard`-prefix group to begin with, and — per
`routes/domains.php`'s own docblock — isn't `->domain()`-constrained
either, so it already matched every host without any routing change.
Every `Card`'s content is byte-for-byte unchanged from the original;
only the outer wrapper and page heading changed, plus the standalone
"Signed in as…"/"back to dashboard" footer was dropped as redundant now
that `PortalLayout`'s own sidebar provides that navigation.

### ADR-81: Security's requested sub-features, checked individually against what the architecture actually supports — nothing invented

Per instruction to implement only what's already backed and to prepare
documented extension points for the rest:

- **Active sessions, password management** — already fully real (Stage
  1); no change beyond the layout wrap above.
- **Recent login history** — genuinely backed, but only partially:
  `App\Domain\Auth\Actions\LoginAction` already writes an `auth.login`
  `AuditLogEntry` per successful sign-in, but captures no IP/user-agent
  on it (unlike `SessionRecord`, which does). `SecurityController` now
  queries the last 5 such entries for the signed-in user directly off
  `AuditLogEntry` (not a new service — `AuditLogService` only ever
  writes, per its own docblock; five rows read straight off the model
  didn't earn a new abstraction) and the page shows their timestamps
  only. Deliberately not showing device/location: that data isn't
  captured at this point in the code today, and fabricating it would
  violate the "don't invent" instruction as much as fabricating the
  feature would.
- **Two-factor authentication** — confirmed zero infrastructure exists
  anywhere in the codebase (no columns, no TOTP/WebAuthn package, no
  enrollment flow — checked directly, not assumed). Not built. Added a
  visibly inert `TwoFactorCard` ("Coming soon" badge, no interactive
  controls) with a docblock naming the concrete extension points (a
  secret/recovery-codes column set on `users`, an enrollment flow, a
  second-factor check inserted into `LoginRequest` alongside its
  existing credential check) for whoever picks this up later.
- **Security notifications** — already real behavior (the four queued
  Account Security notifications from Stage 1/11 fire automatically on
  password reset/change/logout-all-devices). Documented as a one-line
  informational note on the page, not a new preferences UI — there is
  nothing to toggle; these unconditionally fire.
- **Trusted devices** — no separate concept from "active session" in
  this architecture; each `SessionRecord` row already IS one device's
  session. Documented as a code comment rather than built as a second,
  parallel list that would just duplicate the sessions card under a
  different name.

### ADR-82: Validation — full suite, live smoke test of all four fixes, mobile nav drawer confirmed, dark mode confirmed unaffected (app has no dark-mode implementation anywhere, not a gap introduced here)

Added 8 new tests (`TeamControllerTest`, `SupportControllerTest`,
`SecurityPageTest` — including a test that recent-login history is
capped at 5 and scoped to the requesting user only, not leaked across
users) — full suite passed 495/495 (1,611 assertions). `tsc --noEmit`,
`eslint` (two real files needed `--fix` for prettier-only deltas, no
logic changes), and `pint --test` all clean; `vite build` succeeded.

Live smoke test against a freshly seeded dev database: signed in as the
seeded customer owner, confirmed all 8 sidebar items render (Dashboard
through Security) with correct icons including the corrected Billing
icon; visited `/dashboard/team` (real seeded member listed, correct
role), `/dashboard/support` (static content matches v2), and
`/settings/security` (all six cards render, including a real recent-login
timestamp from an actual sign-in during the same session and the inert
2FA card). Confirmed responsive behavior at mobile width (375px): the
hamburger-triggered nav drawer opens and lists all 8 items with correct
icons, including the newly-added ones. Confirmed dark mode: this app has
no dark-mode implementation anywhere (no toggle, no `prefers-color-scheme`
wiring beyond the unused `@custom-variant dark` class-based hook already
in `app.css` from before this work) — forcing the browser's OS-level
color scheme to dark rendered identically to light, which is existing,
unchanged app-wide behavior, not a regression introduced here.
Authorization re-confirmed via the automated suite (non-member redirected
to sign-in on both new routes) rather than repeated manually in-browser,
since that path was already covered.

---

## Post-Stage-12: Enterprise Dashboard UX Review

### Objective

A UX review proposed 16 enterprise-dashboard enhancements, referencing
external (non-Hetkin) energy-dashboard screenshots as inspiration for
enterprise information architecture, not as a copy target. Each item was
evaluated against the decision framework the review itself specified:
does it improve operational decision-making, does it fit Hetkin's actual
domain model, does it integrate with existing entities, and would a
customer naturally expect it. Investigation happened first — reading the
current `DashboardController`/`Dashboard.tsx`, the full domain model
(Organizations, Metering, Optimization, Pricing, Billing), the existing
Admin console, and v2's real dashboard source — before any code changed.

### ADR-83: Fourteen of sixteen proposed enhancements rejected against the decision framework — reasoning recorded per item, not silently dropped

Most of the review's list doesn't fit Hetkin's actual domain or is
already built:

- **Context switching (org/site/metering point/market area)** —
  structurally doesn't apply. A customer belongs to exactly one
  organization (`Member` is a 1:1 link). `Site` is auto-bootstrapped as
  a singleton per org (`UploadMeterReadingsAction`:
  `Site::firstWhere('organization_id', ...) ?? Site::create(...)`) —
  nothing to switch between. `BiddingZone` is a hardcoded single case
  (`FI`) — no multi-market concept exists, in v2 or here.
- **Carbon intensity / carbon reduction indicators** — no carbon data
  exists anywhere in the schema or the ENTSO-E integration. Absent from
  Hetkin's domain entirely; specific to the reference screenshots, not
  something to backfill.
- **Recommendation confidence scores** — confirmed zero infrastructure
  (grepped the whole `Optimization` domain). Not built, per the review's
  own instruction not to invent AI confidence values — same posture as
  ADR-81's 2FA extension point: no UI, no placeholder, nothing to see
  until a real scoring mechanism exists.
- **Portfolio benchmarking, org-vs-org comparison, month/quarter/year
  trend views** — the domain could support these eventually, but a
  pilot-stage customer has days-to-weeks of real data, not quarters or
  years. Building comparison UI against data that doesn't exist yet
  would render misleading flat/empty charts — premature, not merely
  unbuilt.
- **Executive Portfolio Dashboard / Executive Operations Center** — already
  exists. `Admin\DashboardController` + `Admin/Overview.tsx` (Stage 9)
  already show org counts by status, pending pilots, baseline/
  verification/invoice status, ENTSO-E price staleness, job-run health,
  audit-chain integrity, and a computed `needs_attention` flag — this
  ask, already real, already tested. Nothing added.
- **Data freshness indicators** — already implemented: `connection_healthy`
  and `last_reading_at` are already shown on `Portal/Data.tsx`
  (confirmed by reading the page and its controller); `pricing_freshness`
  already exists on the Admin Overview. Nothing missing.
- **Historical playback** — `ScheduleHistory.tsx` and versioned baselines
  already cover this per-entity (Stages 5-7); a separate unified
  "playback" surface would be new information architecture with no v2
  precedent and no concrete request behind it.
- **CSV/Excel export, heatmaps, interactive zoom/pan/quarter/year chart
  ranges** — not backed by any ADR or v2 precedent, and not justified by
  data that exists yet at pilot scale. Flagged as candidate **future
  product decisions**, not built now — per the review's own framework
  ("if the answer is no... recommend whether it should become a future
  product decision").

### ADR-84: The one genuine, fully-backed gap — v2's real dashboard stat cards and onboarding checklist, closed using data that now exists

`DashboardController`'s docblock had said since Stage 5: v2's dashboard
has four stat cards (verified savings MTD/YTD, recommendation adoption
rate, active alerts) plus an onboarding checklist, deferred only because
"that page's OTHER three stat cards query VerificationRecord aggregates
this port hasn't built a view for yet." That's no longer true —
`Baseline`, `SavingsAssessment`, `VerificationRecord` (Stage 7) and
`RecommendationDecision` (Stage 6) all exist now. Read v2's actual
`src/app/(portal)/dashboard/page.tsx` in full rather than approximating
from memory: it's a 4-stat-card grid + `OnboardingChecklist` (4 steps —
upload data → generate assessment → add schedule → act on a
recommendation, permanently disappears once complete) + the existing
recommendations/alerts two-column layout, title "Savings Dashboard" (not
"Dashboard").

Ported both pieces exactly:

- **Stat cards** — `DashboardController` now computes `mtdSavingsCents`/
  `ytdSavingsCents` (`VerificationRecord::where('review_status',
  LiveApproved)->where('period_start', '>=', ...)->sum(...)`, scoped to
  the current month/year), and `adoptionRate` (accepted + partial
  decisions ÷ total, matching v2's exact "both count as followed, a
  partial adopter is still acting on the recommendation" comment) —
  null when zero decisions exist, never a misleading 0%.
- **Onboarding checklist** — three `exists()` checks
  (`MeterReading::whereHas('meteringPoint.site', ...)`,
  `SavingsAssessment::where('organization_id', ...)`,
  `ScheduleBlock::whereHas('schedule', ...)`) plus the existing
  decision count. v2 wraps its three equivalent checks in
  `unstable_cache` purely to avoid repeating an RLS `set_config` round
  trip on every dashboard load forever (see v2's
  `src/lib/onboarding-status.ts` docblock) — neither cost applies here
  (tenant scoping is a plain Eloquent global scope, not a per-request
  RLS transaction), so this port runs the three checks directly with no
  caching layer to build or maintain.
- Title changed from "Dashboard" to "Savings Dashboard", matching v2
  exactly.

This single change also delivers the review's "information hierarchy"
ask (live status → immediate actions → performance/savings → alerts)
without any separate reorg work — v2's own layout already embodies that
order; porting it faithfully was sufficient.

### ADR-85: Validation

8 new tests added to `DashboardControllerTest` (MTD excludes
non-approved and prior-month records; YTD sums across the year;
adoption rate counts accepted+partial as followed and excludes
rejected; adoption rate is `null` with zero decisions, never `0`; the
onboarding checklist reflects real per-check state; a brand-new
organization shows all-false). Full suite: 501/501 passing (1,689
assertions, up from 495/1,611 pre-change). `tsc --noEmit`, ESLint (two
files needed `--fix` for prettier-only deltas), and Pint all clean;
`vite build` succeeded.

Live smoke test against a freshly seeded dev database: signed in as
the seeded customer owner, confirmed the "Savings Dashboard" title, the
onboarding checklist rendering all 4 steps with correct CTAs and "0 of
4 done", all four stat cards showing correct empty-state values (€0.00
MTD/YTD with the "no cycles closed yet" sub-label, "—" adoption with
"No recommendations issued yet", 0 active alerts) — a fresh seeded org
correctly shows real zeros with context, not broken-looking blanks.
Confirmed mobile layout (375px): checklist items wrap correctly, stat
cards stack to a single column, nothing overflows.

---

## New Feature: Labour-Aware Shiftable Load Classification

### Objective

Customer discovery found that a significant share of technically-shiftable
load can only actually be shifted if a person changes when they work
(night shifts, overtime, operator availability). Recommending those
shifts as if they were free — the same as flipping on a compressor at a
cheaper hour — overstates savings and costs trust. This is genuinely new
product scope, not a v2 port: v2 has no such concept anywhere in its
source. Unlike every other ADR in this document, there's no "matches
v2" to verify against — the standard here is the brief this stage was
commissioned against, verified by re-reading it against the actual
domain model before writing code.

### ADR-86: `labour_classification` lives on `ScheduleBlock`, as a property, not a second load-taxonomy table

The brief's own core principle — "not another load category, a property
of a load" — maps directly onto this codebase's existing "shiftable
load" concept: `ScheduleBlock` (asset/line, start/end, estimated kWh),
already the exact unit `RunOptimizationAction` feeds to the
recommendation engine. A new `LabourClassification` string-backed enum
(`Unmanned` / `LabourBound`) was added as one more column on that
existing table, not a new domain or a parallel model — the same pattern
`estimated_kwh` itself already established (a nullable attribute that
gates eligibility, not a reason to fork the schema).

### ADR-87: The DB-level default is `LABOUR_BOUND`, not `UNMANNED` — every existing and future unclassified block starts conservative

The migration (`add_labour_classification_to_schedule_blocks_table`)
sets the column's default to `LABOUR_BOUND` at the schema level. This
means every schedule block that existed before this migration ran
retroactively becomes ineligible for future optimization runs until a
human explicitly reclassifies it as Unmanned — a deliberate behavior
change, not a migration hazard, per the brief's own instruction:
"prefer a conservative default that prevents misleading customer-facing
recommendations until the load has been explicitly classified."
`AddScheduleBlockAction::execute()`'s own PHP-level default parameter
matches the same value, so any caller that omits the argument (a future
API integration, a bulk importer) gets the identical conservative
behavior as a raw insert would — one default, expressed consistently at
both layers, not two independent decisions that could drift apart.

The portal's own form never actually relies on either default silently
— `StoreScheduleBlockRequest` requires the field explicitly, and the
`AddBlockForm` select always renders a real, visible selection
(pre-set to Labour Bound) rather than a blank/placeholder option, so a
customer marking something Unmanned is always a deliberate action, never
an accident of an unfilled dropdown.

### ADR-88: One centralized eligibility check, `ScheduleBlock::isEligibleForOptimization()` — replaces `RunOptimizationAction`'s old inline kWh-only filter, extensible for future constraints

`RunOptimizationAction` used to filter its candidate blocks with one
inline closure (`estimated_kwh !== null`). That's now
`ScheduleBlock::isEligibleForOptimization()` — kWh present AND
`labour_classification === Unmanned` — called from the one place that
used to have the old check, with the recommendation engine itself
(`RecommendationEngine`, `ProvisionalWindowShiftStrategy`) completely
untouched: it never learns a labour-bound block existed, matching the
brief's explicit "the optimization algorithms themselves should remain
unchanged, only the eligible input set changes." Per the brief's own
extensibility ask ("additional operational constraints... without major
refactoring"), a future constraint (a maintenance window, a regulatory
restriction) is one more condition inside that single method, not a
second filter to keep in sync at every call site.

### ADR-89: Savings/verification figures (MTD/YTD, ROI) are NOT filtered by labour classification — they have no per-block breakdown to filter, and building one would itself be a new optimization engine

Investigated before writing any code: `SavingsAssessment`/
`VerificationRecord` (the numbers behind the portal dashboard's
MTD/YTD stat cards and every ROI/invoice figure) are computed by
`ProvisionalHeuristicStrategy`/`ProvisionalPeriodComparisonStrategy`
directly from aggregate meter readings against day-ahead prices — a
whole-site heuristic ("shift 30% of every hour's load into that day's
3 cheapest hours"), with no concept of individual `ScheduleBlock`s at
all, and no v2 precedent that decomposes it into one. There is nothing
architecturally correct to filter by labour classification at that
layer without inventing a new per-load decomposition of a heuristic
that was never per-load to begin with — which the brief explicitly
rules out ("we are not creating a new optimization engine"). The
labour filter applies fully and correctly to everything that actually
is derived from individual blocks: `Recommendation` rows (so
`projected_savings_cents`, the dashboard's "Recent recommendations"
and "Recommendations followed" adoption rate, and every report table
built from them) can now only ever exist for Unmanned blocks, since
`RunOptimizationAction` never sees a labour-bound one. No carbon data
exists anywhere in this domain either (checked directly, not assumed)
— the brief's carbon-reduction bullet is inapplicable, not
unimplemented.

### ADR-90: Frontend — a required select on the add-block form, a read-only badge everywhere blocks are already displayed, no new pages

`Portal/Schedule.tsx`'s `AddBlockForm` gained one more field (Labour
classification, pre-set to Labour Bound); its blocks table and
`Portal/ScheduleHistory.tsx`'s read-only reconstruction view both gained
a "Labour" column rendering a small `LabourClassificationBadge` (`navy`
for Unmanned, `mid` for Labour Bound — reusing this app's existing Badge
variants and their established semantics rather than inventing a third
color). The "why can't I run optimization" hint text and the prop
driving it (`hasBlocksWithEstimatedKwh` → `hasEligibleBlocks`) now
reflect the real, full eligibility condition rather than just its kWh
half. No new page, no new navigation entry — labour classification is a
column on data already shown on pages that already exist, per the
brief's own instruction to integrate with existing architecture rather
than build parallel UI.

### ADR-91: Validation

10 new/updated tests: `ScheduleBlockEligibilityTest` (all 4 kWh ×
classification combinations), `RunOptimizationActionTest` (a
labour-bound block with a valid kWh sitting in the most expensive price
hour of the day produces zero recommendations — proving the filter
actually excludes it, not just that an all-labour-bound schedule throws
early), `AddScheduleBlockActionTest` (default vs. explicit
classification), `ScheduleControllerTest` (required/enum validation,
persisted value, `hasEligibleBlocks` reflecting real state). Full suite:
512/512 passing (1,715 assertions, up from 501/1,689). `tsc --noEmit`,
ESLint, and Pint all clean; `vite build` succeeded.

Live smoke test against a freshly migrated dev database: added one
Unmanned block (Compressor 1) and one Labour Bound block (Welding
Station A) through the real form, confirmed both badges render
correctly on both the Schedule page and Schedule History, confirmed the
"Run optimization" button only appeared once the Unmanned block existed,
and confirmed triggering it produced "No price data covers your
schedule's dates yet" rather than "No schedule blocks are eligible for
optimization yet" — proving the Unmanned block was correctly accepted
as a candidate and the pipeline proceeded past the eligibility check to
the next real validation step, live, not just in the test suite.

## Post-Stage-12: Labour Classification Platform-Wide Consistency Audit

### Objective

ADR-86 through ADR-91 built Labour Classification as a domain concept
and a single centralized eligibility gate
(`ScheduleBlock::isEligibleForOptimization()`), but building the gate
doesn't by itself guarantee every downstream surface — marketing copy,
docs, reports, the recommendation engine, admin views — actually
reflects the new rule that only Unmanned blocks are eligible for
customer-facing optimization. This audit traced the concept's full data
flow end to end (backend Actions/DTOs/models, the reports view-model
and its PDF/web consumers, the dashboard controller, marketing pages,
customer and internal docs) to find any remaining claim, calculation,
or piece of copy that still assumes every block is recommendable.

### ADR-92: Confirmed correct-by-construction — every `Recommendation`-derived surface, with nothing to fix

Because `RunOptimizationAction` is the single upstream point where
`Recommendation` rows get created (ADR-88), every downstream consumer
of `Recommendation` data — `SavingsReportViewModelBuilder`'s
`recommended`/`recommendations`/`callouts` fields, `ReportDetail.tsx`'s
"Recommended schedule" and "Recommendation Summary" sections, the
`NEW_OPPORTUNITY` alert, and `DashboardController`'s recent-
recommendations panel — is already Unmanned-only, transitively, with no
per-surface filter needed. `SavingsReportViewModelBuilder`'s `current`
schedule bars intentionally include every block regardless of
classification: that section renders the customer's real, actual
schedule, not an optimization claim, so showing Labour Bound blocks
there is correct, not a gap. `ApplyDecisionsToNewScheduleVersionAction`
copies every block forward into the new schedule version (correct — it
reconstructs the whole schedule) but only overrides start/end times
where a `RecommendationDecision` exists, which by construction can only
exist for a block that generated a Recommendation in the first place.
The MTD/YTD/ROI figures on `SavingsAssessment`/`VerificationRecord`
(ADR-89) remain unfiltered by design — they're computed from aggregate
meter readings against day-ahead prices, with no per-block breakdown to
filter in the first place. No REST/API layer exists in this app (all
data flows through Inertia responses), and no AI/ML classification
logic exists anywhere (`labour_classification` is a plain manual select
field) — both named as audit areas in the source brief, both confirmed
not applicable to this codebase's actual architecture rather than
built to satisfy the checklist.

### ADR-93: Four marketing/docs copy sites predated the feature and overclaimed eligibility in kWh-only terms — corrected without naming the internal enum on customer-facing pages

`Methodology.tsx`, `Docs.tsx` (customer-facing), and `Beta.tsx` each had
prose written before Labour Classification existed, describing
recommendation eligibility as gated only by having an estimated kWh
figure — now factually incomplete, since an Unmanned classification is
equally required:

- `Methodology.tsx` STAGES[1].body: "each flexible production block" →
  "each block that can actually move — production that runs unmanned,
  not work tied to when someone's on shift".
- `Docs.tsx` PAGES "Schedule" body: "That kWh figure is what makes a
  block eligible for optimization at all" → now names both the kWh
  figure and the unmanned/staffed-shift distinction as joint
  requirements.
- `Docs.tsx` GLOSSARY "Recommendation" body: "a specific, flexible
  production block" → "a specific production block — one that runs
  unmanned, so moving it doesn't mean changing when anyone works".
- `Beta.tsx` PORTAL_SECTIONS "Schedule" body: same kWh-only-eligibility
  claim, corrected the same way.

Per the audit brief's own instruction, copy uses plain operational
language ("runs unmanned", "needs someone on shift") rather than naming
the `LabourClassification` enum's internal case names on public pages.
A grep sweep across `app/`, `resources/js/`, and `resources/views/` for
the brief's full term list ("shiftable", "flexible", "optimisable",
"recommendable", "eligible", "savings", "opportunity") turned up no
further overclaiming instances: `Home.tsx`, `FaqAccordion.tsx`,
`HowItWorks.tsx`, `Pricing.tsx`, `Trust.tsx`, and `Admin/Docs.tsx` were
each read in full and confirmed either unrelated (invoice
traceability, tenant isolation, audit-log completeness, pilot ICP
process-flexibility scoring — a company-level fit score, not a
per-block eligibility claim) or already accurate.

### ADR-94: No Admin UI or new documentation content added — visibility gaps that predate this feature, not introduced by it

Admin has zero `ScheduleBlock` visibility anywhere (`grep` across
`app/Http/Controllers/Admin` and `resources/js/Pages/Admin` returned no
hits) — a pre-existing scope boundary from Stage 9, not a regression
from Labour Classification, and building one would be new functionality
the audit brief explicitly put out of scope ("your task is not to build
new functionality"). Similarly, `Admin/Docs.tsx`'s GLOSSARY has no
entry for Recommendation/ScheduleBlock/Optimization at all — a
pre-existing documentation gap, left alone for the same reason.

### ADR-95: Validation

No behavioral code changed — every fix in ADR-93 is customer-facing
copy only, since the eligibility logic itself (ADR-88) was already
correct and already covered by `ScheduleBlockEligibilityTest` and
`RunOptimizationActionTest`'s existing regression coverage (a
labour-bound block in the cheapest-vs-most-expensive-hour scenario).
Full suite: 512/512 passing, unchanged from ADR-91 (no test file
touched). `tsc --noEmit`, ESLint (`--fix` applied for two
prettier-quote-style violations introduced by the edits), and Pint all
clean; `vite build` succeeded. Live smoke test at `localhost:8123`
against `/methodology`, `/docs`, and `/beta`: all three pages render
the corrected copy exactly as written, confirmed via rendered page
text, not just source diffs.

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

### Execution Log

Preceded by a platform-wide verification pass confirming Stages 1–12 and
every out-of-band feature request across the session were genuinely
complete, not just marked so: migrations clean, 512/512 tests passing,
every stage's routes present and wired, nav/security/dashboard-stat-card
restorations all live-verified, scheduler and external integrations
confirmed wired, and a live browser walkthrough of both the customer and
admin realms end to end. No gaps found — see the Stage Status table
above.

Every ADR from Stage 1 through ADR-95 was then read and classified per
this stage's own guiding principles (Architecture Improvement / Product
Behaviour Preservation / Legacy Product Debt / Security & Data
Integrity) before any code changed. The large majority are Product
Behaviour Preservation — deliberate migration-parity decisions, several
already self-documenting their own known trade-offs (ADR-8's deferred
RLS, ADR-12's Octane caveat, ADR-25's membership-only authorization,
ADR-42/48's intentionally-preserved v2 dead code) — and were left
untouched. The items below are what the review surfaced as actionable.

### ADR-96: `PreventBackHistoryCache` (ADR-14) extended to the portal and admin route groups — its own docblock already called for this, Stage 5 never did it

The middleware's own comment at introduction said the future portal
shell should adopt it too; that step was simply never taken when Stage 5
and Stage 9 later built `routes/portal.php` and
`routes/admin-protected.php`. Concretely: every actually-sensitive
authenticated page — schedules, billing, reports, org data, invoices,
audit logs — was serving `Cache-Control` headers that left it
bfcache-restorable after logout, while only the Account Security
settings pages (the middleware's original, narrower scope) were
protected. Added to both route groups' middleware stacks; the fix is a
response-header change only, no redirect, permission, or data-shape
difference, so no business behavior changes. New tests added to
`BackHistoryCacheHeadersTest.php` covering both realms; existing
settings/sign-in assertions untouched and still passing.

### ADR-97: Baseline security headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`) added globally — CSP deliberately deferred, not attempted alongside them

No security-header middleware existed anywhere in the app; a security
review scope item named "headers" and "CSP compatibility" explicitly.
`App\Http\Middleware\SecureHeaders` adds the three headers with no
third-party-integration risk, registered in `bootstrap/app.php`'s global
`web` group (unlike `PreventBackHistoryCache`, these don't affect
cacheability, so there's no reason to scope them to authenticated
routes only). Content-Security-Policy was considered and explicitly
**not** added in the same pass: this app loads PostHog, Sentry, and (in
local dev) Vite's HMR client from dynamic/third-party origins, and a
CSP misconfigured in any one direction fails silently (a blocked
request, not an error) rather than loudly — it needs its own dedicated
pass with live browser verification against every integration, not a
same-commit addition next to unrelated headers. Recorded here as a
known follow-up, not silently skipped.

### ADR-98: Static analysis (Larastan/PHPStan) added at level 5, existing findings baselined rather than force-fixed — one baselined finding is a documented Larastan false positive, not real dead code

No static-analysis tooling existed in `composer.json` — explicitly named
under this stage's Developer Experience scope. `larastan/larastan`
installed, `phpstan.neon` configured at level 5 across `app/`,
`database/`, `routes/`. A first run against this already-shipped,
fully-tested codebase surfaced 200 findings — captured via
`--generate-baseline` into `phpstan-baseline.neon` rather than fixed
inline: individually triaging 200 findings for observable-behavior risk
was out of this pass's scope, and PHPStan's own findings are not
infallible on a codebase this dynamic. Concretely confirmed by manual
inspection of one flagged item before deciding on the baseline approach:
`AuditLogService::record()`'s `$last?->hash ?? self::genesisHash()` was
flagged `nullsafe.neverNull` (Larastan inferring `$last` can never be
null after a `lockForUpdate()`-chained `->first()`), but `$last`
genuinely **is** null on the very first audit log write to a fresh
database — exactly the case `genesisHash()` exists to handle. Applying
PHPStan's own suggestion there would have crashed the first audit write
in any newly migrated environment. Left in the baseline, untouched. The
baseline means all *new* code is held to level 5 from this point
forward; the 200 pre-existing findings remain visible in
`phpstan-baseline.neon` for deliberate, individually-reviewed cleanup
later, not silently hidden. `composer analyse` added as the run command.

### ADR-99: `laravel/breeze` removed from `composer.json` — its scaffolding was replaced back in Stage 1, but the dependency itself, and its auto-discovered service provider, were never removed

Found while investigating dev dependencies for the DX review: no code
anywhere references `Laravel\Breeze\*`, but `Laravel\Breeze\
BreezeServiceProvider` was still present in `bootstrap/cache/services.php`
and `packages.php` — meaning it booted on every single request for zero
functional benefit, purely because `composer remove` was never run after
its generated scaffolding was hand-replaced by this app's own dual-realm
auth system. `composer remove --dev laravel/breeze` confirmed clean via
`grep` first (no lingering references), then verified via a full
`php artisan test` pass and a fresh PHPStan run — both unchanged. Zero
observable behavior change; one fewer service provider on every boot.

### ADR-100: 27 of 28 pages missing a page-specific `<Head title>` now have one — explicitly pre-flagged as Stage 13 work by ADR-75 itself; one page deliberately left as-is

ADR-75 (Stage 12) found that no page anywhere in the app prior to that
stage ever set a page title, and explicitly named "Stage 13's
post-migration cleanup" as where retrofitting belonged. Added
`<Head title="...">` to all 16 remaining customer-facing pages (Auth,
Invite, Portal, Settings) and all 12 Admin pages, reusing each page's
existing on-screen heading text as the title (`PortalPageHeader`'s
`title` prop, or the Admin `<h1>` content) so no new copy was invented.
`Admin/Organizations/Show.tsx` uses the organization's own name
(`<Head title={organization.name} />`), matching the page's own dynamic
`<h1>`. **One deliberate exception**: `Auth/Login.tsx` was left
untouched — its own docblock already documents, with a cited reason,
that v2's sign-in page has no page-specific metadata export and that
this port matches that exactly ("No `<Head title>` override either...
matching that means NOT setting one here"). Overriding a
specifically-verified parity decision under a generic "add missing
titles" pass would have been exactly the kind of undisclosed product
change this stage's guiding principles rule out — a file-specific,
evidenced decision takes precedence over ADR-75's blanket framing.
Verified live in the browser across both realms post-build: Dashboard,
Schedule, Overview, Organizations, and the dynamic
`{organization.name}` case on Organizations/Show all render the correct
`<title>...· Hetkin</title>`, confirmed via rendered tab title, not just
source diffs. `tsc --noEmit`, ESLint, full suite (515/515), and
`vite build` all clean.

### ADR-101: Performance and Architecture Improvement candidates investigated, none executed — evidence-based decision, not a skipped step

**Performance**: audited the highest-traffic, most relation-heavy read
paths for N+1 queries and missing indexes — Admin's Organization 360-view
(`OrganizationController::show()`, 8 sections, each independently
`.with()`-eager-loaded where needed), `SavingsReportLoader` (the
report/PDF data loader, confirmed `recommendations` eager-loads
`scheduleBlock` before the view-model builder ever touches it), and the
three highest-volume tables (`audit_log_entries`,
`meter_readings`, `electricity_prices`). All three tables already carry
composite indexes matching their actual query patterns
(`[organization_id, created_at]`, `[metering_point_id, hour_start]`
unique, `[bidding_zone, hour_start, source]` unique) — foreign keys are
auto-indexed via `foreignUlid()->constrained()` throughout. No N+1
pattern or missing index found anywhere checked; this reflects care
already taken during the original migration stages, not a gap. No
changes made — there was nothing to fix.

**Architecture**: two candidates from the ADR classification (ADR-21's
duplicated route registration across `routes/portal.php`/
`routes/admin-protected.php`'s two mount points; ADR-29/32/37's
repeated "scope through parent, not directly" pattern across Metering/
Scheduling/Optimization) were investigated and deliberately **not**
executed. Route de-duplication would touch every route definition in
the app for a purely cosmetic reduction in repetition, with real risk of
silently renaming or misregistering a route if done carelessly — a
disproportionate blast radius for the benefit, given this stage's own
"no externally observable behaviour change" bar. The scope-through-parent
"pattern" is not duplicated imperative code to consolidate — it's the
same architectural *decision* (don't scope the child model directly)
independently documented in three domains' own model files; forcing a
shared abstraction over three one-line decisions would add indirection
without removing any actual duplication, contrary to this stage's own
instruction not to introduce unnecessary complexity. Both reviewed
deliberately, per the stage's framework; neither changed.

### ADR-102: Validation

Full suite: 515/515 passing (1,737 assertions, up from 512/1,715 at the
start of this stage — the two new `BackHistoryCacheHeadersTest` cases
plus one new `SecureHeadersTest` case). `tsc --noEmit`, ESLint, and Pint
all clean. PHPStan (new to this stage): 0 errors against the level-5
baseline. `vite build` succeeded. Live smoke test at `localhost:8123`
against both realms: customer login → Dashboard (correct tab title,
`no-store` header present) → Schedule (correct tab title); admin login →
Overview → Organizations → Organizations/Show (dynamic org-name title
confirmed). Stage Status table below updated to reflect completion.

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

### Portal Installation Behaviour
Hetkin consists of multiple application surfaces with different user roles.

The Progressive Web Application experience must remain isolated per
application surface.

Implement the installation flow so that:

- the Customer Portal is installable as its own standalone application
- the Admin Portal is installable as its own standalone application
- each portal has its own Web App Manifest, application identity, icons, start URL, and scope
- installation prompts are only shown while the user is inside the
corresponding portal
- installing the Customer Portal installs only the customer dashboard and customer experience
- installing the Admin Portal installs only the administrative interface
- navigating between portals must not trigger installation prompts for the other application
- authentication and routing remain isolated between installed applications
- each installed application launches directly into its respective portal
- each application maintains its own cache, offline shell, and update
lifecycle where appropriate

The public marketing website must not present itself as an installable
application.

The install experience should only be available after entering an authenticated
portal, ensuring users install the application they actually use rather than
the marketing website.

### Completion Criteria

Stage 14 is complete when:

- Hetkin is installable on supported browsers
- the Customer Portal installs as an independent PWA
- the Admin Portal installs as an independent PWA
- the marketing website is not installable
- update detection works correctly
- versioning is automated
- deployment integration is complete
- offline experience is implemented
- security guarantees remain unchanged
- documentation is complete

### Execution Log

Planned via a design-review pass (a Plan agent pressure-tested the initial
draft) before any code changed, given the size and cross-cutting nature
of the feature (new PHP routes/middleware/config, a new build pipeline
for the Service Worker, versioning spanning both PHP and JS, new tests,
documentation). That review caught three real bugs before they shipped —
see ADR-105 — and manual browser testing after implementation caught a
fourth, more subtle one not visible from source reading alone — see
ADR-107. See [PWA.md](PWA.md) for the practical "how it works, how to
touch it" reference; this log is the decision history.

### ADR-103: One shared surface-detection helper, not host-checking logic duplicated across three call sites

`App\Support\PwaSurface` determines which of the three app surfaces
(marketing/portal/admin) a request belongs to, using the exact same
"check the real subdomain host, else check the `/dashboard` or `/admin`
path prefix" logic `bootstrap/app.php`'s `redirectGuestsTo`/
`redirectUsersTo` closures already used, and the same unconstrained-route
reasoning `routes/settings.php` already established (both cited as
precedent in the class's own docblock — this is not a novel pattern).
`App\Http\Middleware\HandleInertiaRequests` was refactored to delegate
its existing `isDashboardHost`/`isAdminHost` booleans to this helper
(identical output values, just de-duplicated) rather than left as a
fourth independent copy. Reused by the manifest/Service-Worker controller
and the `app.blade.php` install-eligibility gate — host/path detection
logic exists in exactly one place, not three or four.

### ADR-104: Manifests and Service Workers are Laravel routes, not static `public/` files

`routes/pwa.php` registers `GET /manifest-portal.webmanifest`,
`/manifest-admin.webmanifest`, `/sw-portal.js`, `/sw-admin.js` with no
`domain()` constraint, so one route answers correctly whichever way a
visitor reaches it (real subdomain or main-domain path prefix) —
`App\Http\Controllers\PwaController` computes the correct
`start_url`/`scope`/`id` per request via `PwaSurface`. The Service Worker
routes specifically need to be server-routed rather than plain static
files so `Cache-Control: no-cache` can be set explicitly on them — left
to a hosting platform's static-file defaults, a long `max-age` could
delay the browser's ~24h update byte-comparison check well past what
`usePwaUpdate()`'s polling assumes.

### ADR-105: Three real bugs the design-review pass caught before implementation, and why the fixes are what they are

- **A second Vite config for the Service Worker would have deleted
  `public/`.** The original draft planned to bundle `sw-core.ts` via a
  separate `vite.config.sw.js` targeting `public/` directly — Vite's
  default `emptyOutDir: true` would wipe the *entire* `public/`
  directory, including `index.php`, on every build. Fixed by dropping the
  bundler for the Service Worker entirely: `resources/js/service-worker/
  sw-core.ts` and the two tiny per-surface entry files
  (`sw-portal.ts`/`sw-admin.ts`) compile via plain `tsc` (two dedicated
  configs — see ADR-106) into classic, non-module scripts, consistent
  with this codebase's established hand-rolled-over-dependency posture.
- **A version string baked in at JS build time vs. read at PHP request
  time can silently disagree in a real deploy.** The original draft's
  versioning scheme computed `git rev-parse` independently on both the
  Vite build side and the PHP config side; the review flagged that a
  production runtime container commonly ships built assets without
  `.git` (or even a `git` binary), meaning the two sides could drift
  apart with no error, just silent disagreement. Fixed by dropping the
  mechanism that needed synchronizing at all: `config('app.version')` is
  purely informational (a "which build" display value, `env('APP_VERSION')`
  with a git fallback restricted to `local` only — see `config/app.php`'s
  docblock), and PWA update *detection* uses the browser's native Service
  Worker byte-diffing instead of any version-string comparison, which has
  no such failure mode and needed nothing baked in at build time to begin
  with.
- **Hiding the React install button doesn't suppress the browser's own
  native install icon.** Chrome/Edge's install affordance is driven
  purely by "a manifest is linked and a Service Worker controls this
  page" — true regardless of whether `InstallPwaButton.tsx` renders
  anything. Fixed by moving the actual gate server-side into
  `app.blade.php` (§ install eligibility below) rather than treating the
  React component as the security boundary.

### ADR-106: The Service Worker is hand-rolled, classic (non-module) scripts — two more real compiler bugs found and fixed during implementation, beyond what the design review caught

Building on ADR-105's "no bundler" decision: `resources/js/service-worker/
cache-policy.ts` (the pure, allow-list `shouldCache()` function) and
`sw-core.ts` (install/activate/fetch/push handlers) compile via two
dedicated configs, `tsconfig.sw-portal.json` / `tsconfig.sw-admin.json`
(`module: "none"`), into plain global-scope `.js` under `public/`. Two
problems only surfaced once actually compiling and inspecting the output,
not from source review alone:

- **`module: "none"` still emits a broken CommonJS wrapper for a file
  that uses `export`.** `cache-policy.ts` originally exported
  `shouldCache` (for a clean Node import in its own test); compiling it
  produced `Object.defineProperty(exports, "__esModule", ...)` —
  referencing a global `exports` object that doesn't exist in a Service
  Worker, which would throw `ReferenceError` as the very first thing the
  worker does. Fixed by making it a genuinely plain global function, no
  `export` at all; `cache-policy.test.ts` loads the **compiled** output
  via `node:vm` instead (see ADR-108), which also means the test
  exercises the exact artifact that ships, not a parallel hand-written
  copy that could drift from it.
- **A `const`/`let` "forward declaration" for a value defined in another
  file genuinely conflicts, even though the two never coexist at
  runtime.** `sw-core.ts` needs `SURFACE` (set by whichever of
  `sw-portal.ts`/`sw-admin.ts` `importScripts()`-ed it in); an initial
  `declare const SURFACE` collided with the entry file's real
  declaration the moment both were compiled together to type-check
  cross-file references. Fixed two ways: switched to `declare var
  SURFACE` (the one declaration form JS — and TypeScript, matching it —
  allows to redeclare across files sharing a global scope), and split
  into two separate tsconfigs rather than one compiling both entry points
  together, since `sw-portal.ts` and `sw-admin.ts` each declare their own
  real `var SURFACE` and were never meant to be type-checked as one
  combined global scope in the first place.

`resources/js/service-worker/service-worker.d.ts` hand-declares the
handful of Service-Worker-specific types (`ServiceWorkerGlobalScope`,
`FetchEvent`, `ExtendableEvent`, `Clients`) that TypeScript's built-in
`webworker` lib doesn't ship — a long-standing gap in TypeScript's
standard library, not a local oversight, confirmed by the exact
`self`/`caches`/`skipWaiting` "not defined" errors produced without it.

### ADR-107: A fourth bug, found only by testing the actual login flow live in the browser — the install gate worked at the HTTP layer but not across an in-app SPA transition

`app.blade.php`'s server-side gate (ADR-105's fix for the native-icon
problem) was verified correct via `curl` and via `tests/Feature/
PwaManifestTest.php` — both confirmed the manifest link and `window.__PWA__`
appear exactly when authenticated on the matching surface. Manually
clicking through an actual login in the Browser pane, on the
`admin.localhost` subdomain, showed the manifest link and Service Worker
registration **still absent after a successful login** — the automated
Pest test had exercised `route('admin.overview')`, which resolves to the
main-domain path-prefix mode, never the subdomain path this specific
manual check used, so the gap wasn't caught by the existing coverage.

Root cause: Inertia is an SPA. After the very first full page load, it
never re-renders `app.blade.php`'s `<head>` again — a login form submit
is handled as a client-side XHR navigation, not a full browser reload, so
the manifest link computed once on the (unauthenticated) sign-in page
never updated. The mirror-image bug existed on sign-out too: a manifest
added pre-logout stayed present after an in-app sign-out, until reload.
Neither direction is a security hole (the server-rendered HTML for any
*fresh* request was always correct — confirmed again after the fix), but
both meant install eligibility didn't reliably track the visitor's actual
session state during ordinary in-app use.

Fixed by adding `resources/js/lib/pwa.ts`'s `syncPwaForPage()`, called
from `app.tsx` on every Inertia `navigate` event, not just once at boot —
it re-derives the same surface/auth check from the page props Inertia
already shares on every navigation and adds/removes the manifest
`<link>` + registers the Service Worker imperatively as needed. No
independent host-detection logic: it reads the same
`subdomain.isDashboardHost`/`isAdminHost` booleans `PwaSurface` already
computed server-side, applying only the trivial scope mapping to them.
Re-verified live in the browser after the fix, both directions (in-app
login → manifest/registration appear; in-app sign-out → manifest
disappears), on the admin subdomain specifically since that's what
exposed the gap.

### ADR-108: Test coverage split by what each tool can actually verify

`tests/Feature/PwaManifestTest.php` / `ServiceWorkerTest.php` (Pest, same
house style as the rest of this suite) cover manifest JSON content across
all four host/path combinations, Service Worker response headers, and —
the actual regression test for ADR-105's native-icon fix — that the
manifest link and `window.__PWA__` are present only for an authenticated
visitor on the matching surface, absent for a signed-out visitor and for
the marketing site. `resources/js/service-worker/cache-policy.test.ts`
unit-tests the `shouldCache()` allow-list — the highest-risk logic for
"protected content is never cached" — via Node's native TypeScript
support (`npm run test:js`, zero new test-framework dependency; this repo
is otherwise Pest-only), loading the compiled output via `node:vm` per
ADR-106. This closes a gap the design review explicitly flagged: zero
existing JS test infrastructure in this repo, which would otherwise have
left the riskiest logic with no automated coverage at all. What neither
tool can verify — the actual install flow, offline fallback rendering,
the update banner, and ADR-107's SPA-navigation sync — was verified live
in the Browser pane instead, not just asserted from source reading.

### ADR-109: Validation

Full suite: 527/527 passing (1,775 assertions, up from 515/1,737 before
this stage). `tsc --noEmit`, ESLint, and Pint all clean. PHPStan: 0 errors
against the Stage 13 baseline — no new findings. `npm run test:js`: 6/6.
`npm run build` (now also running the two Service Worker `tsc` compiles)
succeeds without touching anything else in `public/`. Live-verified in
the Browser pane on both `dashboard.localhost`/`admin.localhost`
(subdomain mode): manifest content and icons correct for both portals;
Cache Storage after normal browsing contains only `/build/assets/*`,
`/icons/*`, and the manifest — zero page/XHR/API responses, the actual
proof behind "protected content is never cached"; the two portals'
Service Worker registrations and caches are fully independent; the
marketing site never links a manifest or registers a worker regardless
of auth state; and, after ADR-107's fix, install eligibility tracks an
in-app login/logout correctly without requiring a hard reload.

---

## Post-Stage-14: Customer portal had no sign-out control, and the admin console's was silently broken

### ADR-110: A user-raised question ("how does a customer protect their account if they leave their PC unattended?") surfaced that `PortalLayout.tsx` had no sign-out link at all — and testing the fix found the admin console's had never actually worked

Investigating the question directly (grepping `PortalLayout.tsx` for
"sign out"/"logout") confirmed there was no visible way for a customer to
end their own session — the `POST /logout` route existed and worked, it
just wasn't linked from anywhere in the portal chrome. The only existing
protections were `SESSION_LIFETIME=120` (a 2-hour idle timeout) and
Settings → Security's active-session revocation, neither of which covers
someone stepping away from a still-open, still-authenticated browser.

Added a sign-out control to `PortalLayout.tsx`'s sidebar footer (name +
"Sign out", visible whenever `auth.user` is present), mirroring
`AdminLayout.tsx`'s existing nav-row pattern. Live-testing the new
button in the browser found it didn't actually work — clicking did
nothing, no navigation, no error surfaced to the user. The browser
console showed why: `validateDOMNesting(...): <button> cannot appear as
a descendant of <button>`. The pattern being mirrored —
`<Link method="post" as="button"><Button>Sign out</Button></Link>` —
renders Inertia's `Link` itself as a `<button>` and then nests a second,
real `<button>` (from the `Button` component) inside it, which is
invalid HTML and left the click not reliably reaching the outer
element's handler.

Checking `AdminLayout.tsx` found the exact same nested-button markup on
its own "Sign out" control — a pre-existing bug, not something
introduced by this change, that had evidently gone unnoticed. Fixed both
by applying `buttonVariants({ variant: 'ghost', size: 'sm' })` (Button's
own exported class-name function) directly to the `Link`'s `className`
instead of nesting a second interactive element inside it — one real
`<button>`, not two. Re-verified live in the browser: clicking "Sign
out" on the customer portal now correctly signs out and lands on the
customer sign-in page; the admin console's "Sign out" — broken before
this fix, confirmed via the same nested-button check — now correctly
signs out and lands on the admin sign-in page too.

Full suite (527/527), Pint, PHPStan, and `tsc --noEmit` all stayed clean.

### ADR-111: Sign-out worked, but the user reported pressing browser "back" afterward still showed the old authenticated page — traced to a THIRD, previously-unknown history-restoration mechanism, distinct from both ADR-14 and ADR-110

Reproduced exactly as described: sign in, navigate to `/schedule`, sign
out (ADR-110's now-working button), press browser back — the stale,
pre-logout Schedule page content reappeared instead of the sign-in page.

The investigation ruled out the two mechanisms already understood and
defended against:

- **`PreventBackHistoryCache` (ADR-14)'s HTTP headers were not the
  problem.** A direct `curl` check confirmed `/schedule` correctly sent
  `Cache-Control: no-store, no-cache, must-revalidate, private` — exactly
  as that middleware sets it. (An earlier `fetch()`-based check from
  inside the browser tool had suggested otherwise; that reading turned
  out to be unreliable — the curl check, closest to what actually goes
  over the wire, was the one to trust.)
- **A fresh, unauthenticated server request to `/schedule` correctly
  redirected to the sign-in page** (`authUser: null` in the response's
  Inertia page data) — so the session itself genuinely was destroyed.
  Whatever was restoring the old page was happening entirely client-side,
  without a network request at all.

Reading `@inertiajs/core`'s router source directly found the real cause:
Inertia keeps its **own** client-side history-state cache, in
`window.history.state`, restored on `popstate` (`Router.
handlePopstateEvent`) — completely independent of the browser's HTTP
cache or bfcache, and therefore untouched by any `Cache-Control` header.
On back-navigation, Inertia decrypts/reads the previously-visited page's
stored props directly from that history entry and re-renders it
instantly, with no server round-trip at all — which is exactly the
"instant back-navigation" behavior Inertia is designed to provide, and
exactly why neither ADR-14's headers nor ADR-110's now-working sign-out
button could have caught this: there's no request for a middleware to
intercept.

Confirmed the mechanism precisely before fixing it: manually dispatching
a synthetic `pageshow` event with `persisted: true` (the same signal
`app.tsx`'s existing bfcache-fallback listener watches for) correctly
triggered a real reload and redirect — proving that listener's own logic
was sound, but that a genuine back-button press wasn't reliably producing
that signal for this specific restoration path, since it isn't a bfcache
restoration in the first place.

Fixed using Inertia's own purpose-built mechanism for exactly this
problem, not another custom listener:

- `Inertia\EncryptHistoryMiddleware` added to all three authenticated
  route groups (`routes/portal.php`, `routes/admin-protected.php`,
  `routes/settings.php` — the same three `PreventBackHistoryCache`
  already covers) — encrypts each response's history-state payload per
  session.
- `Inertia::clearHistory()` called in both `AuthenticatedSessionController
  ::destroy()` methods (customer and admin), right before the redirect —
  flags the very next Inertia response (the sign-in page) to invalidate
  the encryption key. Any earlier, still-cached history entry from that
  session becomes permanently undecryptable; Inertia's own router falls
  through to `onMissingHistoryItem()` (a real server request) rather than
  restoring it, which correctly hits the dead session and redirects to
  sign-in.

This is a distinct, complementary mechanism to ADR-14's response
headers, not a duplicate of it — one governs the browser's HTTP/bfcache
layer, the other governs Inertia's own separate in-memory history cache.
Both are needed; neither alone would have closed this gap.

`tests/Feature/HistoryEncryptionTest.php` (new) asserts `encryptHistory:
true` appears on portal/admin/settings responses and `clearHistory: true`
appears on the response immediately following each logout — reading the
page object directly out of the initial HTML's `data-page` attribute
rather than sending `X-Inertia: true` (a version-less Inertia XHR request
409s, and matching the real asset version just to read two booleans
would be more machinery than the assertion is worth). Re-verified live in
the browser, repeating the exact reported flow: sign in → navigate →
sign out → press back now correctly shows the sign-in page, no flash of
stale content. Full suite (532/532), Pint, and PHPStan all stayed clean.

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
