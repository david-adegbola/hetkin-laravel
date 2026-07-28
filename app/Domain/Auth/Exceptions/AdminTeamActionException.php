<?php

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

/**
 * User-facing business-rule failure for the admin Team management Actions
 * (App\Domain\Auth\Actions\CreateAdminUserAction and siblings) — same role
 * as App\Domain\Organizations\Exceptions\OrganizationActionException: "the
 * business rule failed, show this message" rather than a 500.
 */
class AdminTeamActionException extends RuntimeException {}
