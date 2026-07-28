<?php

namespace App\Domain\Metering\Exceptions;

use RuntimeException;

/**
 * Every message this is thrown with is shown directly to the uploading
 * user (mirrors src/lib/meter-csv.ts's plain `Error` — the Next.js app's
 * caller does `err instanceof Error ? err.message : "Could not parse this
 * file."`), so wording changes here are user-facing copy changes, not just
 * internal diagnostics.
 */
class InvalidMeterCsvException extends RuntimeException {}
