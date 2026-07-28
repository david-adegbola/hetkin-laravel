<?php

namespace App\Domain\Pricing\Enums;

/** Mirrors the Next.js app's `PriceSource` Prisma enum exactly. */
enum PriceSource: string
{
    case Entsoe = 'ENTSOE';
    case Nordpool = 'NORDPOOL';
}
