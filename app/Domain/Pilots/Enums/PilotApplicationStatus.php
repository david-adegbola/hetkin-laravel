<?php

namespace App\Domain\Pilots\Enums;

/**
 * Mirrors the Next.js app's `PilotApplicationStatus` Prisma enum exactly.
 * Only `Pending` → `Approved`/`Rejected` transitions actually happen
 * anywhere in v2's code — `InReview` and `Nurture` are carried forward for
 * schema fidelity (and `InReview` is grouped with `Pending` in the "still
 * open" bucket the admin queue shows) but nothing ever transitions an
 * application into either state. See MIGRATION_PLAN.md's Stage 8 ADR.
 */
enum PilotApplicationStatus: string
{
    case Pending = 'PENDING';
    case InReview = 'IN_REVIEW';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Nurture = 'NURTURE';
}
