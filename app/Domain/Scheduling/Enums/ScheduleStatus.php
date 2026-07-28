<?php

namespace App\Domain\Scheduling\Enums;

/** Mirrors the Next.js app's `ScheduleStatus` Prisma enum exactly. */
enum ScheduleStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Active = 'ACTIVE';
    case Superseded = 'SUPERSEDED';
}
