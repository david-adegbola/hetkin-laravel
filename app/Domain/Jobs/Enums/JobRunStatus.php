<?php

namespace App\Domain\Jobs\Enums;

/** Mirrors the Next.js app's `JobRunStatus` Prisma enum exactly. */
enum JobRunStatus: string
{
    case Running = 'RUNNING';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
}
