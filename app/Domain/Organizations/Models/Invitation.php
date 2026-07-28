<?php

namespace App\Domain\Organizations\Models;

use App\Domain\Organizations\Concerns\BelongsToOrganization;
use App\Domain\Organizations\Enums\OrgRole;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Mirrors the Next.js app's `Invitation` Prisma model. `invited_by_user_id`
 * is a plain attribute, not an Eloquent relation — see the creating
 * migration's comment: the original Prisma schema never declared a
 * relation for that column either (no `@relation`, so Prisma's own
 * generated client has no `invitedByUser` accessor), so this doesn't add
 * one either.
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable([
    'organization_id', 'email', 'role', 'status',
    'invited_by_user_id', 'expires_at',
])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    public $timestamps = false;

    /** See Organization::newFactory()'s docblock for why this is explicit. */
    protected static function newFactory(): InvitationFactory
    {
        return InvitationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'role' => OrgRole::class,
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
