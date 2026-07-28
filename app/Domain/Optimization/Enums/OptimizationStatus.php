<?php

namespace App\Domain\Optimization\Enums;

/** Mirrors the Next.js app's `OptimizationStatus` Prisma enum exactly. */
enum OptimizationStatus: string
{
    case Pending = 'PENDING';
    case Running = 'RUNNING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
}
