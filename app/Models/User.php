<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Customer realm. Mirrors the Next.js app's Better Auth `User` model
 * (prisma/schema.prisma) — see MIGRATION_PLAN.md ADR-1 for the ULID
 * primary-key decision and ADR-2 for why Better Auth's `Account`/
 * `Verification` tables are not ported.
 *
 * `emailVerified` (Prisma boolean) maps to Laravel's idiomatic
 * `email_verified_at` nullable timestamp — unused for gating today either
 * way (`requireEmailVerification: false` in the original auth config), so
 * this is a safe representation change, not a behavior change.
 */
#[Fillable(['name', 'email', 'password', 'image'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
