<?php

namespace App\Domain\Pricing\Services;

use App\Domain\Pricing\Exceptions\EntsoeFetchException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Ports the Next.js app's fetchEntsoePrices() exactly (src/lib/entsoe.ts)
 * — a real, working HTTP integration against ENTSO-E's Transparency
 * Platform, "already proven against live data" per the source's own
 * header comment, not a stub. Callers only ever see the parsed
 * `{timestamp, price}` pairs or an EntsoeFetchException; nothing here
 * knows about `App\Domain\Pricing\DTOs\PricePoint` — that mapping lives
 * one layer up, in App\Domain\Pricing\Providers\EntsoePriceProvider, same
 * separation v2 keeps between this file and its own EntsoePriceProvider.
 */
class EntsoeClient
{
    private const URL = 'https://web-api.tp.entsoe.eu/api';

    /** EIC code for the Finnish bidding zone — the only zone this app ever queries. */
    private const FI_BIDDING_ZONE = '10YFI-1--------U';

    public function __construct(
        private readonly ?string $token = null,
    ) {}

    /**
     * @return list<array{timestamp: int, price: float}>
     *
     * @throws EntsoeFetchException
     */
    public function fetchPrices(Carbon $start, Carbon $end): array
    {
        $token = $this->token ?? config('services.entsoe.token');

        if (! $token) {
            throw new EntsoeFetchException('ENTSO_E_TOKEN not configured');
        }

        $response = Http::withHeaders(['Accept' => 'application/xml'])->get(self::URL, [
            'securityToken' => $token,
            'documentType' => 'A44',
            'in_Domain' => self::FI_BIDDING_ZONE,
            'out_Domain' => self::FI_BIDDING_ZONE,
            'periodStart' => $this->toEntsoeDate($start),
            'periodEnd' => $this->toEntsoeDate($end),
        ]);

        $body = $response->body();

        if (! $response->successful() || str_contains($body, 'Acknowledgement_MarketDocument')) {
            $reason = $this->extractText($body) ?? "HTTP {$response->status()}";

            throw new EntsoeFetchException("ENTSO-E: {$reason}");
        }

        $entries = $this->parseXml($body);

        if (empty($entries)) {
            throw new EntsoeFetchException('No price data returned from ENTSO-E');
        }

        return $entries;
    }

    /** ENTSO-E's `yyyyMMddHHmm` format, always UTC. */
    private function toEntsoeDate(Carbon $date): string
    {
        return $date->clone()->utc()->format('YmdHi');
    }

    private function extractText(string $body): ?string
    {
        return preg_match('/<text>(.*?)<\/text>/s', $body, $matches) === 1
            ? trim($matches[1])
            : null;
    }

    /**
     * Hand-rolled regex parsing, matching v2 exactly rather than pulling in
     * a real XML parser dependency for one endpoint's fixed shape.
     *
     * @return list<array{timestamp: int, price: float}>
     */
    private function parseXml(string $body): array
    {
        $entries = [];

        preg_match_all('/<Period>(.*?)<\/Period>/s', $body, $periodMatches);

        foreach ($periodMatches[1] as $periodXml) {
            if (preg_match('/<start>(.*?)<\/start>/', $periodXml, $startMatch) !== 1) {
                continue;
            }

            $periodStartEpoch = Carbon::parse($startMatch[1], 'UTC')->getTimestamp();

            preg_match('/<resolution>(.*?)<\/resolution>/', $periodXml, $resolutionMatch);
            $resolutionSeconds = match ($resolutionMatch[1] ?? null) {
                'PT30M' => 1800,
                'PT15M' => 900,
                default => 3600,
            };

            preg_match_all('/<Point>(.*?)<\/Point>/s', $periodXml, $pointMatches);

            foreach ($pointMatches[1] as $pointXml) {
                if (preg_match('/<position>(\d+)<\/position>/', $pointXml, $positionMatch) !== 1
                    || preg_match('/<price\.amount>([\d.\-]+)<\/price\.amount>/', $pointXml, $priceMatch) !== 1
                ) {
                    continue;
                }

                $position = (int) $positionMatch[1];

                $entries[] = [
                    'timestamp' => $periodStartEpoch + ($position - 1) * $resolutionSeconds,
                    'price' => (float) $priceMatch[1],
                ];
            }
        }

        return $entries;
    }
}
