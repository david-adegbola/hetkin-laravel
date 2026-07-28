<?php

namespace App\Domain\Organizations\Enums;

/** Mirrors the Next.js app's `OrganizationNoteCategory` Prisma enum exactly. */
enum OrganizationNoteCategory: string
{
    case General = 'GENERAL';
    case Support = 'SUPPORT';
    case Risk = 'RISK';
    case Billing = 'BILLING';
}
