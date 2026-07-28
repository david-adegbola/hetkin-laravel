<?php

namespace App\Domain\Alerts\Enums;

/**
 * Mirrors the Next.js app's `AlertType` Prisma enum exactly, including
 * `ADOPTION_TREND` — a real enum case that v2 deliberately never produces
 * anywhere (src/lib/alerts.ts's own doc comment: every other type reports
 * something that already happened; "your team isn't following our
 * recommendations enough" needs real usage data a single design partner
 * doesn't have yet). Kept for schema fidelity, not wired to any emitter
 * here either — same treatment as this port's other honest-subset dead
 * enum cases (e.g. PilotApplicationStatus::InReview/Nurture).
 */
enum AlertType: string
{
    case DataGap = 'DATA_GAP';
    case NewOpportunity = 'NEW_OPPORTUNITY';
    case AdoptionTrend = 'ADOPTION_TREND';
    case VarianceFlag = 'VARIANCE_FLAG';
    case ReportReady = 'REPORT_READY';
}
