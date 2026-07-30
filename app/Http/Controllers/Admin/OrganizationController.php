<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Billing\Actions\UpdateOrganizationPricingAction;
use App\Domain\Billing\Exceptions\BillingActionException;
use App\Domain\Billing\Models\Baseline;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\VerificationRecord;
use App\Domain\Organizations\Actions\AddOrganizationNoteAction;
use App\Domain\Organizations\Actions\ErasePersonalDataAction;
use App\Domain\Organizations\Actions\RemoveMemberAction;
use App\Domain\Organizations\Actions\ResendInvitationAction;
use App\Domain\Organizations\Actions\UpdateMemberRoleAction;
use App\Domain\Organizations\Actions\UpdateOrganizationStatusAction;
use App\Domain\Organizations\Exceptions\OrganizationActionException;
use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationNote;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/organizations/
 * {page.tsx,[id]/page.tsx,[id]/actions.ts}. The index has no role gate —
 * any active admin, including AUDITOR, can view it; mutations below are
 * individually role-gated per route to match each Action's v2 allowed-role
 * list exactly (see routes/admin-protected.php).
 *
 * Member/Invitation sub-resources are deliberately NOT resolved via
 * implicit route-model binding — App\Domain\Organizations\Scopes\
 * OrganizationScope fails closed without a bound CurrentOrganization, which
 * nothing in the admin realm sets up. Resolving them here instead, scoped
 * to the route's own {organization}, is both the simpler fix and a real
 * IDOR guard: a member/invitation id from a DIFFERENT org 404s instead of
 * silently acting on the wrong tenant's row.
 */
