<?php

use App\Domain\Auth\Enums\AdminRole;
use App\Domain\Billing\Enums\BaselineStatus;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\VerificationReviewStatus;
use App\Domain\Billing\Models\Baseline;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\VerificationRecord;
use App\Domain\Jobs\Models\JobRun;
use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationNote;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AdminUser;
use Illuminate\Testing\TestResponse;

/**
 * Sends the X-Inertia header, matching how a real Inertia SPA navigation
 * requests a page — the response is then the raw JSON prop payload, not
 * the full HTML shell. The official assertInertia() test helper asserts
 * against a Blade view's embedded page data instead, which only exists on
 * a full-page (non-XHR) response — and a full-page response needs the
 * Vite-built asset manifest, which these pages' resources/js/Pages/Admin/
 * *.tsx files aren't in yet (a later, separate task in this same stage).
 * So these tests assert on the raw JSON body directly (via
 * assertInertiaComponent()/inertiaProp() below) rather than
 * assertInertia() — this exercises everything about these controllers
 * except the actual page component rendering, which the stage's live
 * browser smoke test covers once the pages exist.
 */
function inertiaGet(string $url): TestResponse
{
    $version = app(HandleInertiaRequests::class)->version(request());

    return test()->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version])->get($url);
}

function assertInertiaComponent(TestResponse $response, string $component): void
{
    $response->assertOk();
    expect($response->json('component'))->toBe($component);
}

function inertiaProp(TestResponse $response, string $path): mixed
{
    return $response->json("props.{$path}");
}

// --- Overview ----------------------------------------------------------------

test('the overview page renders a system-health snapshot', function () {
    $admin = AdminUser::factory()->create();
    Organization::factory()->count(2)->create();
    $this->actingAs($admin, 'admin');

    $response = inertiaGet(route('admin.overview'));

    assertInertiaComponent($response, 'Admin/Overview');
    expect(inertiaProp($response, 'organizations'))->not->toBeNull();
    expect(inertiaProp($response, 'audit_chain.valid'))->toBeTrue();
    expect(inertiaProp($response, 'active_admin_count'))->toBe(1);
});

// --- Organizations -------------------------------------------------------------

test('the organizations index lists orgs with member counts', function () {
    $admin = AdminUser::factory()->create();
    $organization = Organization::factory()->create();
    Member::factory()->for($organization, 'organization')->count(2)->create();
    $this->actingAs($admin, 'admin');

    $response = inertiaGet(route('admin.organizations'));

    assertInertiaComponent($response, 'Admin/Organizations/Index');
    expect(inertiaProp($response, 'organizations.0.members_count'))->toBe(2);
});

test('the organization detail page renders members, invitations, notes, and financial history', function () {
    $admin = AdminUser::factory()->create();
    $organization = Organization::factory()->create();
    Member::factory()->for($organization, 'organization')->create();
    Invitation::factory()->for($organization, 'organization')->create();
    OrganizationNote::factory()->for($organization, 'organization')->create();
    Baseline::factory()->for($organization, 'organization')->create();
    VerificationRecord::factory()->for($organization, 'organization')->create();
    Invoice::factory()->for($organization, 'organization')->create();
    $this->actingAs($admin, 'admin');

    $response = inertiaGet(route('admin.organizations.show', $organization));

    assertInertiaComponent($response, 'Admin/Organizations/Show');
    expect(inertiaProp($response, 'members'))->toHaveCount(1);
    expect(inertiaProp($response, 'invitations'))->toHaveCount(1);
    expect(inertiaProp($response, 'notes'))->toHaveCount(1);
    expect(inertiaProp($response, 'baselines'))->toHaveCount(1);
    expect(inertiaProp($response, 'verification_records'))->toHaveCount(1);
    expect(inertiaProp($response, 'invoices'))->toHaveCount(1);
});

test('updating organization status requires ADMIN', function () {
    $support = AdminUser::factory()->role(AdminRole::Support)->create();
    $organization = Organization::factory()->create();

    $this->actingAs($support, 'admin')
        ->post(route('admin.organizations.status', $organization), ['status' => 'ACTIVE'])
        ->assertForbidden();
});

test('an ADMIN can update organization status', function () {
    $admin = AdminUser::factory()->role(AdminRole::Admin)->create();
    $organization = Organization::factory()->create(['status' => 'PILOT']);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.organizations.status', $organization), ['status' => 'ACTIVE'])
        ->assertRedirect();

    expect($organization->refresh()->status->value)->toBe('ACTIVE');
});

