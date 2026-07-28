<?php

namespace App\Domain\Metering\Models;

use App\Domain\Organizations\Concerns\BelongsToOrganization;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mirrors the Next.js app's `Site` Prisma model. The one metering-domain
 * table with a direct `organization_id` column, so it's the one that gets
 * BelongsToOrganization — see MeteringPoint/MeterReading's docblocks for
 * why they don't.
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable(['organization_id', 'name', 'address'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    public $timestamps = false;

    /** See App\Domain\Organizations\Models\Organization::newFactory()'s docblock. */
    protected static function newFactory(): SiteFactory
    {
        return SiteFactory::new();
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MeteringPoint, $this>
     */
    public function meteringPoints(): HasMany
    {
        return $this->hasMany(MeteringPoint::class);
    }
}
