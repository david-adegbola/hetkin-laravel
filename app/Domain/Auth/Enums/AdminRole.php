<?php

namespace App\Domain\Auth\Enums;

/**
 * Mirrors the Next.js app's `AdminRole` Prisma enum exactly (see
 * prisma/schema.prisma and src/lib/admin-session.ts's role-mapping comment
 * for what each role can do — that authorization mapping itself lands in
 * the Organizations/Admin-console stage, once there's anything to
 * authorize against; this enum only carries the value set forward).
 */
enum AdminRole: string
{
    case Admin = 'ADMIN';
    case VerificationAnalyst = 'VERIFICATION_ANALYST';
    case Support = 'SUPPORT';
    case Auditor = 'AUDITOR';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::VerificationAnalyst => 'Verification Analyst',
            self::Support => 'Support',
            self::Auditor => 'Auditor',
        };
    }
}