test('any active admin can add an organization note, no role gate', function () {
    $auditor = AdminUser::factory()->role(AdminRole::Auditor)->create();
    $organization = Organization::factory()->create();

    $this->actingAs($auditor, 'admin')
        ->post(route('admin.organizations.notes.store', $organization), ['body' => 'Called the customer.'])
        ->assertRedirect();

    expect(OrganizationNote::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $organization->id)->count())->toBe(1);
});

test('changing a member role requires ADMIN or SUPPORT', function () {
    $analyst = AdminUser::factory()->role(AdminRole::VerificationAnalyst)->create();
    $organization = Organization::factory()->create();
    $member = Member::factory()->for($organization, 'organization')->create();

    $this->actingAs($analyst, 'admin')
        ->post(route('admin.organizations.members.role', [$organization, $member]), ['role' => 'OWNER'])
        ->assertForbidden();
});

test('a member id from a different org 404s on the org-scoped member route', function () {
    $admin = AdminUser::factory()->role(AdminRole::Admin)->create();
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $foreignMember = Member::factory()->for($otherOrganization, 'organization')->create();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.organizations.members.role', [$organization, $foreignMember]), ['role' => 'OWNER'])
        ->assertNotFound();
});

test('resending an invitation requires ADMIN or SUPPORT', function () {
    $analyst = AdminUser::factory()->role(AdminRole::VerificationAnalyst)->create();
    $organization = Organization::factory()->create();
    $invitation = Invitation::factory()->for($organization, 'organization')->create();

    $this->actingAs($analyst, 'admin')
        ->post(route('admin.organizations.invitations.resend', [$organization, $invitation]))
        ->assertForbidden();
});

test('erasing personal data requires ADMIN', function () {
    $support = AdminUser::factory()->role(AdminRole::Support)->create();
    $organization = Organization::factory()->create();

    $this->actingAs($support, 'admin')
        ->post(route('admin.organizations.erase', $organization), ['confirm_slug' => $organization->slug])
        ->assertForbidden();
});

// --- Baselines -------------------------------------------------------------

test('the baselines index lists baselines across every organization', function () {
    $admin = AdminUser::factory()->create();
    Baseline::factory()->count(2)->create();
    $this->actingAs($admin, 'admin');

    $response = inertiaGet(route('admin.baselines'));

    assertInertiaComponent($response, 'Admin/Baselines');
    expect(inertiaProp($response, 'baselines'))->toHaveCount(2);
});

test('locking a baseline requires ADMIN or VERIFICATION_ANALYST', function () {
    $support = AdminUser::factory()->role(AdminRole::Support)->create();
    $baseline = Baseline::factory()->create(['status' => BaselineStatus::Draft]);

    $this->actingAs($support, 'admin')
        ->post(route('admin.baselines.lock', $baseline))
        ->assertForbidden();
});

test('a VERIFICATION_ANALYST can lock a baseline', function () {
    $analyst = AdminUser::factory()->role(AdminRole::VerificationAnalyst)->create();
    $baseline = Baseline::factory()->create(['status' => BaselineStatus::Draft]);

    $this->actingAs($analyst, 'admin')
        ->post(route('admin.baselines.lock', $baseline))
        ->assertRedirect();

    expect($baseline->refresh()->status)->toBe(BaselineStatus::Locked);
});

// --- Verification -------------------------------------------------------------

test('the verification index lists records and can filter by organization', function () {
    $admin = AdminUser::factory()->create();
    $organization = Organization::factory()->create();
    VerificationRecord::factory()->for($organization, 'organization')->create();
    VerificationRecord::factory()->create();
    $this->actingAs($admin, 'admin');

    $response = inertiaGet(route('admin.verification', ['organization_id' => $organization->id]));

    assertInertiaComponent($response, 'Admin/Verification');
    expect(inertiaProp($response, 'records'))->toHaveCount(1);
});

test('reopening a disputed record requires ADMIN specifically, not VERIFICATION_ANALYST', function () {
    $analyst = AdminUser::factory()->role(AdminRole::VerificationAnalyst)->create();
    $record = VerificationRecord::factory()->create(['review_status' => VerificationReviewStatus::Disputed]);

    $this->actingAs($analyst, 'admin')
        ->post(route('admin.verification.reopen', $record))
        ->assertForbidden();
});

