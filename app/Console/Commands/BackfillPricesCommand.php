<?php

namespace App\Console\Commands;

use App\Domain\Pricing\Actions\BackfillPricesAction;
use App\Domain\Pricing\DTOs\BackfillChunkProgress;
use App\Domain\Pricing\Enums\PriceSource;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Ports the Next.js app's scripts/backfill-prices.ts exactly — historical
 * ElectricityPrice backfill run from your own machine. Why a CLI in
 * addition to the admin "Run backfill" form (App\Http\Controllers\Admin\
 * JobController::triggerBackfillPrices): a large range (years, not months)
 * can take long enough to risk a web request timeout, which is why the
 * admin form caps itself at App\Http\Requests\Admin\BackfillPricesRequest::
 * MAX_ADMIN_BACKFILL_DAYS days. This command calls the exact same
 * App\Domain\Pricing\Actions\BackfillPricesAction core with no such
 * ceiling — it's just a long-running process on your machine.
 *
 * Usage:
 *   php artisan prices:backfill --start=2025-01-01 --end=2025-02-01
 *
 * Optional:
 *   --source=ENTSOE      # only source implemented today
 *   --chunk-days=30      # smaller chunks = more, smaller upstream requests
 *
 * Safe to re-run, or to re-run over a range that overlaps a previous run —
 * already-covered days are skipped, and writes are idempotent.
 */
class BackfillPricesCommand extends Command
{
    protected $signature = 'prices:backfill
        {--start= : Inclusive start date (YYYY-MM-DD)}
        {--end= : Exclusive end date (YYYY-MM-DD)}
        {--source=ENTSOE : Price source}
        {--chunk-days= : Days per chunk (default 30)}';

    protected $description = 'Backfill historical electricity prices for a date range, with no execution-time ceiling.';

    public function handle(BackfillPricesAction $action): int
    {
        $startOption = $this->option('start');
        $endOption = $this->option('end');

        if (! $startOption) {
            $this->error('--start is required (format: YYYY-MM-DD).');

            return self::FAILURE;
        }
        if (! $endOption) {
            $this->error('--end is required (format: YYYY-MM-DD).');

            return self::FAILURE;
        }

        try {
            $startDate = Carbon::createFromFormat('!Y-m-d', $startOption, 'UTC');
        } catch (Throwable) {
            $this->error("--start=\"{$startOption}\" is not a valid date (expected YYYY-MM-DD).");

            return self::FAILURE;
        }

        try {
            $endDate = Carbon::createFromFormat('!Y-m-d', $endOption, 'UTC');
        } catch (Throwable) {
            $this->error("--end=\"{$endOption}\" is not a valid date (expected YYYY-MM-DD).");

            return self::FAILURE;
        }

        $sourceValue = strtoupper((string) $this->option('source'));
        $source = PriceSource::tryFrom($sourceValue);
        if (! $source) {
            $this->error("--source=\"{$sourceValue}\" is not a recognized price source.");

            return self::FAILURE;
        }

        if ($endDate->lte($startDate)) {
            $this->error('--end must be after --start.');

            return self::FAILURE;
        }

        $chunkDaysOption = $this->option('chunk-days');
        $chunkDays = $chunkDaysOption !== null ? (int) $chunkDaysOption : BackfillPricesAction::DEFAULT_CHUNK_DAYS;

        $this->info("[backfill-prices] {$source->value} {$startDate->toDateString()} → {$endDate->toDateString()}");

        $result = $action->execute(
            $source,
            $startDate,
            $endDate,
            chunkDays: $chunkDays,
            onProgress: function (BackfillChunkProgress $progress): void {
                $range = $progress->rangeStart->toDateString().'–'.$progress->rangeEnd->toDateString();
                $detail = match ($progress->outcome) {
                    'written' => "wrote {$progress->rowsWritten} rows",
                    'failed' => "FAILED: {$progress->error}",
                    default => 'already covered, skipped',
                };
                $this->line('[backfill-prices] chunk '.($progress->chunkIndex + 1)."/{$progress->chunkCount} ({$range}) — {$detail}");
            },
        );

        $this->newLine();
        $this->line('========================================');
        $this->line("Chunks:  {$result['chunkCount']} total — {$result['chunksWritten']} written, {$result['chunksSkipped']} skipped, {$result['chunksFailed']} failed");
        $this->line('Rows written: '.number_format($result['rowsWritten']));

        if (count($result['failedRanges']) > 0) {
            $this->newLine();
            $this->line('Failed ranges (re-run with a narrower --start/--end to retry just these):');
            foreach ($result['failedRanges'] as $failedRange) {
                $this->line('  '.substr($failedRange['start'], 0, 10).' – '.substr($failedRange['end'], 0, 10).": {$failedRange['error']}");
            }
        }

        $this->line('========================================');

        return $result['chunksFailed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
