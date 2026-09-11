<?php

namespace App\Domain\Pricing\Concerns;

use App\Domain\Pricing\Exceptions\EntsoeFetchException;
use Closure;
use Throwable;

/**
 * Exponential-backoff retry for a single PriceProvider::fetchPrices() call,
 * shared by App\Domain\Pricing\Actions\BackfillPricesAction (its original
 * home) and App\Domain\Pricing\Actions\IngestPricesAction — both retry the
 * exact same failure class the exact same way, so this used to be
 * duplicated between them until issue 139344544 (a production
 * ingest-prices run that died on a single transient egress-proxy timeout,
 * with nothing to retry it until the next day's cron) showed the daily
 * ingest job needed it too.
 */
trait RetriesTransientEntsoeFailures
{
    private const RETRY_BASE_DELAY_MS = 1000;

    /** Config errors (e.g. a missing API token) won't fix themselves on retry. */
    private function isRetryable(Throwable $e): bool
    {
        return ! ($e instanceof EntsoeFetchException && str_contains($e->getMessage(), 'not configured'));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $fn
     * @return T
     */
    private function withRetry(Closure $fn, int $maxRetries)
    {
        $lastException = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                return $fn();
            } catch (Throwable $e) {
                $lastException = $e;
                if (! $this->isRetryable($e) || $attempt === $maxRetries) {
                    throw $e;
                }
                usleep((int) (self::RETRY_BASE_DELAY_MS * (2 ** $attempt) * 1000));
            }
        }

        throw $lastException;
    }
}
