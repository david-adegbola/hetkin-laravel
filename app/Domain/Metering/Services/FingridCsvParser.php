<?php

namespace App\Domain\Metering\Services;

use App\Domain\Metering\DTOs\ParsedMeterRow;
use App\Domain\Metering\Exceptions\InvalidMeterCsvException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Fingrid Datahub CSV export parser — a line-for-line PHP port of the
 * Next.js app's src/lib/meter-csv.ts (itself ported from
 * hetkin-nextjs/src/lib/calculator.ts's parseFingridCSV). Handles PT1H and
 * PT15M resolutions, semicolon- or comma-delimited, matching Fingrid's
 * actual export format quirks the original was written against real
 * exports to handle — not reinvented from scratch, and not "improved" here
 * either: every quirk below (including ones that look like they could be
 * simplified) is preserved exactly because it maps to a real Fingrid export
 * shape the original author encountered.
 */
class FingridCsvParser
{
    private const MAX_RAW_ROWS = 50_000;

    /**
     * @return array<int, ParsedMeterRow>
     *
     * @throws InvalidMeterCsvException
     */
    public function parse(string $text): array
    {
        $lines = array_values(array_filter(
            preg_split('/\r?\n/', trim($text)),
            fn (string $line) => trim($line) !== '',
        ));

        if (count($lines) < 2) {
            throw new InvalidMeterCsvException('File is empty or has no data rows.');
        }

        $delimiter = str_contains($lines[0], ';') ? ';' : ',';
        $rawHeaders = array_map(
            fn (string $h) => $this->stripSurroundingQuotes(trim($h)),
            explode($delimiter, $lines[0]),
        );
        $lowerHeaders = array_map(strtolower(...), $rawHeaders);

        $startCol = $this->findColumn($lowerHeaders, ['start time', 'alkuaika', 'start', 'timestamp', 'time']);
        $quantityCol = $this->findColumn($lowerHeaders, ['quantity', 'määrä', 'value', 'kwh', 'energy']);
        $resolutionCol = $this->findColumn($lowerHeaders, ['resolution', 'resoluutio', 'interval']);
        $qualityCol = $this->findColumn($lowerHeaders, ['quality', 'laatu']);

        if ($startCol === -1) {
            throw new InvalidMeterCsvException('Cannot find a start-time column. Found: '.implode(', ', $rawHeaders));
        }
        if ($quantityCol === -1) {
            throw new InvalidMeterCsvException('Cannot find a consumption (quantity/kWh) column. Found: '.implode(', ', $rawHeaders));
        }

        $dataLines = array_values(array_filter(
            array_slice($lines, 1),
            fn (string $line) => trim($line) !== '',
        ));

        if (count($dataLines) === 0) {
            throw new InvalidMeterCsvException('No data rows found after header.');
        }
        if (count($dataLines) > self::MAX_RAW_ROWS) {
            throw new InvalidMeterCsvException(sprintf(
                'File has %s data rows, which exceeds the %s-row limit. Export a shorter date range from Fingrid Datahub.',
                number_format(count($dataLines)),
                number_format(self::MAX_RAW_ROWS),
            ));
        }

        $resolution = 'PT1H';
        if ($resolutionCol !== -1) {
            $firstRowCols = explode($delimiter, $dataLines[0]);
            $resolutionValue = strtoupper(trim($firstRowCols[$resolutionCol] ?? ''));
            if ($resolutionValue === 'PT15M') {
                $resolution = 'PT15M';
            }
        }

        /** @var array<int, array{ts: Carbon, qty: float}> $rawRows */
        $rawRows = [];
        foreach ($dataLines as $line) {
            $cols = explode($delimiter, $line);

            // Include qualityCol in the length check even though it's
            // optional (-1 when absent doesn't affect the max) — a row
            // truncated right after its quantity column would otherwise
            // pass this guard and then hit a missing column below instead
            // of being safely skipped.
            if (count($cols) < max($startCol, $quantityCol, $qualityCol) + 1) {
                continue;
            }

            $startStr = $this->stripSurroundingQuotes(trim($cols[$startCol]));
            // preg_replace with a limit of 1, not str_replace: str_replace
            // has no "first occurrence only" mode (its 4th parameter is an
            // output count, not a limit) — this must match JS's non-global
            // `.replace(",", ".")`, which replaces only the first comma.
            $qtyStr = preg_replace('/,/', '.', $this->stripSurroundingQuotes(trim($cols[$quantityCol])), 1);
            $quality = $qualityCol !== -1 ? strtoupper(trim($cols[$qualityCol])) : 'OK';

            if ($startStr === '' || $quality === 'MISSING') {
                continue;
            }

            $ts = $this->parseIsoLoose($startStr);
            if ($ts === null) {
                throw new InvalidMeterCsvException('Cannot parse timestamp: '.$startStr);
            }

            $qty = $this->parseFloatLoose($qtyStr);
            if ($qty === null) {
                throw new InvalidMeterCsvException('Non-numeric quantity on line: '.$line);
            }

            $rawRows[] = ['ts' => $ts, 'qty' => $qty];
        }

        if (count($rawRows) === 0) {
            throw new InvalidMeterCsvException('No valid data rows found.');
        }

        if ($resolution === 'PT15M') {
            /** @var array<string, array{ts: Carbon, qty: float, count: int}> $buckets */
            $buckets = [];
            foreach ($rawRows as $row) {
                $floored = $this->floorToHour($row['ts']);
                $key = $floored->format('Y-m-d\TH');
                $buckets[$key] ??= ['ts' => $floored, 'qty' => 0.0, 'count' => 0];
                $buckets[$key]['qty'] += $row['qty'];
                $buckets[$key]['count']++;
            }
            $hourlyRows = array_values(array_map(
                fn (array $b) => ['ts' => $b['ts'], 'qty' => $b['qty']],
                array_filter($buckets, fn (array $b) => $b['count'] >= 3),
            ));
        } else {
            $hourlyRows = $rawRows;
        }

        if (count($hourlyRows) < 20) {
            throw new InvalidMeterCsvException(sprintf(
                'Only %d hourly rows found. Expected at least 20 for a meaningful upload.',
                count($hourlyRows),
            ));
        }

        usort($hourlyRows, fn (array $a, array $b) => $a['ts']->timestamp <=> $b['ts']->timestamp);

        return array_map(
            fn (array $row) => new ParsedMeterRow($row['ts'], $row['qty']),
            $hourlyRows,
        );
    }

