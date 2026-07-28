<?php

use App\Domain\Metering\Exceptions\InvalidMeterCsvException;
use App\Domain\Metering\Services\FingridCsvParser;
use Illuminate\Support\Carbon;

function csvRows(int $count, string $delimiter = ',', string $startDate = '2024-01-01T00:00'): string
{
    $header = "Start time{$delimiter}Quantity";
    $lines = [$header];
    $ts = new DateTimeImmutable($startDate);
    for ($i = 0; $i < $count; $i++) {
        $lines[] = $ts->modify("+{$i} hours")->format('Y-m-d\TH:i').$delimiter.'10.5';
    }

    return implode("\n", $lines);
}

test('parses a well-formed comma-delimited PT1H file', function () {
    $rows = (new FingridCsvParser)->parse(csvRows(24));

    expect($rows)->toHaveCount(24);
    expect($rows[0]->consumptionKwh)->toBe(10.5);
    expect($rows[0]->hourStart)->toBeInstanceOf(Carbon::class);
});

test('detects semicolon delimiter from the header line', function () {
    $rows = (new FingridCsvParser)->parse(csvRows(24, ';'));

    expect($rows)->toHaveCount(24);
});

test('rows are sorted by hourStart ascending regardless of input order', function () {
    $header = 'Start time,Quantity';
    $lines = [
        $header,
        ...array_map(
            fn (int $i) => sprintf('2024-01-01T%02d:00,%d', $i, $i),
            range(0, 23),
        ),
    ];
    // Reverse the data rows so input arrives out of order.
    $shuffled = [$lines[0], ...array_reverse(array_slice($lines, 1))];

    $rows = (new FingridCsvParser)->parse(implode("\n", $shuffled));

    expect($rows[0]->consumptionKwh)->toBe(0.0);
    expect($rows[23]->consumptionKwh)->toBe(23.0);
});

test('accepts a decimal comma in the quantity column when semicolon-delimited', function () {
    // A literal comma inside a field only makes sense once the file's own
    // delimiter is semicolon — with a comma-delimited file, "10,5" would
    // itself be split into two columns.
    $lines = ['Start time;Quantity'];
    for ($i = 0; $i < 20; $i++) {
        $lines[] = sprintf('2024-01-01T%02d:00;10,5', $i);
    }

    $rows = (new FingridCsvParser)->parse(implode("\n", $lines));

    expect($rows[0]->consumptionKwh)->toBe(10.5);
});

test('strips surrounding quotes from header and data values', function () {
    $lines = ['"Start time","Quantity"'];
    for ($i = 0; $i < 20; $i++) {
        $lines[] = sprintf('"2024-01-01T%02d:00","10.5"', $i);
    }

    $rows = (new FingridCsvParser)->parse(implode("\n", $lines));

    expect($rows)->toHaveCount(20);
});

test('recognizes Finnish header aliases', function () {
    $lines = ['Alkuaika;Määrä'];
    for ($i = 0; $i < 20; $i++) {
        $lines[] = sprintf('2024-01-01T%02d:00;5.0', $i);
    }

    $rows = (new FingridCsvParser)->parse(implode("\n", $lines));

    expect($rows)->toHaveCount(20);
});

test('skips rows with MISSING quality', function () {
    $lines = ['Start time,Quantity,Quality'];
    for ($i = 0; $i < 25; $i++) {
        $quality = $i < 5 ? 'MISSING' : 'OK';
        $lines[] = sprintf('2024-01-01T%02d:00,10.0,%s', $i, $quality);
    }

    $rows = (new FingridCsvParser)->parse(implode("\n", $lines));

    // 25 rows minus 5 MISSING = 20, clearing the 20-row minimum exactly.
    expect($rows)->toHaveCount(20);
});

test('aggregates PT15M rows into hourly buckets, requiring at least 3 of 4 subintervals', function () {
    $lines = ['Start time,Quantity,Resolution'];
    // 20 complete hours of 4 x 15-minute rows each (1.0 kWh per interval).
    for ($h = 0; $h < 20; $h++) {
        for ($q = 0; $q < 4; $q++) {
            $minute = $q * 15;
            $lines[] = sprintf('2024-01-01T%02d:%02d,1.0,PT15M', $h, $minute);
        }
    }

    $rows = (new FingridCsvParser)->parse(implode("\n", $lines));

    expect($rows)->toHaveCount(20);
    // Each hour sums its 4 subintervals: 4 x 1.0 = 4.0 kWh.
    expect($rows[0]->consumptionKwh)->toBe(4.0);
});

