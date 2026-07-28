<?php

use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('the security settings page can be rendered', function () {
    $user = confirmedPasswordUser();

    $this->get(route('settings.security'))->assertOk();
});

test('a user can change their password', function () {
    Notification::fake();
    $user = confirmedPasswordUser();

    $response = $this->put(route('settings.security.password'), [
        'current_password' => 'password',
        'password' => strong_password(),
        'password_confirmation' => strong_password(),
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $user->refresh();
    expect(Hash::check(strong_password(), $user->password))->toBeTrue();
    expect($user->password_changed_at)->not->toBeNull();

    Notification::assertSentTo($user, PasswordChanged::class);
});

test('changing password requires the correct current password', function () {
    $user = confirmedPasswordUser();

    $response = $this->put(route('settings.security.password'), [
        'current_password' => 'wrong-current-password',
        'password' => strong_password(),
        'password_confirmation' => strong_password(),
    ]);

    $response->assertSessionHasErrors('current_password');
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('a weak new password is rejected', function () {
    confirmedPasswordUser();

    $response = $this->put(route('settings.security.password'), [
        'current_password' => 'password',
        'password' => 'short1!',
        'password_confirmation' => 'short1!',
    ]);

    $response->assertSessionHasErrors('password');
});

test('changing password invalidates every other session', function () {
    $user = confirmedPasswordUser();
    DB::table('sessions')->insert([
        'id' => 'other-device-session',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => base64_encode('{}'),
        'last_activity' => time(),
    ]);

    $this->put(route('settings.security.password'), [
        'current_password' => 'password',
        'password' => strong_password(),
        'password_confirmation' => strong_password(),
    ]);

    expect(DB::table('sessions')->where('id', 'other-device-session')->exists())->toBeFalse();
});

test('changing password does not leave the pre-change session as an orphaned row', function () {
    // Regression test: PasswordController::update() must call
    // $request->session()->regenerate(destroy: true), not the bare
    // regenerate(). The default $destroy = false only abandons the old,
    // still-fully-authenticated session ID rather than destroying it,
    // which leaves a live orphaned row in `sessions` indistinguishable
    // from a genuine "other device" in the Active Sessions list.
    config(['session.driver' => 'database']);
    $user = confirmedPasswordUser();

    // confirmedPasswordUser() already made one real, authenticated request
    // (POST /confirm-password), which persisted exactly one row for this
    // user in the real `sessions` table.
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(1);

    $this->put(route('settings.security.password'), [
        'current_password' => 'password',
        'password' => strong_password(),
        'password_confirmation' => strong_password(),
    ]);

    // Whichever specific row PasswordController's own request session
    // turns out to be, LogoutOtherDevicesAction plus a destroying
    // regenerate must converge on exactly one row afterward: the freshly
    // regenerated session. A stray second row means the pre-change session
    // was abandoned instead of invalidated.
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(1);
});

test('the password-changed notification mentions other devices only when there were any', function () {
    Notification::fake();
    $user = confirmedPasswordUser();
    // No other session rows exist for this user.

    $this->put(route('settings.security.password'), [
        'current_password' => 'password',
        'password' => strong_password(),
        'password_confirmation' => strong_password(),
    ]);

    Notification::assertSentTo($user, PasswordChanged::class, function (PasswordChanged $notification) {
        return $notification->hadOtherActiveSessions === false;
    });
});
