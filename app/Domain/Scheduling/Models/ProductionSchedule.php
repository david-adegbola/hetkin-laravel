<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Organizations\Concerns\BelongsToOrganization;
use App\Domain\Scheduling\Enums\ScheduleStatus;
use App\Models\User;
use Database\Factories\ProductionScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mirrors the Next.js app's `ProductionSchedule` Prisma model — versioned,
 * never overwritten. No `updated_at` — see the creating migration's
 * comment.
 */
#[Fillable(['organization_id', 'version', 'status', 'submitted_by_id'])]
class ProductionSchedule extends Model
{
    /** @use HasFactory<ProductionScheduleFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    public $timestamps = false;

    /** See App\Domain\Organizations\Models\Organization::newFactory()'s docblock. */
    protected static function newFactory(): ProductionScheduleFactory
    {
        return ProductionScheduleFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => ScheduleStatus::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    /**
     * @return HasMany<ScheduleBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(ScheduleBlock::class, 'schedule_id');
    }
}