test('an ADMIN can reopen a disputed record', function () {
    $admin = AdminUser::factory()->role(AdminRole::Admin)->create();
    $record = VerificationRecord::factory()->create(['review_status' => VerificationReviewStatus::Disputed]);

    $this->actingAs($admin, 'admin')
        ->post(route('admin.verification.reopen', $record))
        ->assertRedirect();

    expect($record->refresh()->review_status)->toBe(VerificationReviewStatus::ShadowPendingReview);
});

// --- Invoices -------------------------------------------------------------

test('the invoices index lists invoiceable records and existing invoices', function () {
    $admin = AdminUser::factory()->create();
    VerificationRecord::factory()->create(['review_status' => VerificationReviewStatus::LiveApproved]);
    Invoice::factory()->create();
    $this->actingAs($admin, 'admin');

    $response = inertiaGet(route('admin.invoices'));

    assertInertiaComponent($response, 'Admin/Invoices');
    expect(inertiaProp($response, 'invoiceable_records'))->toHaveCount(1);
    expect(inertiaProp($response, 'invoices'))->toHaveCount(1);
});

test('voiding an invoice requires ADMIN, not shared with VERIFICATION_ANALYST', function () {
    $analyst = AdminUser::factory()->role(AdminRole::VerificationAnalyst)->create();
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);

    $this->actingAs($analyst, 'admin')
        ->post(route('admin.invoices.void', $invoice))
        ->assertForbidden();
});

// --- Team -------------------------------------------------------------

test('the team index lists the admin roster', function () {
    $admin = AdminUser::factory()->create();
    AdminUser::factory()->count(2)->create();
    $this->actingAs($admin, 'admin');

    $response = inertiaGet(route('admin.team'));

    assertInertiaComponent($response, 'Admin/Team');
    expect(inertiaProp($response, 'admins'))->toHaveCount(3);
});

test('creating an admin user requires ADMIN and flashes the temp password once', function () {
    $admin = AdminUser::factory()->role(AdminRole::Admin)->create();

    $response = $this->actingAs($admin, 'admin')->post(route('admin.team.store'), [
        'name' => 'New Analyst',
        'email' => 'analyst@example.test',
        'role' => 'VERIFICATION_ANALYST',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('created_admin');
});

test('a SUPPORT admin cannot create an admin user', function () {
    $support = AdminUser::factory()->role(AdminRole::Support)->create();

    $this->actingAs($support, 'admin')->post(route('admin.team.store'), [
        'name' => 'New Analyst', 'email' => 'analyst2@example.test', 'role' => 'SUPPORT',
    ])->assertForbidden();
});

// --- Audit log -------------------------------------------------------------

test('the audit log renders with an integrity banner, no role gate', function () {
    $auditor = AdminUser::factory()->role(AdminRole::Auditor)->create();
    $this->actingAs($auditor, 'admin');

    $response = inertiaGet(route('admin.audit-log'));

    assertInertiaComponent($response, 'Admin/AuditLog');
    expect(inertiaProp($response, 'integrity.valid'))->toBeTrue();
});

// --- Jobs -------------------------------------------------------------

test('the jobs index shows the three known jobs with only run-verification triggerable', function () {
    $admin = AdminUser::factory()->create();
    JobRun::factory()->succeeded()->create(['job_name' => 'run-verification']);
    $this->actingAs($admin, 'admin');

    $response = inertiaGet(route('admin.jobs'));

    assertInertiaComponent($response, 'Admin/Jobs');
    expect(inertiaProp($response, 'known_jobs'))->toHaveCount(3);
    expect(inertiaProp($response, 'known_jobs.1.job_name'))->toBe('run-verification');
    expect(inertiaProp($response, 'known_jobs.1.triggerable'))->toBeTrue();
    expect(inertiaProp($response, 'known_jobs.0.triggerable'))->toBeFalse();
});

test('triggering run-verification requires ADMIN', function () {
    $support = AdminUser::factory()->role(AdminRole::Support)->create();

    $this->actingAs($support, 'admin')
        ->post(route('admin.jobs.run-verification'))
        ->assertForbidden();
});

test('an ADMIN can trigger run-verification, creating a job run', function () {
    $admin = AdminUser::factory()->role(AdminRole::Admin)->create();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.jobs.run-verification'))
        ->assertRedirect();

    expect(JobRun::query()->where('job_name', 'run-verification')->count())->toBe(1);
});
