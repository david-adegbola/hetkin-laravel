<?php

namespace App\Domain\Organizations\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Auth\Models\SessionRecord;
use App\Domain\Organizations\Enums\OrganizationNoteCategory;
use App\Domain\Organizations\Exceptions\OrganizationActionException;
use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationNote;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ports the Next.js app's erasePersonalData() Server Action exactly (src/
 * app/admin/(protected)/organizations/[id]/actions.ts) — GDPR Article 17
 * erasure. ADMIN only.
 *
 * Every Member/Invitation query here deliberately opts out of
 * OrganizationScope (App\Domain\Organizations\Concerns\
 * BelongsToOrganization's scope) rather than relying on CurrentOrganization
 * being bound — this Action must work correctly from an admin context that
 * has no "current organization" concept, and one query (a member's OTHER
 * org memberships) is inherently cross-tenant and could never be expressed
 * through the scope anyway. Mirrors v2's own `adminScopedDb()` used for the
 * same reasons in the source.
 *
 * The confirmation gate is a single check: the caller must type the
 * organization's own slug exactly.
 *
 * What this erases, per member (only when that member's User account isn't
 * shared with another org):
 *  - all of that user's sessions (immediate logout everywhere)
 *  - name/email/image, replaced with a deterministic "erased-<id>@erased.
 *    hetkin.invalid" placeholder
 *  - the password, rotated to a random unusable value
 *
 * v2's schema has no separate Account table to delete (Better Auth
 * credential storage) — this port's User model holds the password hash
 * directly (MIGRATION_PLAN.md ADR-2), so revoking login access here means
 * rotating User.password to an unknown random value, not just deleting a
 * row. This is a deliberate mechanism substitution for a schema difference,
 * not a change to the stated intent ("erasure also means immediate logout/
 * access revocation").
 *
 * Members who share their User account with another org are skipped
 * entirely (their Member row, name, email, sessions — untouched); erasing
 * a shared account would break that other org's access.
 *
 * Deliberately does NOT touch: MeterReading, ScheduleBlock,
 * SavingsAssessment, VerificationRecord, Invoice, Baseline — GDPR Art.
 * 17(3)(b)/(c) permits retaining operational/financial records needed for
 * a legal obligation (Finnish bookkeeping law requires 6 years' retention).
 *
 * Always creates a permanent, pinned OrganizationNote summarizing exactly
 * what was erased — this note is the human-readable record; the audit log
 * entry links to it via `noteId`.
 */
class ErasePersonalDataAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @throws OrganizationActionException
     */
    public function execute(Organization $organization, AdminUser $adminUser, string $confirmSlug): OrganizationNote
    {
        if (trim($confirmSlug) !== $organization->slug) {
            throw new OrganizationActionException(
                sprintf('Type the organization slug ("%s") to confirm erasure.', $organization->slug),
            );
        }

        $members = Member::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->get();

        $pilotApplication = $organization->pilotApplication;

        return DB::transaction(function () use ($organization, $adminUser, $members, $pilotApplication) {
            $membersAnonymized = 0;
            $membersSkipped = 0;

            foreach ($members as $member) {
                $otherMemberships = Member::withoutGlobalScope(OrganizationScope::class)
                    ->where('user_id', $member->user_id)
                    ->where('organization_id', '!=', $organization->id)
                    ->count();

                if ($otherMemberships > 0) {
                    $membersSkipped++;

                    continue;
                }

                SessionRecord::where('user_id', $member->user_id)->delete();

                User::whereKey($member->user_id)->update([
                    'name' => 'Erased contact',
                    'email' => "erased-{$member->user_id}@erased.hetkin.invalid",
                    'image' => null,
                    'password' => Str::random(64),
                ]);

                $membersAnonymized++;
            }

            $invitationsRemoved = Invitation::withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $organization->id)
                ->where('status', 'pending')
                ->delete();

            $pilotApplicationErased = false;

            if ($pilotApplication) {
                $pilotApplication->update([
                    'contact_name' => 'Erased contact',
                    'contact_email' => "erased-{$pilotApplication->id}@erased.hetkin.invalid",
                    'contact_phone' => null,
                ]);
                $pilotApplicationErased = true;
            }

            $note = OrganizationNote::create([
                'organization_id' => $organization->id,
                'author_admin_user_id' => $adminUser->id,
                'category' => OrganizationNoteCategory::General,
                'pinned' => true,
                'body' => sprintf(
                    'GDPR erasure processed. %d member account(s) anonymized, %d skipped '.
                    '(shared across orgs), %d pending invitation(s) removed, pilot application '.
                    'contact %s. Operational/financial records retained per the statutory-retention '.
                    'basis in README.md.',
                    $membersAnonymized,
                    $membersSkipped,
                    $invitationsRemoved,
                    $pilotApplicationErased ? 'erased' : 'n/a',
                ),
            ]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'organization.gdpr_erasure_processed',
                entityType: 'Organization',
                entityId: $organization->id,
                organizationId: $organization->id,
                metadata: [
                    'membersAnonymized' => $membersAnonymized,
                    'membersSkipped' => $membersSkipped,
                    'invitationsRemoved' => $invitationsRemoved,
                    'pilotApplicationErased' => $pilotApplicationErased,
                    'noteId' => $note->id,
                ],
            ));

            return $note;
        });
    }
}
