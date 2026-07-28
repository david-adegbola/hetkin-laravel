<?php

namespace App\Domain\Organizations\Enums;

/** Mirrors the Next.js app's `OrganizationStatus` Prisma enum exactly. */
enum OrganizationStatus: string
{
    case Pilot = 'PILOT';
    case Active = 'ACTIVE';
    case Paused = 'PAUSED';
    case Churned = 'CHURNED';
}
