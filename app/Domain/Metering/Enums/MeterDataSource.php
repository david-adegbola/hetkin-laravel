<?php

namespace App\Domain\Metering\Enums;

/** Mirrors the Next.js app's `MeterDataSource` Prisma enum exactly. */
enum MeterDataSource: string
{
    case Datahub = 'DATAHUB';
    case ManualUpload = 'MANUAL_UPLOAD';
}
