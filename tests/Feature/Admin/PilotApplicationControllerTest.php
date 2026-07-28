<?php

use App\Domain\Auth\Enums\AdminRole;
use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Domain\Pilots\Models\PilotApplication;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Notification;

test('the pilots page lists pending and decided applications separately', function () {
    $admin = AdminUser::factory()->role(AdminRole::Admin)->create();
    PilotApplication::factory()->create(['icp_score' => 90]);
    PilotApplication::factory()->approved()->create(['icp_score' => 50]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.pilots'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Pilots')
        ->has('pending', 1)
        ->has('decided', 1));
});

test('pending applications are sorted by ICP score descending', function () {
    $admin = AdminUser::factory()->role(AdminRole::Admin)->create();
    $low = PilotApplication::factory()->create(['icp_score' => 25, 'company_name' => 'Low']);
    $high = PilotApplication::factory()->create(['icp_score' => 100, 'company_name' => 'High']);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.pilots'));

    $response->assertInertia(fn ($page) => $page
        ->where('pending.0.company_name', 'High')
        ->where('pending.1.company_name', 'Low'));
});

test('a SUPPORT admin can approve an application', function () {
    Notification::fake();
    $admin = AdminUser::factory()->role(AdminRole::Support)->create();
    $application = PilotApplication::factory()->create();

    $response = $this->actingAs($admin, 'admin')->post(route('admin.pilots.approve', $application));

    $response->assertRedirect();
    expect($application->refresh()->status->value)->toBe('APPROVED');
});

test('a SUPPORT admin can reject an application', function () {
    $admin = AdminUser::factory()->role(AdminRole::Support)->create();
    $application = PilotApplication::factory()->create();

    $response = $this->actingAs($admin, 'admin')->post(route('admin.pilots.reject', $application));

    $response->assertRedirect();
    expect($application->refresh()->status->value)->toBe('REJECTED');
});

test('a VERIFICATION_ANALYST admin cannot approve or reject — 403', function () {
    $admin = AdminUser::factory()->role(AdminRole::VerificationAnalyst)->create();
    $application = PilotApplication::factory()->create();

    $this->actingAs($admin, 'admin')->post(route('admin.pilots.approve', $application))->assertForbidden();
    $this->actingAs($admin, 'admin')->post(route('admin.pilots.reject', $application))->assertForbidden();
    expect($application->refresh()->status->value)->toBe('PENDING');
});

test('a guest cannot reach the pilots page', function () {
    $response = $this->get(route('admin.pilots'));

    $response->assertRedirect(route('admin.login'));
});

test('a decided application with a pending invitation exposes an invite link', function () {
    Notification::fake();
    $admin = AdminUser::factory()->role(AdminRole::Admin)->create();
    $application = PilotApplication::factory()->create();

    $this->actingAs($admin, 'admin')->post(route('admin.pilots.approve', $application));

    $invitation = Invitation::withoutGlobalScope(OrganizationScope::class)->first();

    $response = $this->actingAs($admin, 'admin')->get(route('admin.pilots'));

    $response->assertInertia(fn ($page) => $page
        ->where('decided.0.invite_url', route('invite.show', ['invitation' => $invitation->id])));
});
