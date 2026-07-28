<?php

namespace App\Domain\Billing\Enums;

/**
 * Mirrors the Next.js app's `SavingsAssessmentStatus` Prisma enum exactly.
 * `Delivered`/`Accepted` are carried forward for schema fidelity even
 * though no code path in v2 ever transitions an assessment to either state
 * — every assessment `generateSavingsAssessment` creates stays `Draft`
 * forever. See MIGRATION_PLAN.md's Stage 7 ADR for why this dead state is
 * ported as-is rather than "fixed."
 */
enum SavingsAssessmentStatus: string
{
    case Draft = 'DRAFT';
    case Delivered = 'DELIVERED';
    case Accepted = 'ACCEPTED';
}
