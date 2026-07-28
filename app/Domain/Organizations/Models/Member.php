<?php

namespace App\Domain\Organizations\Models;

use App\Domain\Organizations\Concerns\BelongsToOrganization;
use App\Domain\Organizations\Enums\OrgRole;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Models\User;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mirrors the Next.js app's `Member` Prisma model — the organization/user
 * pivot carrying a role, not a plain many-to-many. Scoped by
 * BelongsToOrganization like any other tenant-owned row; the one
 * legitimate exception is App\Domain\Organizations\Services\
 * OrganizationContextResolver's own bootstrap query (which organization
 * does this user belong to, before any organization is known to scope
 * by) — that query explicitly opts out via
 * `Member::withoutGlobalScope(OrganizationScope::class)`.
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable(['organization_id', 'user_id', 'role'])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    public $timestamps = false;

    /** See Organization::newFactory()'s docblock for why this is explicit. */
    protected static function newFactory(): MemberFactory
    {
        return MemberFactory::new();
    }

    protected function casts(): array
    {
        return [
            'role' => OrgRole::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The membership check every Organization/Member/Invitation Policy in
     * this domain is built on — deliberately unscoped (a membership check
     * is exactly the kind of query that has to run before any
     * CurrentOrganization can be considered "current").
     */
    public static function isMemberOf(User $user, string $organizationId): bool
    {
        return static::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->exists();
    }
}
