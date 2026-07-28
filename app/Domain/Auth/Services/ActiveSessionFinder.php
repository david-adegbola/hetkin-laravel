<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\DTOs\ActiveSessionData;
use App\Domain\Auth\Models\SessionRecord;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Reads the `sessions` table for the Security settings page's
 * active-sessions list. Read-only — revocation lives in RevokeSessionAction
 * / LogoutOtherDevicesAction, kept separate so a page render can never have
 * a write side effect.
 */
class ActiveSessionFinder
{
    public function __construct(private readonly UserAgentParser $userAgentParser) {}

    /**
     * @return Collection<int, ActiveSessionData>
     */
    public function forUser(User $user, string $currentSessionId): Collection
    {
        return SessionRecord::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function (SessionRecord $session) use ($currentSessionId) {
                $agent = $this->userAgentParser->parse($session->user_agent);

                return new ActiveSessionData(
                    id: $session->id,
                    browser: $agent['browser'],
                    platform: $agent['platform'],
                    ipAddress: $session->ip_address,
                    location: null,
                    lastActiveAt: $session->lastActivityAt(),
                    isCurrentDevice: $session->id === $currentSessionId,
                );
            });
    }
}
