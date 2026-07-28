<?php

use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// No RefreshDatabase here deliberately: RefreshDatabase wraps every test in
// its own outer DB transaction for fast rollback-based isolation, which
// makes DB::transactionLevel() === 0 unreachable from a 'Feature' test —
// exactly the condition AuditLogServiceGuardTest needs to exercise
// (App\Domain\Audit\Services\AuditLogService::record() refusing to run
// outside an active transaction). Still boots the full app (TestCase), just
// without the ambient transaction.
pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Satisfies Password::defaults() (AppServiceProvider): 12+ chars, upper +
 * lower case, a number, a symbol. Centralized here rather than as a
 * per-file constant so multiple test files can share it without a
 * "cannot redeclare" collision (Pest loads every test file into the same
 * process).
 */
function strong_password(): string
{
    return 'Str0ng!Passw0rd#2026';
}

/**
 * Signs in a fresh user AND confirms their password (session's
 * `auth.password_confirmed_at` set) — the precondition every
 * `password.confirm`-gated settings route needs. Shared here so
 * tests/Feature/Settings/{ChangePassword,SessionManagement}Test.php don't
 * each declare their own colliding copy.
 *
 * Explicitly carries the session cookie from the confirm-password response
 * into every later call this test makes, via withCookie() — Laravel's
 * TestCase does NOT do this automatically (confirmed by reading
 * Illuminate\Foundation\Testing\Concerns\MakesHttpRequests: there is no
 * cookie-jar that captures a response's Set-Cookie for reuse on the next
 * call). Without this, each separate $this->get()/post()/etc call in a
 * test is a "cookieless" request from Illuminate\Session\Middleware\
 * StartSession's point of view, which regenerates a fresh random session
 * ID for that call (Illuminate\Session\Store::setId() falls back to
 * generateSessionId() for any id that didn't come from a real cookie).
 * Session DATA still often appears to "persist" across such calls (Store::
 * loadSession() merges via array_replace() rather than overwriting, so
 * attributes set on a cached driver instance earlier in the test survive a
 * later call's empty read-by-new-id) — which is exactly what made this so
 * confusing to track down: auth.password_confirmed_at kept working while
 * the actual session ID silently diverged from what's persisted in the
 * `sessions` table underneath it. A real browser never hits this: it
 * always sends its one real cookie back, so `$request->session()->getId()`
 * is server-side ground truth there. This helper makes tests behave the
 * same way, rather than working around the symptom in every call site.
 */
function confirmedPasswordUser(): User
{
    $user = User::factory()->create();
    $response = test()->actingAs($user, 'web')->post('/confirm-password', ['password' => 'password']);

    $cookieName = config('session.cookie');
    $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === $cookieName);
    if ($cookie) {
        test()->withCookie($cookieName, $cookie->getValue());
    }

    return $user;
}

/**
 * Convenience for reading the session ID a user's active session actually
 * persisted as — equivalent to what confirmedPasswordUser()'s cookie
 * propagation now keeps in sync with $request->session()->getId(), but
 * still useful wherever a test wants the DB's own view directly.
 */
function currentSessionIdFor(string $userId): string
{
    return DB::table('sessions')
        ->where('user_id', $userId)
        ->value('id');
}

/**
 * A signed-in customer with a real organization membership — the
 * precondition every portal route needs (App\Http\Middleware\
 * ResolveOrganizationContext redirects to sign-in without one, matching
 * v2's requirePortalContext()). Shared here so Portal controller/policy
 * tests don't each hand-roll the same three-row setup.
 *
 * @return array{user: User, organization: Organization}
 */
function portalUser(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    Member::factory()->for($user)->for($organization, 'organization')->create();

    return ['user' => $user, 'organization' => $organization];
}
