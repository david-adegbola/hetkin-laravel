<?php

namespace App\Domain\Alerts\Models;

use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertType;
use App\Domain\Organizations\Concerns\BelongsToOrganization;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Mirrors the Next.js app's `Alert` Prisma model. Has a direct
 * `organization_id` and is in v2's RLS policy list, so it gets
 * BelongsToOrganization like any other tenant-owned row.
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable(['organization_id', 'type', 'severity', 'title', 'body', 'read_at'])]
class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    public $timestamps = false;

    /** See App\Domain\Organizations\Models\Organization::newFactory()'s docblock. */
    protected static function newFactory(): AlertFactory
    {
        return AlertFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'severity' => AlertSeverity::class,
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
