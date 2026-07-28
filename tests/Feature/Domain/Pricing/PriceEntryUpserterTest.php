<?php

use App\Domain\Pricing\DTOs\PricePoint;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Domain\Pricing\Services\PriceEntryUpserter;
use Illuminate\Support\Carbon;

test('inserts new rows', function () {
    $entries = [
        new PricePoint(Carbon::parse('2026-01-01 00:00', 'UTC'), 45.5),
        new PricePoint(Carbon::parse('2026-01-01 01:00', 'UTC'), 52.1),
    ];

    $rowsWritten = app(PriceEntryUpserter::class)->upsert(BiddingZone::FI, PriceSource::Entsoe, $entries);

    expect($rowsWritten)->toBe(2);
    expect(ElectricityPrice::query()->count())->toBe(2);
});

test('updates eur_per_mwh on conflict without touching created_at', function () {
    $hourStart = Carbon::parse('2026-01-01 00:00', 'UTC');
    $existing = ElectricityPrice::factory()->create([
        'bidding_zone' => BiddingZone::FI,
        'hour_start' => $hourStart,
        'eur_per_mwh' => 10.0,
        'source' => PriceSource::Entsoe,
        'created_at' => Carbon::parse('2020-01-01'),
    ]);

    app(PriceEntryUpserter::class)->upsert(BiddingZone::FI, PriceSource::Entsoe, [
        new PricePoint($hourStart, 99.9),
    ]);

    expect(ElectricityPrice::query()->count())->toBe(1);
    $updated = ElectricityPrice::query()->first();
    expect((float) $updated->eur_per_mwh)->toBe(99.9);
    expect($updated->created_at->isSameDay(Carbon::parse('2020-01-01')))->toBeTrue();
    expect($updated->id)->toBe($existing->id);
});

test('a different bidding zone or source does not collide', function () {
    $hourStart = Carbon::parse('2026-01-01 00:00', 'UTC');
    app(PriceEntryUpserter::class)->upsert(BiddingZone::FI, PriceSource::Entsoe, [
        new PricePoint($hourStart, 10.0),
    ]);
    app(PriceEntryUpserter::class)->upsert(BiddingZone::FI, PriceSource::Nordpool, [
        new PricePoint($hourStart, 20.0),
    ]);

    expect(ElectricityPrice::query()->count())->toBe(2);
});
