<?php

namespace App\Domain\Billing\Enums;

/**
 * Mirrors the Next.js app's `VerificationReviewStatus` Prisma enum exactly.
 * This is the state machine that enforces "verification runs in shadow
 * mode before it can affect billing" — see
 * App\Domain\Billing\Actions\ConfirmShadowReviewAction /
 * ApproveLiveAction / DisputeRecordAction / ReopenDisputedAction for the
 * only legal transitions between these values.
 */
enum VerificationReviewStatus: string
{
    case ShadowPendingReview = 'SHADOW_PENDING_REVIEW';
    case ShadowConfirmed = 'SHADOW_CONFIRMED';
    case LiveApproved = 'LIVE_APPROVED';
    case Disputed = 'DISPUTED';
}