class OrganizationController extends Controller
{
    public function index(): Response
    {
        $organizations = Organization::query()->orderByDesc('created_at')->get();
        $organizationIds = $organizations->pluck('id');

        // Member/Baseline are BelongsToOrganization-scoped, and nothing in
        // the admin realm binds a CurrentOrganization — every relation-based
        // count/eager-load here would hit App\Domain\Organizations\Scopes\
        // OrganizationScope's fail-closed guard. Bulk, explicitly-unscoped
        // queries grouped by organization_id sidestep that, same shape as
        // App\Http\Controllers\Admin\PilotApplicationController's pending-
        // invitations grouping.
        $memberCounts = Member::withoutGlobalScope(OrganizationScope::class)
            ->whereIn('organization_id', $organizationIds)
            ->selectRaw('organization_id, count(*) as aggregate')
            ->groupBy('organization_id')
            ->pluck('aggregate', 'organization_id');

        $lockedBaselineOrgIds = Baseline::withoutGlobalScope(OrganizationScope::class)
            ->where('status', 'LOCKED')
            ->whereIn('organization_id', $organizationIds)
            ->pluck('organization_id')
            ->unique();

        return Inertia::render('Admin/Organizations/Index', [
            'organizations' => $organizations->map(fn (Organization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'status' => $organization->status->value,
                'members_count' => (int) ($memberCounts[$organization->id] ?? 0),
                'has_locked_baseline' => $lockedBaselineOrgIds->contains($organization->id),
                'created_at' => $organization->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function show(Organization $organization): Response
    {
        $members = Member::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->with('user')
            ->orderBy('created_at')
            ->get();

        $invitations = Invitation::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->orderByDesc('created_at')
            ->get();

        $notes = OrganizationNote::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->with('author')
            ->orderByDesc('pinned')
            ->orderByDesc('created_at')
            ->get();

        $baselines = Baseline::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->orderByDesc('created_at')
            ->get();

        $verificationRecords = VerificationRecord::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->orderByDesc('period_start')
            ->get();

        $invoices = Invoice::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->orderByDesc('created_at')
            ->get();

        $activity = AuditLogEntry::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $alerts = Alert::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('Admin/Organizations/Show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'status' => $organization->status->value,
                'process_type' => $organization->process_type?->value,
                'base_fee_cents' => $organization->base_fee_cents,
                'revenue_share_percent' => $organization->revenue_share_percent !== null
                    ? (float) $organization->revenue_share_percent
                    : null,
                'created_at' => $organization->created_at->toIso8601String(),
                'pilot_application_id' => $organization->pilot_application_id,
            ],
            'members' => $members->map(fn (Member $member) => [
                'id' => $member->id,
                'user_id' => $member->user_id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => $member->role->value,
                'created_at' => $member->created_at->toIso8601String(),
            ]),
            'invitations' => $invitations->map(fn (Invitation $invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'status' => $invitation->status,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'created_at' => $invitation->created_at->toIso8601String(),
            ]),
            'notes' => $notes->map(fn ($note) => [
                'id' => $note->id,
                'category' => $note->category->value,
                'body' => $note->body,
                'pinned' => $note->pinned,
                'author_name' => $note->author->name,
                'created_at' => $note->created_at->toIso8601String(),
            ]),
            'baselines' => $baselines->map(fn (Baseline $baseline) => [
                'id' => $baseline->id,
                'status' => $baseline->status->value,
                'methodology_version' => $baseline->methodology_version,
                'baseline_annual_cost_cents' => $baseline->baseline_annual_cost_cents,
                'created_at' => $baseline->created_at->toIso8601String(),
            ]),
            'verification_records' => $verificationRecords->map(fn (VerificationRecord $record) => [
                'id' => $record->id,
                'period_start' => $record->period_start->toIso8601String(),
                'period_end' => $record->period_end->toIso8601String(),
                'review_status' => $record->review_status->value,
                'verified_savings_cents' => $record->verified_savings_cents,
                'variance_flagged' => $record->variance_flagged,
            ]),
            'invoices' => $invoices->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'status' => $invoice->status->value,
                'total_cents' => $invoice->total_cents,
                'issued_at' => $invoice->issued_at?->toIso8601String(),
                'created_at' => $invoice->created_at->toIso8601String(),
            ]),
            'activity' => $activity->map(fn (AuditLogEntry $entry) => [
                'id' => $entry->id,
                'action' => $entry->action,
                'actor_type' => $entry->actor_type->value,
                'actor_id' => $entry->actor_id,
                'entity_type' => $entry->entity_type,
                'entity_id' => $entry->entity_id,
                'metadata' => $entry->metadata,
                'created_at' => $entry->created_at->toIso8601String(),
            ]),
            'alerts' => $alerts->map(fn (Alert $alert) => [
                'id' => $alert->id,
                'type' => $alert->type->value,
                'severity' => $alert->severity->value,
                'title' => $alert->title,
                'body' => $alert->body,
                'created_at' => $alert->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function updateStatus(Request $request, Organization $organization, UpdateOrganizationStatusAction $action): RedirectResponse
    {
        try {
            $action->execute($organization, $this->admin(), (string) $request->input('status'));
        } catch (OrganizationActionException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }

        return back();
    }

    public function updatePricing(Request $request, Organization $organization, UpdateOrganizationPricingAction $action): RedirectResponse
    {
        try {
            $action->execute(
                $organization,
                $this->admin(),
                $request->input('base_fee_eur'),
                $request->input('revenue_share_percent'),
            );
        } catch (BillingActionException $e) {
            throw ValidationException::withMessages(['base_fee_eur' => $e->getMessage()]);
        }

        return back();
    }

    public function updateMemberRole(Request $request, Organization $organization, string $member, UpdateMemberRoleAction $action): RedirectResponse
    {
        $memberModel = $this->resolveMember($organization, $member);

        try {
            $action->execute($memberModel, $this->admin(), (string) $request->input('role'));
        } catch (OrganizationActionException $e) {
            throw ValidationException::withMessages(['role' => $e->getMessage()]);
        }

        return back();
    }

    public function removeMember(Organization $organization, string $member, RemoveMemberAction $action): RedirectResponse
    {
        $action->execute($this->resolveMember($organization, $member), $this->admin());

        return back();
    }

    public function addNote(Request $request, Organization $organization, AddOrganizationNoteAction $action): RedirectResponse
    {
        try {
            $action->execute(
                $organization,
                $this->admin(),
                (string) $request->input('body'),
                (string) $request->input('category', 'GENERAL'),
                $request->boolean('pinned'),
            );
        } catch (OrganizationActionException $e) {
            throw ValidationException::withMessages(['body' => $e->getMessage()]);
        }

        return back();
    }

    public function resendInvitation(Organization $organization, string $invitation, ResendInvitationAction $action): RedirectResponse
    {
        $invitationModel = Invitation::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->findOrFail($invitation);

        try {
            $action->execute($invitationModel, $this->admin());
        } catch (OrganizationActionException $e) {
            throw ValidationException::withMessages(['invitation' => $e->getMessage()]);
        }

        return back();
    }

    public function eraseData(Request $request, Organization $organization, ErasePersonalDataAction $action): RedirectResponse
    {
        try {
            $action->execute($organization, $this->admin(), (string) $request->input('confirm_slug'));
        } catch (OrganizationActionException $e) {
            throw ValidationException::withMessages(['confirm_slug' => $e->getMessage()]);
        }

        return back();
    }

    private function resolveMember(Organization $organization, string $memberId): Member
    {
        return Member::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->findOrFail($memberId);
    }

    private function admin(): AdminUser
    {
        /** @var AdminUser $admin */
        $admin = Auth::guard('admin')->user();

        return $admin;
    }
}
