<?php

use App\Domain\Auth\Services\UserAgentParser;

dataset('user_agents', [
    'Chrome on macOS' => [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Chrome',
        'macOS',
    ],
    'Safari on macOS' => [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
        'Safari',
        'macOS',
    ],
    'Firefox on Windows' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Firefox',
        'Windows',
    ],
    'Edge on Windows' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
        'Edge',
        'Windows',
    ],
    'Chrome on Android' => [
        'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        'Chrome',
        'Android',
    ],
    'Safari on iOS' => [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
        'Safari',
        'iOS',
    ],
]);

test('it detects browser and platform', function (string $ua, string $expectedBrowser, string $expectedPlatform) {
    $result = (new UserAgentParser)->parse($ua);

    expect($result['browser'])->toBe($expectedBrowser);
    expect($result['platform'])->toBe($expectedPlatform);
})->with('user_agents');

test('it handles a missing user agent gracefully', function () {
    $result = (new UserAgentParser)->parse(null);

    expect($result['browser'])->toBe('Unknown browser');
    expect($result['platform'])->toBe('Unknown device');
});

test('it handles an unrecognized user agent gracefully', function () {
    $result = (new UserAgentParser)->parse('SomeBot/1.0');

    expect($result['browser'])->toBe('Unknown browser');
    expect($result['platform'])->toBe('Unknown device');
});
