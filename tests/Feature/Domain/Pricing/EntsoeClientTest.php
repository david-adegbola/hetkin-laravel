<?php

use App\Domain\Pricing\Exceptions\EntsoeFetchException;
use App\Domain\Pricing\Services\EntsoeClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

function entsoeXmlFixture(): string
{
    return <<<'XML'
    <GL_MarketDocument>
        <TimeSeries>
            <Period>
                <timeInterval>
                    <start>2026-01-01T00:00Z</start>
                    <end>2026-01-01T02:00Z</end>
                </timeInterval>
                <resolution>PT60M</resolution>
                <Point>
                    <position>1</position>
                    <price.amount>45.50</price.amount>
                </Point>
                <Point>
                    <position>2</position>
                    <price.amount>52.10</price.amount>
                </Point>
            </Period>
        </TimeSeries>
    </GL_MarketDocument>
    XML;
}

test('throws when no token is configured', function () {
    config(['services.entsoe.token' => null]);
    $client = new EntsoeClient;

    expect(fn () => $client->fetchPrices(Carbon::parse('2026-01-01', 'UTC'), Carbon::parse('2026-01-02', 'UTC')))
        ->toThrow(EntsoeFetchException::class, 'ENTSO_E_TOKEN not configured');
});

test('parses hourly points into timestamp/price pairs', function () {
    Http::fake(['web-api.tp.entsoe.eu/*' => Http::response(entsoeXmlFixture(), 200)]);
    $client = new EntsoeClient('test-token');

    $entries = $client->fetchPrices(Carbon::parse('2026-01-01', 'UTC'), Carbon::parse('2026-01-02', 'UTC'));

    expect($entries)->toHaveCount(2);
    expect($entries[0]['price'])->toBe(45.50);
    expect($entries[1]['price'])->toBe(52.10);
    expect($entries[1]['timestamp'] - $entries[0]['timestamp'])->toBe(3600);
});

test('sends the security token, documentType, and Finnish bidding zone', function () {
    Http::fake(['web-api.tp.entsoe.eu/*' => Http::response(entsoeXmlFixture(), 200)]);
    $client = new EntsoeClient('secret-token');

    $client->fetchPrices(Carbon::parse('2026-01-01', 'UTC'), Carbon::parse('2026-01-02', 'UTC'));

    Http::assertSent(function ($request) {
        return $request['securityToken'] === 'secret-token'
            && $request['documentType'] === 'A44'
            && $request['in_Domain'] === '10YFI-1--------U'
            && $request['out_Domain'] === '10YFI-1--------U';
    });
});

test('throws with the extracted reason on an Acknowledgement_MarketDocument error response', function () {
    Http::fake(['web-api.tp.entsoe.eu/*' => Http::response(
        '<Acknowledgement_MarketDocument><Reason><text>No matching data found</text></Reason></Acknowledgement_MarketDocument>',
        200,
    )]);
    $client = new EntsoeClient('test-token');

    expect(fn () => $client->fetchPrices(Carbon::parse('2026-01-01', 'UTC'), Carbon::parse('2026-01-02', 'UTC')))
        ->toThrow(EntsoeFetchException::class, 'ENTSO-E: No matching data found');
});

test('throws on a non-2xx response', function () {
    Http::fake(['web-api.tp.entsoe.eu/*' => Http::response('Internal error', 500)]);
    $client = new EntsoeClient('test-token');

    expect(fn () => $client->fetchPrices(Carbon::parse('2026-01-01', 'UTC'), Carbon::parse('2026-01-02', 'UTC')))
        ->toThrow(EntsoeFetchException::class, 'HTTP 500');
});

test('throws when the response parses to zero entries', function () {
    Http::fake(['web-api.tp.entsoe.eu/*' => Http::response('<GL_MarketDocument></GL_MarketDocument>', 200)]);
    $client = new EntsoeClient('test-token');

    expect(fn () => $client->fetchPrices(Carbon::parse('2026-01-01', 'UTC'), Carbon::parse('2026-01-02', 'UTC')))
        ->toThrow(EntsoeFetchException::class, 'No price data returned from ENTSO-E');
});

test('resolves the 30-minute resolution correctly', function () {
    $xml = <<<'XML'
    <GL_MarketDocument>
        <TimeSeries>
            <Period>
                <start>2026-01-01T00:00Z</start>
                <resolution>PT30M</resolution>
                <Point><position>1</position><price.amount>10.0</price.amount></Point>
                <Point><position>2</position><price.amount>11.0</price.amount></Point>
            </Period>
        </TimeSeries>
    </GL_MarketDocument>
    XML;
    Http::fake(['web-api.tp.entsoe.eu/*' => Http::response($xml, 200)]);
    $client = new EntsoeClient('test-token');

    $entries = $client->fetchPrices(Carbon::parse('2026-01-01', 'UTC'), Carbon::parse('2026-01-02', 'UTC'));

    expect($entries[1]['timestamp'] - $entries[0]['timestamp'])->toBe(1800);
});
