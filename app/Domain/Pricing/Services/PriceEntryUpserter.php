<?php

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\DTOs\PricePoint;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ports the Next.js app's upsertPriceEntries() exactly (src/lib/prices/
 * upsert.ts). Batched upsert on the `(bidding_zone, hour_start, source)`
 * unique constraint (matches the creating migration's own `$table->unique(
 * [...])`) — on conflict, only `eur_per_mwh` is updated, `created_at`
 * (the original insert time) is left untouched, matching v2's `DO UPDATE
 * SET "eurPerMwh" = EXCLUDED."eurPerMwh"` exactly.
 */
class PriceEntryUpserter
{
    /** Matches v2's UPSERT_BATCH_SIZE exactly. */
    private const BATCH_SIZE = 2000;

    /**
     * @param  list<PricePoint>  $entries
     * @return int Rows in the input, not a distinct insert-vs-update count
     *             (same caveat v2's own return value carries — Postgres
     *             doesn't report that back through a batched upsert).
     */
    public function upsert(BiddingZone $biddingZone, PriceSource $source, array $entries): int
    {
        foreach (array_chunk($entries, self::BATCH_SIZE) as $chunk) {
            $rows = array_map(fn (PricePoint $point) => [
                'id' => (string) Str::ulid(),
                'bidding_zone' => $biddingZone->value,
                'hour_start' => $point->hourStart,
                'eur_per_mwh' => $point->eurPerMwh,
                'source' => $source->value,
                'created_at' => now(),
            ], $chunk);

            DB::table('electricity_prices')->upsert(
                $rows,
                ['bidding_zone', 'hour_start', 'source'],
                ['eur_per_mwh'],
            );
        }

        return count($entries);
    }
}
