<?php

namespace App\Domain\Pricing\Providers;

use App\Domain\Pricing\Contracts\PriceProvider;
use App\Domain\Pricing\DTOs\PricePoint;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Services\EntsoeClient;
use Illuminate\Support\Carbon;

/** Mirrors the Next.js app's `EntsoePriceProvider` class (src/lib/prices/providers/entsoe.ts). */
class EntsoePriceProvider implements PriceProvider
{
    public function __construct(
        private readonly EntsoeClient $client,
    ) {}

    public function source(): PriceSource
    {
        return PriceSource::Entsoe;
    }

    public function biddingZone(): BiddingZone
    {
        return BiddingZone::FI;
    }

    public function fetchPrices(Carbon $start, Carbon $end): array
    {
        $entries = $this->client->fetchPrices($start, $end);

        return array_map(
            // Defensive against both epoch-seconds and epoch-millis
            // inputs, matching v2's own `entry.timestamp > 1e12 ? ... :
            // ... * 1000` guard — EntsoeClient::parseXml() always
            // produces seconds today, but this keeps the same contract
            // v2's provider documents at this boundary.
            fn (array $entry) => new PricePoint(
                hourStart: Carbon::createFromTimestamp(
                    $entry['timestamp'] > 1_000_000_000_000 ? intdiv((int) $entry['timestamp'], 1000) : $entry['timestamp'],
                    'UTC',
                ),
                eurPerMwh: $entry['price'],
            ),
            $entries,
        );
    }
}
