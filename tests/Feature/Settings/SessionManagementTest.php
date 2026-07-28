<?php

use App\Domain\Auth\Actions\RevokeSessionAction;
use App\Domain\Auth\Models\SessionRecord;
use App\Http\Controllers\Settings\SessionController;
use App\Models\User;
use App\Notifications\LoggedOutAllDevices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * The test suite's default SESSION_DRIVER is 'array' (phpunit.xml, for
 * speed) — this file needs the real 'sessions' table populated, since
 * that's exactly what it's testing.
 *
 * A structural note on several tests below: Laravel's HTTP test client
 * (Illuminate\Foundation\Testing\Concerns\MakesHttpRequests) does not carry
 * cookies between separate $this->get()/post()/delete() calls the way a
 * real browser would — confirmed by extensive direct investigation while
 * building this file (session ID persists correctly *within* one real
 * request/response cycle, and session *data* often appears to survive
 * across calls because Illuminate\Session\Store::loadSession() merges via
 * array_replace() onto a driver instance Illuminate\Support\Manager caches
 * per test, but the session *ID* itself does not track a cookie across
 * separate calls without deliberately propagating one). Where a test needs
 * to know its own "current session ID" reliably, it either uses a single
 * real request (GET /settings/security isn't behind password.confirm, so
 * this works directly) or invokes the controller directly with a session
 * object it fully controls — see the "cannot revoke own session" test.
 */
beforeEach(function () {
    config(['session.driver' => 'database']);
});

function insertSessionRow(string $id, string $userId, string $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0 Safari/537.36'): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => '203.0.113.5',
        'user_agent' => $userAgent,
        'payload' => base64_encode('{}'),
        'last_activity' => time(),
    ]);
}

test('the security page lists the signed-in user\'s session', function () {
    // A session row only exists in the `sessions` table once a request that
    // creates one has fully completed (Illuminate\Session\Middleware\
    // StartSession saves *after* the controller runs, not before) — so a
    // literal first-ever request has nothing to list yet. confirmedPasswordUser()
    // already made one real request (POST /confirm-password), which is what
    // this asserts shows up here. The is_current_device *flagging logic*
    // itself is verified directly and unambiguously in
    // tests/Feature/Domain/Auth/ActiveSessionFinderTest.php — seeAlso there
    // for why HTTP-level assertions on which specific row is "current"
    // aren't reliable within this test client (see this file's header
    // comment).
    $user = confirmedPasswordUser();

    $response = $this->get(route('settings.security'));

    $response->assertInertia(fn ($page) => $page
        ->component('Settings/Security')
        ->has('sessions', 1)
        ->where('sessions.0.browser', fn ($browser) => is_string($browser)));
});

test('a user can revoke one other session', function () {
    $user = confirmedPasswordUser();
    insertSessionRow('other-device', $user->id);

    $response = $this->delete(route('settings.security.sessions.destroy', 'other-device'));

    $response->assertRedirect();
    expect(DB::table('sessions')->where('id', 'other-device')->exists())->toBeFalse();
});

test('a user cannot revoke their own current session', function () {
    // Direct controller invocation, not a full HTTP round trip — see this
    // file's header comment for why: this is the one rule that genuinely
    // requires the SAME request's session identity to be both "the one
    // making the call" and "the one named in the URL," which Laravel's
    // test client cannot simulate across two separate calls. This still
    // exercises the real Action, real Eloquent model, and the exact
    // conditional in App\Http\Controllers\Settings\SessionController —
    // only the routing/middleware layer (covered by the "requires a
    // confirmed password" test below and the guard-isolation tests from
    // Stage 1) is bypassed.
    $user = User::factory()->create();
    $sessionId = (string) Str::random(40);
    insertSessionRow($sessionId, $user->id);

    $request = Request::create('/settings/security/sessions/'.$sessionId, 'DELETE');
    $store = app('session')->driver('array');
    $store->setId($sessionId);
    $request->setLaravelSession($store);
    $this->actingAs($user, 'web');

    $sessionRecord = SessionRecord::query()->findOrFail($sessionId);
    $controller = app(SessionController::class);

    $response = $controller->destroy($request, $sessionRecord, app(RevokeSessionAction::class));

    expect($response->getSession()->get('errors')->has('session'))->toBeTrue();
    expect(DB::table('sessions')->where('id', $sessionId)->exists())->toBeTrue();
});

test('a user cannot revoke another user\'s session', function () {
    confirmedPasswordUser();
    $otherUser = User::factory()->create();
    insertSessionRow('someone-elses-session', $otherUser->id);

    $response = $this->delete(route('settings.security.sessions.destroy', 'someone-elses-session'));

    $response->assertForbidden();
    expect(DB::table('sessions')->where('id', 'someone-elses-session')->exists())->toBeTrue();
});

test('a user can log out all other devices at once', function () {
    Notification::fake();
    $user = confirmedPasswordUser();
    insertSessionRow('device-a', $user->id);
    insertSessionRow('device-b', $user->id);

    $response = $this->delete(route('settings.security.sessions.destroy-others'));

    $response->assertRedirect();
    expect(DB::table('sessions')->where('id', 'device-a')->exists())->toBeFalse();
    expect(DB::table('sessions')->where('id', 'device-b')->exists())->toBeFalse();
    Notification::assertSentTo($user, LoggedOutAllDevices::class);
});

test('logging out other devices when there are none does not send a notification', function () {
    Notification::fake();
    $user = confirmedPasswordUser();
    // Clear every session row this user has so far (confirmedPasswordUser()'s
    // own confirm-password call leaves one behind) — a clean slate so this
    // specific DELETE request's own session is unambiguously the only one.
    DB::table('sessions')->where('user_id', $user->id)->delete();

    $this->delete(route('settings.security.sessions.destroy-others'));

    Notification::assertNothingSent();
});

test('session management routes require a confirmed password', function () {
    $user = User::factory()->create();
    insertSessionRow('some-session', $user->id);

    // Signed in but never confirmed via POST /confirm-password.
    $response = $this->actingAs($user, 'web')->delete(route('settings.security.sessions.destroy', 'some-session'));

    $response->assertRedirect(route('password.confirm'));
});
