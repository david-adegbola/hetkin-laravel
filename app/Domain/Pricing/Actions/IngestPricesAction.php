<?php

namespace App\Domain\Pricing\Actions;

use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Exceptions\EntsoeFetchException;
use App\Domain\Pricing\Services\PriceEntryUpserter;
use App\Domain\Pricing\Services\PriceProviderRegistry;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Ports the Next.js app's runIngestPricesJob() exactly (src/lib/jobs/
 * ingest-prices.ts) — the price ingestion job body (hetkin-phase5-roadmap.md
 * M2). Only ever covers "today + tomorrow" going forward in UTC; the
 * historical backfill (App\Domain\Pricing\Actions\BackfillPricesAction,
 * Stage 10 task #90) fills in everything before whenever this job first
 * started running.
 *
 * Runs through the same App\Domain\Pricing\Contracts\PriceProvider
 * abstraction as the backfill rather than calling EntsoeClient directly.
 * Wrapped in App\Domain\Jobs\Services\JobRunTracker by both callers (a
 * future scheduled command and App\Domain\Jobs\Actions\
 * TriggerIngestPricesAction), same as v2's withJobRunTracking().
 */
class IngestPricesAction
{
    public function __construct(
        private readonly PriceProviderRegistry $providerRegistry,
        private readonly PriceEntryUpserter $upserter,
    ) {}

    /**
     * @return array{entriesFetched: int, rowsWritten: int}
     */
    public function execute(): array
    {
        $provider = $this->providerRegistry->get(PriceSource::Entsoe);

        $start = Carbon::now('UTC')->startOfDay();
        $end = $start->copy()->addDays(2);

        try {
            $entries = $provider->fetchPrices($start, $end);
        } catch (EntsoeFetchException $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        } catch (\Throwable $e) {
            throw new RuntimeException('Unexpected ingestion error', previous: $e);
        }

        $rowsWritten = $this->upserter->upsert($provider->biddingZone(), $provider->source(), $entries);

        return [
            'entriesFetched' => count($entries),
            'rowsWritten' => $rowsWritten,
        ];
    }
}
