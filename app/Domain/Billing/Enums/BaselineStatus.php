<?php

namespace App\Domain\Billing\Enums;

/** Mirrors the Next.js app's `BaselineStatus` Prisma enum exactly. */
enum BaselineStatus: string
{
    case Draft = 'DRAFT';
    case Locked = 'LOCKED';
    case Superseded = 'SUPERSEDED';
}
