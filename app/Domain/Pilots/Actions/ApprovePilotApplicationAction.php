<?php

namespace App\Domain\Pilots\Actions;

use App\Domain\Analytics\Services\PostHogService;
use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Organizations\Enums\OrgRole;
use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Pilots\DTOs\ApprovePilotApplicationResult;
use App\Domain\Pilots\Enums\PilotApplicationStatus;
use App\Domain\Pilots\Models\PilotApplication;
use App\Models\AdminUser;
use App\Notifications\PilotApplicationApproved;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ports the Next.js app's approvePilotApplication() Server Action exactly
 * (src/app/admin/(protected)/pilots/actions.ts). A single combined step,
 * not "approve" then a separate "invite": provisions the customer
 * Organization directly and creates its first Invitation (role OWNER) in
 * one transaction — there's no reason for a human to retype data the
 * application already captured, and an Organization nobody can sign into
 * would be a real gap (Phase 4 §5: no self-serve signup anywhere in this
 * app; the only account-creation path is invite acceptance).
 *
 * Deliberately has no application-level idempotency guard (no "already
 * decided?" check before proceeding) — matching v2 exactly. The backstop
 * is `organizations.pilot_application_id`'s own unique constraint (mirrors
 * the Prisma schema's `pilotApplicationId String? @unique`): a second
 * approval of the same application throws a raw database exception rather
 * than being rejected gracefully or silently no-op'd. That's v2's real
 * behavior, not an oversight introduced here — see MIGRATION_PLAN.md's
 * Stage 8 ADR. The caller (the admin controller) is expected to only
 * offer this action for still-open applications, the same way v2's own
 * admin UI only ever renders Approve/Reject buttons for pending rows —
 * but nothing stops a replayed request from hitting the constraint.
 */
class ApprovePilotApplicationAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PostHogService $postHog,
    ) {}

    /** 14 days — longer than a normal peer invite (7 days, InvitationFactory), since this is the pilot's first contact and may sit in an inbox over a weekend. */
    private const INVITATION_TTL_DAYS = 14;

    public function execute(PilotApplication $application, AdminUser $adminUser): ApprovePilotApplicationResult
    {
        $result = DB::transaction(function () use ($application, $adminUser) {
            $organization = Organization::create([
                'name' => $application->company_name,
                'slug' => $this->slugify($application->company_name),
                'process_type' => $application->process_type,
                'pilot_application_id' => $application->id,
            ]);

            $application->update([
                'status' => PilotApplicationStatus::Approved,
                'reviewed_by_admin_user_id' => $adminUser->id,
                'reviewed_at' => now(),
            ]);

            // No self-serve signup — approval provisions the org, and the
            // applicant's contact gets a one-time invitation to create the
            // account that becomes the org's OWNER.
            $invitation = Invitation::create([
                'organization_id' => $organization->id,
                'email' => $application->contact_email,
                'role' => OrgRole::Owner,
                'status' => 'pending',
                'invited_by_user_id' => "admin:{$adminUser->id}",
                'expires_at' => now()->addDays(self::INVITATION_TTL_DAYS),
            ]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'pilot_application.approved',
                entityType: 'PilotApplication',
                entityId: $application->id,
                organizationId: $organization->id,
                metadata: ['organizationId' => $organization->id, 'invitationId' => $invitation->id],
            ));

            return new ApprovePilotApplicationResult($application->refresh(), $organization, $invitation);
        });

        // The Organization + Invitation are already committed above, and
        // the invite link stays available in the admin Pilots page
        // (PilotApplicationController::index's `invite_url`) regardless of
        // whether this email goes out — an admin can always copy/paste it
        // manually. A send failure here must not turn the already-
        // successful approval into a 500. Same posture as every other
        // post-hoc notification in this app; see
        // SubmitPilotApplicationAction's docblock for the incident that
        // established the pattern.
        try {
            Notification::route('mail', $application->contact_email)
                ->notify(new PilotApplicationApproved(
                    $result->invitation,
                    $application->contact_name,
                    $result->organization->name,
                ));
        } catch (Throwable $e) {
            Log::error('[pilots] failed to send application-approved notification', ['exception' => $e, 'applicationId' => $application->id]);
        }

        // Phase 1 §8 funnel: "Qualification → Approval". Same distinctId
        // (contact email) as pilot_application_submitted, so the two
        // events chain into one funnel step for the same person in
        // PostHog.
        $this->postHog->capture('pilot_application_approved', $application->contact_email, [
            'applicationId' => $application->id,
            'organizationId' => $result->organization->id,
            'processType' => $application->process_type->value,
        ]);

        return $result;
    }

    /**
     * Mirrors the Next.js app's slugify() exactly (lowercase, non-alnum
     * runs collapsed to a single `-`, trimmed, `"org"` fallback when
     * empty) plus a random suffix — not checked for collision against
     * existing slugs, matching v2's own accepted looseness (no
     * retry-on-conflict logic there either).
     */
    private function slugify(string $name): string
    {
        $base = Str::slug($name) ?: 'org';
        $suffix = Str::lower(Str::random(5));

        return "{$base}-{$suffix}";
    }
}
