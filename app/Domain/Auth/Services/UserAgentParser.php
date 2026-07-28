<?php

namespace App\Domain\Auth\Services;

/**
 * Deliberately minimal, dependency-free browser/OS detection for the
 * active-sessions list — good enough for "Chrome on macOS," not
 * forensic-grade device fingerprinting. No new Composer dependency for
 * something this app only ever displays, never makes a security decision
 * against; swap in a real UA-parsing library later if that changes without
 * touching any caller (both callers only see the return shape).
 */
class UserAgentParser
{
    /**
     * @return array{browser: string, platform: string}
     */
    public function parse(?string $userAgent): array
    {
        if (! $userAgent) {
            return ['browser' => 'Unknown browser', 'platform' => 'Unknown device'];
        }

        return [
            'browser' => $this->detectBrowser($userAgent),
            'platform' => $this->detectPlatform($userAgent),
        ];
    }

    private function detectBrowser(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/Edg\//', $ua) => 'Edge',
            (bool) preg_match('/OPR\//', $ua) => 'Opera',
            (bool) preg_match('/Chrome\//', $ua) && ! str_contains($ua, 'Chromium') => 'Chrome',
            (bool) preg_match('/CriOS\//', $ua) => 'Chrome',
            (bool) preg_match('/FxiOS\//', $ua) => 'Firefox',
            (bool) preg_match('/Firefox\//', $ua) => 'Firefox',
            (bool) preg_match('/Version\/.*Safari\//', $ua) => 'Safari',
            default => 'Unknown browser',
        };
    }

    private function detectPlatform(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/', $ua) => 'iOS',
            (bool) preg_match('/Android/', $ua) => 'Android',
            (bool) preg_match('/Mac OS X/', $ua) => 'macOS',
            (bool) preg_match('/Windows/', $ua) => 'Windows',
            (bool) preg_match('/Linux/', $ua) => 'Linux',
            default => 'Unknown device',
        };
    }
}
