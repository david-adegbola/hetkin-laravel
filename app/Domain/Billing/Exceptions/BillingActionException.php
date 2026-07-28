<?php

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

/**
 * User-facing business-rule failure for the Billing domain's Actions —
 * same role as App\Domain\Optimization\Exceptions\OptimizationActionException:
 * "the business rule failed, show this message" rather than a 500.
 */
class BillingActionException extends RuntimeException {}
