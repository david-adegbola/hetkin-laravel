<?php

use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Providers\EntsoePriceProvider;
use App\Domain\Pricing\Services\PriceProviderRegistry;

test('resolves the ENTSO-E provider', function () {
    $provider = app(PriceProviderRegistry::class)->get(PriceSource::Entsoe);

    expect($provider)->toBeInstanceOf(EntsoePriceProvider::class);
});

test('throws for Nord Pool, not implemented yet', function () {
    expect(fn () => app(PriceProviderRegistry::class)->get(PriceSource::Nordpool))
        ->toThrow(RuntimeException::class, 'Nord Pool price provider is not implemented yet');
});
