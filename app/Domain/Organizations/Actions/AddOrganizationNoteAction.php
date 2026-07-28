<?php

namespace App\Domain\Organizations\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Organizations\Enums\OrganizationNoteCategory;
use App\Domain\Organizations\Exceptions\OrganizationActionException;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationNote;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's addOrganizationNote() Server Action exactly
 * (src/app/admin/(protected)/organizations/[id]/actions.ts). Deliberately
 * NOT role-gated — any authenticated, active admin can leave a note (the
 * route uses plain `auth:admin`, no `admin.role:` middleware), matching
 * v2's own comment that support/internal notes should have the lowest
 * possible friction.
 *
 * `category` is cast to the enum with no allow-list validation, matching
 * v2 exactly: the source only validates `body` (non-empty after trim), and
 * passes `category` straight through to Prisma, which would throw a raw
 * DB-layer error for an invalid value rather than a friendly message. This
 * port intentionally doesn't add stricter validation than v2 actually has
 * — `OrganizationNoteCategory::from()` below throws `\ValueError` the same
 * "not a friendly message" way.
 */
class AddOrganizationNoteAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @throws OrganizationActionException
     */
    public function execute(
        Organization $organization,
        AdminUser $adminUser,
        string $body,
        string $category = 'GENERAL',
        bool $pinned = false,
    ): OrganizationNote {
        $body = trim($body);

        if ($body === '') {
            throw new OrganizationActionException('Note cannot be empty.');
        }

        $categoryEnum = OrganizationNoteCategory::from($category);

        return DB::transaction(function () use ($organization, $adminUser, $body, $categoryEnum, $pinned) {
            $note = OrganizationNote::create([
                'organization_id' => $organization->id,
                'author_admin_user_id' => $adminUser->id,
                'category' => $categoryEnum,
                'body' => $body,
                'pinned' => $pinned,
            ]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'organization_note.added',
                entityType: 'OrganizationNote',
                entityId: $note->id,
                organizationId: $organization->id,
                metadata: ['category' => $categoryEnum->value, 'pinned' => $pinned],
            ));

            return $note;
        });
    }
}
