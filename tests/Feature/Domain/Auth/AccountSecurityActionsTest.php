<?php

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Auth\Actions\LogoutOtherDevicesAction;
use App\Domain\Auth\Actions\RevokeSessionAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Direct, HTTP-free coverage of the Actions layer — complements (doesn't
 * duplicate) the Feature tests in tests/Feature/Settings/, which exercise
 * the same Actions indirectly through the full controller/middleware
 * stack. These call the Actions directly to isolate their own behavior
 * and audit-log side effects.
 */
test('RevokeSessionAction deletes only the targeted session and writes an audit entry', function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert([
        ['id' => 'target', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
        ['id' => 'keep', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
    ]);

    app(RevokeSessionAction::class)->execute($user, 'target');

    expect(DB::table('sessions')->where('id', 'target')->exists())->toBeFalse();
    expect(DB::table('sessions')->where('id', 'keep')->exists())->toBeTrue();
    expect(AuditLogEntry::where('action', 'session.revoked')->where('actor_id', $user->id)->exists())->toBeTrue();
});

test('RevokeSessionAction cannot delete a session belonging to a different user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    DB::table('sessions')->insert([
        'id' => 'not-yours', 'user_id' => $other->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time(),
    ]);

    app(RevokeSessionAction::class)->execute($user, 'not-yours');

    // The action's own query is scoped by user_id regardless of caller-side
    // authorization — see the class docblock.
    expect(DB::table('sessions')->where('id', 'not-yours')->exists())->toBeTrue();
});

test('LogoutOtherDevicesAction deletes every session except the given one and returns the count', function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert([
        ['id' => 'current', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
        ['id' => 'a', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
        ['id' => 'b', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()],
    ]);

    $revoked = app(LogoutOtherDevicesAction::class)->execute($user, 'current');

    expect($revoked)->toBe(2);
    expect(DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all())->toBe(['current']);
});

test('LogoutOtherDevicesAction writes no audit entry when there was nothing to revoke', function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert([
        'id' => 'current', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time(),
    ]);

    $revoked = app(LogoutOtherDevicesAction::class)->execute($user, 'current');

    expect($revoked)->toBe(0);
    expect(AuditLogEntry::where('action', 'session.logged_out_other_devices')->exists())->toBeFalse();
});
