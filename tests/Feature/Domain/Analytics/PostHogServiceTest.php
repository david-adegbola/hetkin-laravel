<?php

use App\Domain\Analytics\Services\PostHogService;
use Illuminate\Support\Facades\Log;

function posthogTestFile(): string
{
    return sys_get_temp_dir().'/posthog-test-'.uniqid().'.log';
}

function readPostHogCaptures(string $file): array
{
    if (! file_exists($file)) {
        return [];
    }

    $lines = array_filter(explode("\n", file_get_contents($file)));

    return array_map(fn (string $line) => json_decode($line, true), $lines);
}

test('is a silent no-op when no key is configured', function () {
    config(['services.posthog.key' => null]);

    app(PostHogService::class)->capture('some_event', 'user-1', ['foo' => 'bar']);

    expect(true)->toBeTrue(); // reaching here without throwing is the assertion
});

test('captures an event with the distinctId, event name, and properties', function () {
    $file = posthogTestFile();
    config([
        'services.posthog.key' => 'test-key',
        'services.posthog.options' => ['consumer' => 'file', 'filename' => $file],
    ]);

    app(PostHogService::class)->capture('pilot_application_submitted', 'contact@example.test', [
        'applicationId' => 'app_123',
        'icpScore' => 80,
    ]);

    $captures = readPostHogCaptures($file);
    expect($captures)->toHaveCount(1);
    expect($captures[0]['event'])->toBe('pilot_application_submitted');
    expect($captures[0]['distinctId'] ?? $captures[0]['distinct_id'] ?? null)->toBe('contact@example.test');
    expect($captures[0]['properties']['applicationId'])->toBe('app_123');
    expect($captures[0]['properties']['icpScore'])->toBe(80);

    @unlink($file);
});

test('a capture failure is logged, not thrown, matching the "never block the real work" posture', function () {
    Log::shouldReceive('error')->once()->with(
        '[analytics] failed to capture "some_event"',
        Mockery::on(fn ($context) => $context['exception'] instanceof Throwable),
    );
    config([
        'services.posthog.key' => 'test-key',
        // An invalid option the SDK validates eagerly and rejects with an
        // InvalidArgumentException — a deterministic way to exercise
        // capture()'s catch block without a real network call or relying
        // on filesystem failure behavior the SDK might swallow silently.
        'services.posthog.options' => ['flag_definition_cache_provider' => 'not-a-valid-provider'],
    ]);

    app(PostHogService::class)->capture('some_event', 'user-1');
});
