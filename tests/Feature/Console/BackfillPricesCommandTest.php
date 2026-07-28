<?php

use App\Domain\Pricing\Actions\BackfillPricesAction;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Providers\EntsoePriceProvider;

test('requires --start', function () {
    $this->artisan('prices:backfill', ['--end' => '2026-01-02'])
        ->assertExitCode(1)
        ->expectsOutputToContain('--start is required');
});

test('requires --end', function () {
    $this->artisan('prices:backfill', ['--start' => '2026-01-01'])
        ->assertExitCode(1)
        ->expectsOutputToContain('--end is required');
});

test('rejects an invalid date', function () {
    $this->artisan('prices:backfill', ['--start' => 'not-a-date', '--end' => '2026-01-02'])
        ->assertExitCode(1)
        ->expectsOutputToContain('is not a valid date');
});

test('rejects an unrecognized source', function () {
    $this->artisan('prices:backfill', ['--start' => '2026-01-01', '--end' => '2026-01-02', '--source' => 'BOGUS'])
        ->assertExitCode(1)
        ->expectsOutputToContain('not a recognized price source');
});

test('rejects end not after start', function () {
    $this->artisan('prices:backfill', ['--start' => '2026-01-05', '--end' => '2026-01-01'])
        ->assertExitCode(1)
        ->expectsOutputToContain('--end must be after --start');
});

test('runs the backfill and reports success', function () {
    $this->mock(EntsoePriceProvider::class, function ($mock) {
        $mock->shouldReceive('fetchPrices')->once()->andReturn([]);
        $mock->shouldReceive('source')->andReturn(PriceSource::Entsoe);
        $mock->shouldReceive('biddingZone')->andReturn(BiddingZone::FI);
    });

    $this->artisan('prices:backfill', ['--start' => '2026-01-01', '--end' => '2026-01-02'])
        ->assertExitCode(0)
        ->expectsOutputToContain('Rows written: 0');
});

test('exits non-zero when a chunk fails', function () {
    $this->mock(BackfillPricesAction::class, function ($mock) {
        $mock->shouldReceive('execute')->once()->andReturn([
            'chunkCount' => 1, 'chunksWritten' => 0, 'chunksSkipped' => 0, 'chunksFailed' => 1, 'rowsWritten' => 0,
            'failedRanges' => [['start' => '2026-01-01T00:00:00Z', 'end' => '2026-01-02T00:00:00Z', 'error' => 'boom']],
        ]);
    });

    $this->artisan('prices:backfill', ['--start' => '2026-01-01', '--end' => '2026-01-02'])
        ->assertExitCode(1)
        ->expectsOutputToContain('boom');
});
