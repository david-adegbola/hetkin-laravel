<?php

test('the support page renders for a member', function () {
    ['user' => $user] = portalUser();

    $response = $this->actingAs($user, 'web')->get(route('support'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Portal/Support'));
});

test('a non-member cannot reach the support page', function () {
    $response = $this->get(route('support'));

    $response->assertRedirect(route('login'));
});
