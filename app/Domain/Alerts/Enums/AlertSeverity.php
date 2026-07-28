<?php

namespace App\Domain\Alerts\Enums;

/** Mirrors the Next.js app's `AlertSeverity` Prisma enum exactly. */
enum AlertSeverity: string
{
    case Info = 'INFO';
    case Opportunity = 'OPPORTUNITY';
    case Warning = 'WARNING';
}
