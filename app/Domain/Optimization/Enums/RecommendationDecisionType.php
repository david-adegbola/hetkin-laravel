<?php

namespace App\Domain\Optimization\Enums;

/** Mirrors the Next.js app's `RecommendationDecisionType` Prisma enum exactly. */
enum RecommendationDecisionType: string
{
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
    case Partial = 'PARTIAL';
}
