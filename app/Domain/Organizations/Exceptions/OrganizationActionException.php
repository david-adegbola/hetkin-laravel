<?php

namespace App\Domain\Organizations\Exceptions;

use RuntimeException;

/**
 * User-facing business-rule failure for the Organizations domain's
 * admin-facing Actions — same role as App\Domain\Billing\Exceptions\
 * BillingActionException: "the business rule failed, show this message"
 * rather than a 500.
 */
class OrganizationActionException extends RuntimeException {}
