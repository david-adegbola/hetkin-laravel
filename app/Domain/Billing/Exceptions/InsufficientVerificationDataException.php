<?php

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

/** Mirrors the Next.js app's `InsufficientVerificationDataError` (src/lib/verification/types.ts). */
class InsufficientVerificationDataException extends RuntimeException {}
