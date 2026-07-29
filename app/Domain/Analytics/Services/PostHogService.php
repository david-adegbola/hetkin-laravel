<?php

namespace App\Domain\Analytics\Services;

use Illuminate\Support\Facades\Log;
use PostHog\Client;
use Throwable;

/**
 * Ports the Next.js app's captureServerEvent() exactly (src/lib/analytics/
 * server.ts) — server-side PostHog capture. Most of this app's meaningful
 * events happen inside Actions (pilot approval, assessment generation,
 * recommendation decisions, invoicing), not client clicks, so this is the
 * primary instrumentation surface, not an afterthought next to the client
 * SDK (resources/js/app.tsx's PostHog init).
 *
 * A missing/unset key makes `capture()` a silent no-op — same "log instead
 * of crash, never block the real work" posture as every other optional
 * integration in this app (Resend, Sentry). Never throws into its caller.
 *
 * Flushes immediately after every capture rather than letting the SDK's
 * default batching (5-second interval) hold events — v2's posthog-node
 * client makes the identical trade (`flushAt: 1, flushInterval: 0`) for
 * the same reason: a short-lived process (Vercel's serverless functions
 * there, a single PHP-FPM request here) can end before a delayed batch
 * ever gets sent, silently losing events.
 */
class PostHogService
{
    private ?Client $client = null;

    private bool $initialized = false;

    public function capture(string $event, string $distinctId, array $properties = []): void
    {
        try {
            $client = $this->client();
            if (! $client) {
                return;
            }

            $client->capture([
                'distinctId' => $distinctId,
                'event' => $event,
                'properties' => $properties,
            ]);
            $client->flush();
        } catch (Throwable $e) {
            Log::error("[analytics] failed to capture \"{$event}\"", ['exception' => $e]);
        }
    }

    private function client(): ?Client
    {
        if ($this->initialized) {
            return $this->client;
        }

        $this->initialized = true;

        $key = config('services.posthog.key');
        if (! $key) {
            return null;
        }

        // 'options' has no production purpose — it's how tests swap the
        // default network-sending consumer for the SDK's 'file' consumer
        // (writes to a local file instead of POSTing), so a capture()
        // call can be asserted on without a real HTTP request. Empty in
        // every real environment, so this changes nothing there.
        $this->client = new Client($key, [
            'host' => config('services.posthog.host'),
            ...config('services.posthog.options', []),
        ]);

        return $this->client;
    }
}
