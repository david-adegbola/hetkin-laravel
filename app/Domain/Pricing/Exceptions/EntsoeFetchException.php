<?php

namespace App\Domain\Pricing\Exceptions;

use RuntimeException;

/**
 * Mirrors the Next.js app's `EntsoeFetchError` exactly (src/lib/entsoe.ts)
 * — a trivial subclass used purely as a type discriminator by callers
 * (App\Domain\Pricing\Actions\IngestPricesAction unwraps its message;
 * App\Domain\Pricing\Actions\BackfillPricesAction's retry logic checks
 * whether the message contains "not configured" to decide whether a
 * failure is retryable).
 */
class EntsoeFetchException extends RuntimeException {}
