<?php

namespace App\Domain\Scheduling\Services;

use App\Domain\Scheduling\DTOs\ParsedScheduleBlock;
use App\Domain\Scheduling\Enums\LabourClassification;
use App\Domain\Scheduling\Exceptions\InvalidScheduleCsvException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Imports a CSV exported from a production ERP into Hetkin; it never calls
 * the ERP or writes back to it. Headers are deliberately matched by common
 * ERP labels so a customer can export a normal planning report rather than
 * hand-maintain a Hetkin-specific file. Naive times are Finnish site time.
 */
class ScheduleCsvParser
{
    private const MAX_ROWS = 10_000;

    /** @return array<int, ParsedScheduleBlock> */
    public function parse(string $text): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($text)) ?: [];
        $lines = array_values(array_filter($lines, fn (string $line) => trim($line) !== ''));

        if (count($lines) < 2) {
            throw new InvalidScheduleCsvException('File is empty or has no data rows.');
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $headers = array_map(fn (string $value) => $this->normalizeHeader($value), str_getcsv($lines[0], $delimiter));
        $assetColumn = $this->findColumn($headers, ['asset label', 'asset', 'work center', 'workcentre', 'production line', 'operation']);
        $startColumn = $this->findColumn($headers, ['start at', 'start time', 'planned start', 'start']);
        $endColumn = $this->findColumn($headers, ['end at', 'end time', 'planned end', 'end', 'finish']);

        if ($assetColumn === -1 || $startColumn === -1 || $endColumn === -1) {
            throw new InvalidScheduleCsvException('CSV must include asset_label, start_at, and end_at columns. Found: '.implode(', ', $headers));
        }

        $kwhColumn = $this->findColumn($headers, ['estimated kwh', 'estimated energy', 'energy kwh', 'kwh', 'energy']);
        $labourColumn = $this->findColumn($headers, ['labour classification', 'labor classification', 'labour', 'labor']);
        $descriptionColumn = $this->findColumn($headers, ['description', 'notes', 'note']);
        $siteColumn = $this->findColumn($headers, ['site name', 'site', 'plant', 'factory']);
        $dataLines = array_slice($lines, 1);

        if (count($dataLines) > self::MAX_ROWS) {
            throw new InvalidScheduleCsvException(sprintf('File has %s data rows, exceeding the %s-row limit.', number_format(count($dataLines)), number_format(self::MAX_ROWS)));
        }

        $rows = [];
        foreach ($dataLines as $offset => $line) {
            $lineNumber = $offset + 2;
            $columns = str_getcsv($line, $delimiter);
            if (count($columns) <= max($assetColumn, $startColumn, $endColumn)) {
                throw new InvalidScheduleCsvException("Line {$lineNumber} is missing a required value.");
            }

            $asset = trim($columns[$assetColumn]);
            if ($asset === '') {
                throw new InvalidScheduleCsvException("Line {$lineNumber} has no asset label.");
            }
            $this->ensureMaximumLength($asset, $lineNumber, 'asset label');

            $start = $this->parseDate(trim($columns[$startColumn]), $lineNumber, 'start');
            $end = $this->parseDate(trim($columns[$endColumn]), $lineNumber, 'end');
            if ($end->lessThanOrEqualTo($start)) {
                throw new InvalidScheduleCsvException("Line {$lineNumber} must end after it starts.");
            }

            $kwh = $kwhColumn !== -1 ? $this->parseKwh($columns[$kwhColumn] ?? '', $lineNumber) : null;
            $labour = $labourColumn !== -1
                ? $this->parseLabour($columns[$labourColumn] ?? '', $lineNumber)
                : LabourClassification::LabourBound;

            $description = $descriptionColumn !== -1 ? $this->nullableString($columns[$descriptionColumn] ?? '') : null;
            $siteName = $siteColumn !== -1 ? ($this->nullableString($columns[$siteColumn] ?? '') ?? 'Main site') : 'Main site';
            if ($description !== null) {
                $this->ensureMaximumLength($description, $lineNumber, 'description');
            }
            $this->ensureMaximumLength($siteName, $lineNumber, 'site name');

            $rows[] = new ParsedScheduleBlock(
                $asset,
                $start,
                $end,
                $kwh,
                $labour,
                $description,
                $siteName,
            );
        }

        return $rows;
    }

    /** @param array<int, string> $headers @param array<int, string> $candidates */
    private function findColumn(array $headers, array $candidates): int
    {
        foreach ($candidates as $candidate) {
            $candidate = $this->normalizeHeader($candidate);
            foreach ($headers as $index => $header) {
                if ($header === $candidate) {
                    return $index;
                }
            }
        }

        return -1;
    }

    private function normalizeHeader(string $value): string
    {
        return str_replace(['_', '-'], ' ', strtolower(trim($value)));
    }

    private function parseDate(string $value, int $lineNumber, string $field): Carbon
    {
        if ($value === '') {
            throw new InvalidScheduleCsvException("Line {$lineNumber} has no {$field} time.");
        }

        try {
            return Carbon::parse($value, config('scheduling.timezone'))->utc();
        } catch (Throwable) {
            throw new InvalidScheduleCsvException("Line {$lineNumber} has an invalid {$field} time: {$value}");
        }
    }

    private function parseKwh(string $value, int $lineNumber): ?float
    {
        $value = trim(str_replace(',', '.', $value));
        if ($value === '') {
            return null;
        }
        if (! is_numeric($value) || (float) $value <= 0) {
            throw new InvalidScheduleCsvException("Line {$lineNumber} has an invalid estimated kWh value.");
        }

        return (float) $value;
    }

    private function parseLabour(string $value, int $lineNumber): LabourClassification
    {
        return match (strtoupper(str_replace([' ', '-'], '_', trim($value)))) {
            '', 'LABOUR_BOUND', 'LABOR_BOUND' => LabourClassification::LabourBound,
            'UNMANNED' => LabourClassification::Unmanned,
            default => throw new InvalidScheduleCsvException("Line {$lineNumber} has an invalid labour classification. Use UNMANNED or LABOUR_BOUND."),
        };
    }

    private function nullableString(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function ensureMaximumLength(string $value, int $lineNumber, string $field): void
    {
        if (mb_strlen($value) > 255) {
            throw new InvalidScheduleCsvException("Line {$lineNumber} has a {$field} longer than 255 characters.");
        }
    }
}
