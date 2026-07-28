<?php

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\DTOs\BackfillChunkProgress;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Exceptions\EntsoeFetchException;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Domain\Pricing\Services\PriceEntryUpserter;
use App\Domain\Pricing\Services\PriceProviderRegistry;
use Closure;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * Ports the Next.js app's backfillPrices() exactly (src/lib/jobs/
 * backfill-prices.ts) — historical ElectricityPrice backfill for an
 * arbitrary date range, on demand, provider-agnostically. The daily
 * App\Domain\Pricing\Actions\IngestPricesAction only ever fetches "today +
 * tomorrow" going forward, so this fills the gap for a pilot's savings
 * assessment against historical meter data.
 *
 * Called from both the admin "Run backfill" form (bypassing
 * App\Domain\Jobs\Services\JobRunTracker so onProgress can update the
 * JobRun row live — see the admin controller) and the `prices:backfill`
 * Artisan command (no execution-time ceiling, for multi-year ranges).
 *
 * Chunked (default 30 days), idempotent (App\Domain\Pricing\Services\
 * PriceEntryUpserter's upsert), skips already-covered chunks, retries
 * transient failures with backoff but fails fast on a non-retryable
 * config error, and never lets one bad chunk abort the whole run.
 */
class BackfillPricesAction
{
    public const DEFAULT_CHUNK_DAYS = 30;

    public const DEFAULT_MAX_RETRIES = 3;

    private const RETRY_BASE_DELAY_MS = 1000;

    // Any real day-ahead resolution (hourly = 24/day, 15-minute = 96/day)
    // clears this comfortably — a resolution-agnostic way to say "this day
    // is covered" without knowing in advance which resolution a given
    // historical day was published at.
    private const MIN_ROWS_PER_COVERED_DAY = 20;

    public function __construct(
        private readonly PriceProviderRegistry $providerRegistry,
        private readonly PriceEntryUpserter $upserter,
    ) {}

    /**
     * @return array{
     *     source: string, rangeStart: string, rangeEnd: string, chunkCount: int,
     *     chunksSkipped: int, chunksWritten: int, chunksFailed: int, rowsWritten: int,
     *     failedRanges: list<array{start: string, end: string, error: string}>,
     * }
     */
    public function execute(
        PriceSource $source,
        Carbon $startDate,
        Carbon $endDate,
        int $chunkDays = self::DEFAULT_CHUNK_DAYS,
        int $maxRetries = self::DEFAULT_MAX_RETRIES,
        ?Closure $onProgress = null,
    ): array {
        if ($endDate->lte($startDate)) {
            throw new InvalidArgumentException('endDate must be after startDate.');
        }

        $provider = $this->providerRegistry->get($source);
        $chunks = $this->chunkDateRange($startDate, $endDate, $chunkDays);
        $chunkCount = count($chunks);

        $chunksSkipped = 0;
        $chunksWritten = 0;
        $chunksFailed = 0;
        $rowsWritten = 0;
        $failedRanges = [];

        foreach ($chunks as $index => $chunk) {
            [$start, $end] = $chunk;

            if ($this->isChunkAlreadyCovered($provider->biddingZone(), $provider->source(), $start, $end)) {
                $chunksSkipped++;
                $onProgress?->__invoke(new BackfillChunkProgress($index, $chunkCount, $start, $end, 'skipped-existing'));

                continue;
            }

            try {
                $entries = $this->withRetry(fn () => $provider->fetchPrices($start, $end), $maxRetries);
                $written = $this->upserter->upsert($provider->biddingZone(), $provider->source(), $entries);
                $rowsWritten += $written;
                $chunksWritten++;
                $onProgress?->__invoke(new BackfillChunkProgress($index, $chunkCount, $start, $end, 'written', rowsWritten: $written));
            } catch (Throwable $e) {
                $chunksFailed++;
                $failedRanges[] = ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String(), 'error' => $e->getMessage()];
                $onProgress?->__invoke(new BackfillChunkProgress($index, $chunkCount, $start, $end, 'failed', error: $e->getMessage()));
            }
        }

        return [
            'source' => $source->value,
            'rangeStart' => $startDate->toIso8601String(),
            'rangeEnd' => $endDate->toIso8601String(),
            'chunkCount' => $chunkCount,
            'chunksSkipped' => $chunksSkipped,
            'chunksWritten' => $chunksWritten,
            'chunksFailed' => $chunksFailed,
            'rowsWritten' => $rowsWritten,
            'failedRanges' => $failedRanges,
        ];
    }

    /**
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    private function chunkDateRange(Carbon $start, Carbon $end, int $days): array
    {
        $chunks = [];
        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $chunkEnd = $cursor->copy()->addDays($days);
            if ($chunkEnd->gt($end)) {
                $chunkEnd = $end->copy();
            }
            $chunks[] = [$cursor->copy(), $chunkEnd];
            $cursor = $chunkEnd;
        }

        return $chunks;
    }

    private function isChunkAlreadyCovered(BiddingZone $biddingZone, PriceSource $source, Carbon $start, Carbon $end): bool
    {
        $totalDays = (int) ceil($start->diffInHours($end) / 24);

        // Grouped in PHP rather than a raw `date_trunc(...)` query — this
        // stays portable across the Postgres connection this app runs on
        // and the sqlite connection its test suite runs on, and a chunk is
        // small enough (≤30 days, even at 15-minute resolution) that
        // pulling its timestamps into memory to group them is negligible.
        $coveredDays = ElectricityPrice::query()
            ->where('bidding_zone', $biddingZone)
            ->where('source', $source)
            ->where('hour_start', '>=', $start)
            ->where('hour_start', '<', $end)
            ->get(['hour_start'])
            ->countBy(fn (ElectricityPrice $price) => $price->hour_start->toDateString())
            ->filter(fn (int $rowsThatDay) => $rowsThatDay >= self::MIN_ROWS_PER_COVERED_DAY)
            ->count();

        return $coveredDays >= $totalDays;
    }

    /** Config errors (e.g. a missing API token) won't fix themselves on retry. */
    private function isRetryable(Throwable $e): bool
    {
        return ! ($e instanceof EntsoeFetchException && str_contains($e->getMessage(), 'not configured'));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $fn
     * @return T
     */
    private function withRetry(Closure $fn, int $maxRetries)
    {
        $lastException = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                return $fn();
            } catch (Throwable $e) {
                $lastException = $e;
                if (! $this->isRetryable($e) || $attempt === $maxRetries) {
                    throw $e;
                }
                usleep((int) (self::RETRY_BASE_DELAY_MS * (2 ** $attempt) * 1000));
            }
        }

        throw $lastException;
    }
}
