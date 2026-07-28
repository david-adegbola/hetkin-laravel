<?php

namespace App\Domain\Organizations\Models;

use App\Domain\Organizations\Concerns\BelongsToOrganization;
use App\Domain\Organizations\Enums\OrganizationNoteCategory;
use App\Models\AdminUser;
use Database\Factories\OrganizationNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mirrors the Next.js app's `OrganizationNote` Prisma model. Has a direct
 * `organization_id` and is in v2's RLS policy list, so it gets
 * BelongsToOrganization.
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable(['organization_id', 'author_admin_user_id', 'category', 'body', 'pinned'])]
class OrganizationNote extends Model
{
    /** @use HasFactory<OrganizationNoteFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    public $timestamps = false;

    /** See App\Domain\Organizations\Models\Organization::newFactory()'s docblock. */
    protected static function newFactory(): OrganizationNoteFactory
    {
        return OrganizationNoteFactory::new();
    }

    protected function casts(): array
    {
        return [
            'category' => OrganizationNoteCategory::class,
            'pinned' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'author_admin_user_id');
    }
}
