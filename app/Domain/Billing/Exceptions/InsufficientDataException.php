<?php

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

/** Mirrors the Next.js app's `InsufficientDataError` (src/lib/savings/types.ts). */
class InsufficientDataException extends RuntimeException {}
