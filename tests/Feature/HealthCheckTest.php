<?php

/**
 * See MIGRATION_PLAN.md's Stage 15 ADR on this. Laravel's own `/up`
 * route (bootstrap/app.php's `health: '/up'`) ships with no listener on
 * the DiagnosingHealth event it dispatches — App\Providers\
 * AppServiceProvider registers one that checks the database is actually
 * reachable, since SESSION_DRIVER/CACHE_STORE/QUEUE_CONNECTION all
 * default to `database` and a DB outage would otherwise leave `/up`
 * returning 200 through all of it.
 */
test('the health check passes when the database is reachable', function () {
    $this->get('/up')->assertOk();
});

test('the health check fails when the database is unreachable', function () {
    // Restored before this closure returns, not left broken for
    // RefreshDatabase's own post-test rollback (tests/Pest.php) to trip
    // over — that rollback runs against `database.default` after this
    // closure completes, so it needs the real connection back by then.
    $originalDefault = config('database.default');

    config(['database.connections.unreachable-for-test' => [
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        // Port 1 is a privileged port nothing listens on — the connection
        // attempt fails fast (connection refused) rather than timing out.
        'port' => 1,
        'database' => 'nonexistent',
        'username' => 'nonexistent',
        'password' => '',
    ]]);
    config(['database.default' => 'unreachable-for-test']);

    try {
        $this->get('/up')->assertStatus(500);
    } finally {
        config(['database.default' => $originalDefault]);
    }
});
