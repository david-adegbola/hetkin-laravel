<?php

namespace App\Domain\Pricing\DTOs;

use Illuminate\Support\Carbon;

/**
 * Ports the Next.js app's ChunkProgress interface exactly (src/lib/jobs/
 * backfill-prices.ts) — passed to App\Domain\Pricing\Actions\
 * BackfillPricesAction's optional onProgress callback after each chunk.
 */
final class BackfillChunkProgress
{
    /**
     * @param  'skipped-existing'|'written'|'failed'  $outcome
     */
    public function __construct(
        public readonly int $chunkIndex,
        public readonly int $chunkCount,
        public readonly Carbon $rangeStart,
        public readonly Carbon $rangeEnd,
        public readonly string $outcome,
        public readonly ?int $rowsWritten = null,
        public readonly ?string $error = null,
    ) {}
}
