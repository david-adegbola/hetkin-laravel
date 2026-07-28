<?php

namespace App\Domain\Auth\DTOs;

use Illuminate\Support\Carbon;

/**
 * `location` is always null today — no geolocation resolver is wired up
 * (deliberately not added by this module; see Account Security module's
 * design notes). The field exists so a resolver can be plugged in later
 * without changing ActiveSessionFinder's callers or the frontend contract.
 */
final class ActiveSessionData
{
    public function __construct(
        public readonly string $id,
        public readonly string $browser,
        public readonly string $platform,
        public readonly ?string $ipAddress,
        public readonly ?string $location,
        public readonly Carbon $lastActiveAt,
        public readonly bool $isCurrentDevice,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'browser' => $this->browser,
            'platform' => $this->platform,
            'ip_address' => $this->ipAddress,
            'location' => $this->location,
            'last_active_at' => $this->lastActiveAt->toIso8601String(),
            'last_active_at_human' => $this->lastActiveAt->diffForHumans(),
            'is_current_device' => $this->isCurrentDevice,
        ];
    }
}
