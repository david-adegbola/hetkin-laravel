<?php

use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Providers\EntsoePriceProvider;
use App\Domain\Pricing\Services\EntsoeClient;
use Illuminate\Support\Carbon;

test('reports ENTSOE as its source and FI as its bidding zone', function () {
    $provider = app(EntsoePriceProvider::class);

    expect($provider->source())->toBe(PriceSource::Entsoe);
    expect($provider->biddingZone())->toBe(BiddingZone::FI);
});

test('maps the client\'s raw entries into PricePoint DTOs', function () {
    $this->mock(EntsoeClient::class, function ($mock) {
        $mock->shouldReceive('fetchPrices')->once()->andReturn([
            ['timestamp' => 1_767_225_600, 'price' => 45.5],
        ]);
    });

    $provider = app(EntsoePriceProvider::class);
    $points = $provider->fetchPrices(Carbon::parse('2026-01-01', 'UTC'), Carbon::parse('2026-01-02', 'UTC'));

    expect($points)->toHaveCount(1);
    expect($points[0]->eurPerMwh)->toBe(45.5);
    expect($points[0]->hourStart->getTimestamp())->toBe(1_767_225_600);
});

test('treats a millisecond-scale timestamp defensively, matching v2\'s own guard', function () {
    $this->mock(EntsoeClient::class, function ($mock) {
        $mock->shouldReceive('fetchPrices')->once()->andReturn([
            ['timestamp' => 1_767_225_600_000, 'price' => 45.5],
        ]);
    });

    $provider = app(EntsoePriceProvider::class);
    $points = $provider->fetchPrices(Carbon::parse('2026-01-01', 'UTC'), Carbon::parse('2026-01-02', 'UTC'));

    expect($points[0]->hourStart->getTimestamp())->toBe(1_767_225_600);
});
