<?php

namespace App\Domain\Pricing\Contracts;

use App\Domain\Pricing\DTOs\PricePoint;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Exceptions\EntsoeFetchException;
use Illuminate\Support\Carbon;

/** Mirrors the Next.js app's `PriceProvider` interface (src/lib/prices/provider.ts). */
interface PriceProvider
{
    public function source(): PriceSource;

    public function biddingZone(): BiddingZone;

    /**
     * @return list<PricePoint>
     *
     * @throws EntsoeFetchException
     */
    public function fetchPrices(Carbon $start, Carbon $end): array;
}
