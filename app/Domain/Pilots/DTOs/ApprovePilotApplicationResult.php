<?php

namespace App\Domain\Pilots\DTOs;

use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Pilots\Models\PilotApplication;

final class ApprovePilotApplicationResult
{
    public function __construct(
        public readonly PilotApplication $application,
        public readonly Organization $organization,
        public readonly Invitation $invitation,
    ) {}
}
