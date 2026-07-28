<?php

namespace App\Models;

use App\Domain\Auth\Models\SessionRecord;
use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Notifications\PasswordResetRequested;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;

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
 *
 * `password_changed_at` and `sessions()` back the Account Security module
 * (MIGRATION_PLAN.md ADR-13) — "last password change" display and the
 * active-sessions list respectively.
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
            'password_changed_at' => 'datetime',
        ];
    }

    /**
     * Laravel's own session table, scoped to this user — the data source
     * for the Security settings page's active-sessions list. Only
     * meaningful with SESSION_DRIVER=database (this app's default).
     *
     * @return HasMany<SessionRecord, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(SessionRecord::class, 'user_id');
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * The organizations this user belongs to, via the Member pivot —
     * mirrors the Next.js app's `member Member[]` relation on `User`
     * combined with each membership's own `organization` relation.
     *
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'members')
            ->using(Member::class)
            ->withPivot('role');
    }

    /**
     * Overrides Illuminate\Auth\Passwords\CanResetPassword's default, which
     * sends Laravel's stock Illuminate\Auth\Notifications\ResetPassword —
     * built around a Blade "reset-password/{token}" URL that doesn't exist
     * in an Inertia app. Same Password Broker, same token, just our own
     * queued notification and an Inertia-route reset URL. This method is
     * the framework's own designated extension point for this (called by
     * Password::sendResetLink() via $user->sendPasswordResetNotification()),
     * not a workaround.
     */
    public function sendPasswordResetNotification($token): void
    {
        $url = URL::route('password.reset', ['token' => $token, 'email' => $this->email]);

        $this->notify(new PasswordResetRequested($url));
    }
}