test('drops an hour with fewer than 3 of its 4 PT15M subintervals', function () {
    $lines = ['Start time,Quantity,Resolution'];
    // 19 complete hours...
    for ($h = 0; $h < 19; $h++) {
        for ($q = 0; $q < 4; $q++) {
            $lines[] = sprintf('2024-01-01T%02d:%02d,1.0,PT15M', $h, $q * 15);
        }
    }
    // ...plus one hour with only 2 of 4 subintervals present.
    $lines[] = '2024-01-01T19:00,1.0,PT15M';
    $lines[] = '2024-01-01T19:15,1.0,PT15M';

    expect(fn () => (new FingridCsvParser)->parse(implode("\n", $lines)))
        ->toThrow(InvalidMeterCsvException::class, 'Only 19 hourly rows found');
});

test('throws for an empty file', function () {
    expect(fn () => (new FingridCsvParser)->parse(''))
        ->toThrow(InvalidMeterCsvException::class, 'File is empty or has no data rows.');
});

test('throws when no start-time column is found', function () {
    $csv = "Foo,Quantity\n1,2";

    expect(fn () => (new FingridCsvParser)->parse($csv))
        ->toThrow(InvalidMeterCsvException::class, 'Cannot find a start-time column.');
});

test('throws when no quantity column is found', function () {
    $csv = "Start time,Foo\n2024-01-01T00:00,2";

    expect(fn () => (new FingridCsvParser)->parse($csv))
        ->toThrow(InvalidMeterCsvException::class, 'Cannot find a consumption (quantity/kWh) column.');
});

test('throws when the row count exceeds the 50,000-row cap', function () {
    $csv = csvRows(50_001);

    expect(fn () => (new FingridCsvParser)->parse($csv))
        ->toThrow(InvalidMeterCsvException::class, 'exceeds the 50,000-row limit');
});

test('throws for an unparseable timestamp', function () {
    $csv = "Start time,Quantity\nnot-a-date,10.5\n".implode("\n", array_map(
        fn (int $i) => sprintf('2024-01-01T%02d:00,10.5', $i),
        range(1, 19),
    ));

    expect(fn () => (new FingridCsvParser)->parse($csv))
        ->toThrow(InvalidMeterCsvException::class, 'Cannot parse timestamp: not-a-date');
});

test('throws for a non-numeric quantity', function () {
    $csv = "Start time,Quantity\n2024-01-01T00:00,not-a-number\n".implode("\n", array_map(
        fn (int $i) => sprintf('2024-01-01T%02d:00,10.5', $i),
        range(1, 19),
    ));

    expect(fn () => (new FingridCsvParser)->parse($csv))
        ->toThrow(InvalidMeterCsvException::class, 'Non-numeric quantity on line');
});

test('leniently extracts a leading numeric quantity, matching JS parseFloat', function () {
    $lines = ['Start time,Quantity'];
    for ($i = 0; $i < 20; $i++) {
        $lines[] = sprintf('2024-01-01T%02d:00,12.5kWh', $i);
    }

    $rows = (new FingridCsvParser)->parse(implode("\n", $lines));

    expect($rows[0]->consumptionKwh)->toBe(12.5);
});

test('throws when fewer than 20 hourly rows are found', function () {
    $csv = csvRows(19);

    expect(fn () => (new FingridCsvParser)->parse($csv))
        ->toThrow(InvalidMeterCsvException::class, 'Only 19 hourly rows found');
});

test('skips a truncated row missing trailing columns', function () {
    $lines = ['Start time,Quantity,Quality'];
    for ($i = 0; $i < 19; $i++) {
        $lines[] = sprintf('2024-01-01T%02d:00,10.0,OK', $i);
    }
    // Truncated: no Quality column value at all on this line.
    $lines[] = '2024-01-01T19:00,10.0';
    // One more good row to still clear the 20-row minimum.
    $lines[] = '2024-01-01T20:00,10.0,OK';

    $rows = (new FingridCsvParser)->parse(implode("\n", $lines));

    expect($rows)->toHaveCount(20);
});
