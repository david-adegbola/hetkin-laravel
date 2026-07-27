<?php

namespace App\Domain\Audit\Enums;

/** Mirrors the Next.js app's `AuditActorType` Prisma enum exactly. */
enum AuditActorType: string
{
    case User = 'USER';
    case AdminUser = 'ADMIN_USER';
    case System = 'SYSTEM';
}
