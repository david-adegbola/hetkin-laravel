<?php

use App\Domain\Auth\Services\ActiveSessionFinder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Direct, HTTP-free coverage of the "which row is the current device"
 * logic — the one piece of Settings/Security's active-sessions feature
 * that HTTP-level tests in SessionManagementTest.php can't reliably assert
 * on (see that file's header comment: Laravel's test client doesn't carry
 * a session cookie between separate calls, so "is this row the one making
 * THIS specific request" isn't something a two-call HTTP test can predict).
 * ActiveSessionFinder::forUser() takes the current session ID as a plain
 * argument, so it's trivial to test directly with a known value.
 */
function insertRawSessionRow(string $id, string $userId, ?string $userAgent = null, ?string $ip = '198.51.100.10'): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => $ip,
        'user_agent' => $userAgent ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        'payload' => base64_encode('{}'),
        'last_activity' => time(),
    ]);
}

test('it flags the row matching the given current session id', function () {
    $user = User::factory()->create();
    insertRawSessionRow('session-a', $user->id);
    insertRawSessionRow('session-b', $user->id);

    $sessions = app(ActiveSessionFinder::class)->forUser($user, 'session-b');

    $byId = $sessions->keyBy('id');
    expect($byId->get('session-a')->isCurrentDevice)->toBeFalse();
    expect($byId->get('session-b')->isCurrentDevice)->toBeTrue();
});

test('no row is flagged current when the given id matches none', function () {
    $user = User::factory()->create();
    insertRawSessionRow('session-a', $user->id);

    $sessions = app(ActiveSessionFinder::class)->forUser($user, 'some-other-id-entirely');

    expect($sessions->every(fn ($s) => $s->isCurrentDevice === false))->toBeTrue();
});

test('it only returns sessions belonging to the given user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    insertRawSessionRow('mine', $user->id);
    insertRawSessionRow('not-mine', $otherUser->id);

    $sessions = app(ActiveSessionFinder::class)->forUser($user, 'mine');

    expect($sessions)->toHaveCount(1);
    expect($sessions->first()->id)->toBe('mine');
});

test('it orders sessions by most recently active first', function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert([
        ['id' => 'older', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time() - 3600],
        ['id' => 'newer', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
    ]);

    $sessions = app(ActiveSessionFinder::class)->forUser($user, 'newer');

    expect($sessions->pluck('id')->all())->toBe(['newer', 'older']);
});

test('it parses browser and platform from the user agent', function () {
    $user = User::factory()->create();
    insertRawSessionRow(
        'session-a',
        $user->id,
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
    );

    $session = app(ActiveSessionFinder::class)->forUser($user, 'session-a')->first();

    expect($session->browser)->toBe('Chrome');
    expect($session->platform)->toBe('macOS');
});