    /**
     * @param  array<int, string>  $candidates
     */
    private function findColumn(array $headers, array $candidates): int
    {
        foreach ($candidates as $candidate) {
            foreach ($headers as $index => $header) {
                if (str_contains($header, $candidate)) {
                    return $index;
                }
            }
        }

        return -1;
    }

    private function stripSurroundingQuotes(string $value): string
    {
        return preg_replace('/^"|"$/', '', $value);
    }

    private function parseIsoLoose(string $value): ?Carbon
    {
        // Matches JS's `str.replace(/\s/, "T")` — only the FIRST whitespace
        // character becomes a "T" (a Fingrid export's "2024-01-01 00:00"
        // becomes the ISO-parseable "2024-01-01T00:00"), not every space.
        $normalized = preg_replace('/\s/', 'T', $value, 1);

        try {
            return Carbon::parse($normalized);
        } catch (Throwable) {
            return null;
        }
    }

    private function floorToHour(Carbon $ts): Carbon
    {
        return $ts->copy()->utc()->startOf('hour');
    }

    /**
     * JS's `parseFloat` leniently extracts a leading numeric token and
     * ignores trailing garbage (`parseFloat("12.5xyz")` === 12.5), unlike
     * PHP's `is_numeric()`/`(float)` cast, which either rejects the whole
     * string or silently truncates without signaling failure. Matched here
     * with a leading-numeric-token regex so a value Fingrid's export
     * quirks might trail with extra characters parses the same way it did
     * in the original, and a value with no leading numeric token at all
     * (parseFloat's NaN case) is rejected the same way too.
     */
    private function parseFloatLoose(string $value): ?float
    {
        if (preg_match('/^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?/', trim($value), $matches) !== 1) {
            return null;
        }

        return (float) $matches[0];
    }
}
