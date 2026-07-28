<?php

use App\Domain\Pricing\Actions\BackfillPricesAction;
use App\Domain\Pricing\DTOs\BackfillChunkProgress;
use App\Domain\Pricing\DTOs\PricePoint;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Exceptions\EntsoeFetchException;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Domain\Pricing\Providers\EntsoePriceProvider;
use Illuminate\Support\Carbon;

test('throws when endDate is not after startDate', function () {
    $start = Carbon::parse('2026-01-05', 'UTC');
    $end = Carbon::parse('2026-01-01', 'UTC');

    expect(fn () => app(BackfillPricesAction::class)->execute(PriceSource::Entsoe, $start, $end))
        ->toThrow(InvalidArgumentException::class, 'endDate must be after startDate.');
});

test('skips a chunk already covered by at least 20 rows/day, without calling the provider', function () {
    $day = Carbon::parse('2026-01-01', 'UTC');
    ElectricityPrice::factory()->count(24)->sequence(fn ($sequence) => [
        'hour_start' => $day->copy()->addHours($sequence->index),
    ])->create(['bidding_zone' => BiddingZone::FI, 'source' => PriceSource::Entsoe]);

    $this->mock(EntsoePriceProvider::class, function ($mock) {
        $mock->shouldReceive('fetchPrices')->never();
        $mock->shouldReceive('source')->andReturn(PriceSource::Entsoe);
        $mock->shouldReceive('biddingZone')->andReturn(BiddingZone::FI);
    });

    $result = app(BackfillPricesAction::class)->execute(PriceSource::Entsoe, $day, $day->copy()->addDay());

    expect($result['chunksSkipped'])->toBe(1);
    expect($result['chunksWritten'])->toBe(0);
    expect($result['rowsWritten'])->toBe(0);
});

test('fetches and upserts a chunk not already covered, and reports rowsWritten', function () {
    $day = Carbon::parse('2026-01-01', 'UTC');
    $this->mock(EntsoePriceProvider::class, function ($mock) use ($day) {
        $mock->shouldReceive('fetchPrices')->once()->andReturn([
            new PricePoint($day->copy(), 45.5),
            new PricePoint($day->copy()->addHour(), 52.1),
        ]);
        $mock->shouldReceive('source')->andReturn(PriceSource::Entsoe);
        $mock->shouldReceive('biddingZone')->andReturn(BiddingZone::FI);
    });

    $result = app(BackfillPricesAction::class)->execute(PriceSource::Entsoe, $day, $day->copy()->addDay());

    expect($result['chunksWritten'])->toBe(1);
    expect($result['chunksSkipped'])->toBe(0);
    expect($result['rowsWritten'])->toBe(2);
    expect(ElectricityPrice::query()->count())->toBe(2);
});

test('splits a multi-chunk range and reports the correct chunk count', function () {
    $start = Carbon::parse('2026-01-01', 'UTC');
    $end = $start->copy()->addDays(65);
    $this->mock(EntsoePriceProvider::class, function ($mock) {
        $mock->shouldReceive('fetchPrices')->times(3)->andReturn([]);
        $mock->shouldReceive('source')->andReturn(PriceSource::Entsoe);
        $mock->shouldReceive('biddingZone')->andReturn(BiddingZone::FI);
    });

    $result = app(BackfillPricesAction::class)->execute(PriceSource::Entsoe, $start, $end, chunkDays: 30);

    expect($result['chunkCount'])->toBe(3);
    expect($result['chunksWritten'])->toBe(3);
});

test('a non-retryable "not configured" failure fails the chunk without retrying', function () {
    $day = Carbon::parse('2026-01-01', 'UTC');
    $this->mock(EntsoePriceProvider::class, function ($mock) {
        $mock->shouldReceive('fetchPrices')->once()->andThrow(new EntsoeFetchException('ENTSO_E_TOKEN not configured'));
        $mock->shouldReceive('source')->andReturn(PriceSource::Entsoe);
        $mock->shouldReceive('biddingZone')->andReturn(BiddingZone::FI);
    });

    $result = app(BackfillPricesAction::class)->execute(PriceSource::Entsoe, $day, $day->copy()->addDay(), maxRetries: 3);

    expect($result['chunksFailed'])->toBe(1);
    expect($result['failedRanges'])->toHaveCount(1);
    expect($result['failedRanges'][0]['error'])->toBe('ENTSO_E_TOKEN not configured');
});

test('a retryable failure that never recovers still fails the chunk instead of aborting the run', function () {
    $day = Carbon::parse('2026-01-01', 'UTC');
    $this->mock(EntsoePriceProvider::class, function ($mock) {
        $mock->shouldReceive('fetchPrices')->once()->andThrow(new RuntimeException('upstream hiccup'));
        $mock->shouldReceive('source')->andReturn(PriceSource::Entsoe);
        $mock->shouldReceive('biddingZone')->andReturn(BiddingZone::FI);
    });

    $result = app(BackfillPricesAction::class)->execute(PriceSource::Entsoe, $day, $day->copy()->addDay(), maxRetries: 0);

    expect($result['chunksFailed'])->toBe(1);
    expect($result['failedRanges'][0]['error'])->toBe('upstream hiccup');
});

test('invokes onProgress once per chunk with the outcome', function () {
    $day = Carbon::parse('2026-01-01', 'UTC');
    $this->mock(EntsoePriceProvider::class, function ($mock) use ($day) {
        $mock->shouldReceive('fetchPrices')->once()->andReturn([new PricePoint($day->copy(), 45.5)]);
        $mock->shouldReceive('source')->andReturn(PriceSource::Entsoe);
        $mock->shouldReceive('biddingZone')->andReturn(BiddingZone::FI);
    });

    $progressEvents = [];
    app(BackfillPricesAction::class)->execute(
        PriceSource::Entsoe,
        $day,
        $day->copy()->addDay(),
        onProgress: function (BackfillChunkProgress $progress) use (&$progressEvents) {
            $progressEvents[] = $progress;
        },
    );

    expect($progressEvents)->toHaveCount(1);
    expect($progressEvents[0]->outcome)->toBe('written');
    expect($progressEvents[0]->chunkIndex)->toBe(0);
    expect($progressEvents[0]->chunkCount)->toBe(1);
    expect($progressEvents[0]->rowsWritten)->toBe(1);
});
