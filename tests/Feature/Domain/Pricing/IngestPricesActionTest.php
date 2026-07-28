<?php

use App\Domain\Pricing\Actions\IngestPricesAction;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Domain\Pricing\Providers\EntsoePriceProvider;
use Illuminate\Support\Facades\Http;

function ingestPricesXmlFixture(): string
{
    return <<<'XML'
    <GL_MarketDocument>
        <TimeSeries>
            <Period>
                <start>2026-01-01T00:00Z</start>
                <resolution>PT60M</resolution>
                <Point><position>1</position><price.amount>45.50</price.amount></Point>
                <Point><position>2</position><price.amount>52.10</price.amount></Point>
            </Period>
        </TimeSeries>
    </GL_MarketDocument>
    XML;
}

test('fetches today+tomorrow and upserts the entries', function () {
    config(['services.entsoe.token' => 'test-token']);
    Http::fake(['web-api.tp.entsoe.eu/*' => Http::response(ingestPricesXmlFixture(), 200)]);

    $result = app(IngestPricesAction::class)->execute();

    expect($result)->toBe(['entriesFetched' => 2, 'rowsWritten' => 2]);
    expect(ElectricityPrice::query()->count())->toBe(2);
    expect(ElectricityPrice::query()->where('source', PriceSource::Entsoe)->where('bidding_zone', BiddingZone::FI)->count())->toBe(2);
});

test('requests a UTC-midnight-anchored 48 hour window', function () {
    config(['services.entsoe.token' => 'test-token']);
    Http::fake(['web-api.tp.entsoe.eu/*' => Http::response(ingestPricesXmlFixture(), 200)]);

    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-01-01 13:37:00', 'UTC'));

    app(IngestPricesAction::class)->execute();

    Http::assertSent(function ($request) {
        return $request['periodStart'] === '202601010000' && $request['periodEnd'] === '202601030000';
    });

    Carbon\Carbon::setTestNow();
});

test('wraps an EntsoeFetchException message as-is', function () {
    config(['services.entsoe.token' => null]);

    expect(fn () => app(IngestPricesAction::class)->execute())
        ->toThrow(RuntimeException::class, 'ENTSO_E_TOKEN not configured');
});

test('wraps any other unexpected error with a generic message', function () {
    config(['services.entsoe.token' => 'test-token']);
    $this->mock(EntsoePriceProvider::class, function ($mock) {
        $mock->shouldReceive('fetchPrices')->once()->andThrow(new RuntimeException('some upstream fluke'));
        $mock->shouldReceive('source')->andReturn(PriceSource::Entsoe);
        $mock->shouldReceive('biddingZone')->andReturn(BiddingZone::FI);
    });

    expect(fn () => app(IngestPricesAction::class)->execute())
        ->toThrow(RuntimeException::class, 'Unexpected ingestion error');
});
