<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Password::defaults()'s uncompromised() check (AppServiceProvider)
        // calls out to the real Have I Been Pwned API — faked globally here
        // so no test suite run ever depends on network access or that
        // service's availability. An empty response body means "not found
        // in the breach corpus," i.e. the check passes.
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response('', 200),
        ]);
    }
}
