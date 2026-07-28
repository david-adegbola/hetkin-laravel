<?php

namespace App\Domain\Optimization\Enums;

/** Mirrors the Next.js app's `OptimizationTrigger` Prisma enum exactly. */
enum OptimizationTrigger: string
{
    case Scheduled = 'SCHEDULED';
    case Manual = 'MANUAL';
    case NewData = 'NEW_DATA';
}
