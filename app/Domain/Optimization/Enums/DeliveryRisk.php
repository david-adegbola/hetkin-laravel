<?php

namespace App\Domain\Optimization\Enums;

/** Mirrors the Next.js app's `DeliveryRisk` Prisma enum exactly. */
enum DeliveryRisk: string
{
    case Low = 'LOW';
    case Medium = 'MEDIUM';
    case High = 'HIGH';
}
