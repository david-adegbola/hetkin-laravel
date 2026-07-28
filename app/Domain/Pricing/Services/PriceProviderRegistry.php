<?php

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\Contracts\PriceProvider;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Providers\EntsoePriceProvider;
use RuntimeException;

/** Mirrors the Next.js app's getPriceProvider() (src/lib/prices/providers/index.ts). */
class PriceProviderRegistry
{
    public function __construct(
        private readonly EntsoePriceProvider $entsoeProvider,
    ) {}

    public function get(PriceSource $source): PriceProvider
    {
        return match ($source) {
            PriceSource::Entsoe => $this->entsoeProvider,
            PriceSource::Nordpool => throw new RuntimeException(
                'Nord Pool price provider is not implemented yet (PriceSource::Nordpool is reserved for it).',
            ),
        };
    }
}
