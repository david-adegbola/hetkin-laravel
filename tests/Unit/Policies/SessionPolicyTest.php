<?php

use App\Domain\Auth\Models\SessionRecord;
use App\Models\User;
use App\Policies\SessionPolicy;

test('a user may revoke their own session', function () {
    $user = new User;
    $user->id = 'user-1';
    $session = new SessionRecord;
    $session->user_id = 'user-1';

    expect((new SessionPolicy)->revoke($user, $session))->toBeTrue();
});

test('a user may not revoke another user\'s session', function () {
    $user = new User;
    $user->id = 'user-1';
    $session = new SessionRecord;
    $session->user_id = 'user-2';

    expect((new SessionPolicy)->revoke($user, $session))->toBeFalse();
});
