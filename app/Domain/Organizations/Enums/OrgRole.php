<?php

namespace App\Domain\Organizations\Enums;

/**
 * Customer-side role. Mirrors the Next.js app's `OrgRole` Prisma enum
 * exactly (prisma/schema.prisma) — display-only today (the portal "team"
 * page badge in a later stage): v2 itself has no code path that gates a
 * portal action by role, only by organization membership, and this port
 * doesn't invent one either (MIGRATION_PLAN.md ADR-25).
 */
enum OrgRole: string
{
    case Owner = 'OWNER';
    case Scheduler = 'SCHEDULER';
    case Finance = 'FINANCE';
    case Viewer = 'VIEWER';
}
