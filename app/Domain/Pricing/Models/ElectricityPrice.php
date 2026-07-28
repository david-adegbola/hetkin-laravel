<?php

namespace App\Domain\Pricing\Models;

use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use Database\Factories\ElectricityPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Mirrors the Next.js app's `ElectricityPrice` Prisma model — the
 * day-ahead price feed the recommendation engine (App\Domain\Optimization)
 * depends on. Global reference data: no BelongsToOrganization, no
 * organization relation at all — every organization reads the same price
 * series for a given bidding zone. Ingestion (ENTSO-E) is Stage 11; this
 * stage only needs the table and model to exist, same as
 * App\Domain\Metering\Models\MeterReading not needing Datahub integration
 * to exist first.
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable(['bidding_zone', 'hour_start', 'eur_per_mwh', 'source'])]
class ElectricityPrice extends Model
{
    /** @use HasFactory<ElectricityPriceFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    /** See App\Domain\Organizations\Models\Organization::newFactory()'s docblock. */
    protected static function newFactory(): ElectricityPriceFactory
    {
        return ElectricityPriceFactory::new();
    }

    protected function casts(): array
    {
        return [
            'bidding_zone' => BiddingZone::class,
            'hour_start' => 'datetime',
            'eur_per_mwh' => 'decimal:2',
            'source' => PriceSource::class,
            'created_at' => 'datetime',
        ];
    }
}
