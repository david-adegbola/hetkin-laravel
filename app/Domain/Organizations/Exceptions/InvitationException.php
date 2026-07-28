<?php

namespace App\Domain\Organizations\Exceptions;

use RuntimeException;

/**
 * User-facing failure for App\Domain\Organizations\Actions\
 * AcceptInvitationAction — "the invitation isn't valid for this attempt,
 * show this message" rather than a 500.
 */
class InvitationException extends RuntimeException {}
