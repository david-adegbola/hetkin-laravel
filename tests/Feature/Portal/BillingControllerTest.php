<?php

use App\Domain\Billing\Models\Invoice;
use App\Domain\Organizations\CurrentOrganization;

test('the billing page lists invoices for the organization', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    Invoice::factory()->issued()->for($organization, 'organization')->create();

    $response = $this->actingAs($user, 'web')->get(route('billing'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Portal/Billing')
        ->has('invoices', 1));
});

test('pricing is null when the organization has no base fee or revenue share set', function () {
    ['user' => $user] = portalUser();

    $response = $this->actingAs($user, 'web')->get(route('billing'));

    $response->assertInertia(fn ($page) => $page->where('pricing', null));
});

test('pricing is shown when both fields are set', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    $organization->update(['base_fee_cents' => 50_000, 'revenue_share_percent' => 10]);

    $response = $this->actingAs($user, 'web')->get(route('billing'));

    $response->assertInertia(fn ($page) => $page
        ->where('pricing.base_fee_cents', 50_000)
        ->where('pricing.revenue_share_percent', 10));
});

test('a non-member cannot reach the billing page', function () {
    $response = $this->get(route('billing'));

    $response->assertRedirect(route('login'));
});
