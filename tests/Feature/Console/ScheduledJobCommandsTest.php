<?php

use App\Domain\Alerts\Actions\GenerateAlertsAction;
use App\Domain\Billing\Actions\RunVerificationAction;
use App\Domain\Jobs\Enums\JobRunStatus;
use App\Domain\Jobs\Models\JobRun;
use App\Domain\Pricing\Actions\IngestPricesAction;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

test('jobs:ingest-prices tracks a succeeded run as cron', function () {
    $this->mock(IngestPricesAction::class, function ($mock) {
        $mock->shouldReceive('execute')->once()->andReturn(['entriesFetched' => 48, 'rowsWritten' => 48]);
    });

    $this->artisan('jobs:ingest-prices')->assertExitCode(0);

    $jobRun = JobRun::query()->where('job_name', 'ingest-prices')->latest('started_at')->first();
    expect($jobRun->triggered_by)->toBe('cron');
    expect($jobRun->status)->toBe(JobRunStatus::Succeeded);
});

test('jobs:ingest-prices exits non-zero and logs on failure, without throwing', function () {
    Log::shouldReceive('error')->once();
    $this->mock(IngestPricesAction::class, function ($mock) {
        $mock->shouldReceive('execute')->once()->andThrow(new RuntimeException('ENTSO-E blew up'));
    });

    $this->artisan('jobs:ingest-prices')->assertExitCode(1);

    $jobRun = JobRun::query()->where('job_name', 'ingest-prices')->latest('started_at')->first();
    expect($jobRun->status)->toBe(JobRunStatus::Failed);
});

test('jobs:generate-alerts tracks a succeeded run as cron', function () {
    $this->mock(GenerateAlertsAction::class, function ($mock) {
        $mock->shouldReceive('execute')->once()->andReturn(['organizationsChecked' => 0, 'alertsCreated' => 0, 'results' => []]);
    });

    $this->artisan('jobs:generate-alerts')->assertExitCode(0);

    $jobRun = JobRun::query()->where('job_name', 'generate-alerts')->latest('started_at')->first();
    expect($jobRun->triggered_by)->toBe('cron');
    expect($jobRun->status)->toBe(JobRunStatus::Succeeded);
});

test('jobs:run-verification tracks a succeeded run as cron', function () {
    $this->mock(RunVerificationAction::class, function ($mock) {
        $mock->shouldReceive('execute')->once()->andReturn(['organizationsConsidered' => 0, 'results' => []]);
    });

    $this->artisan('jobs:run-verification')->assertExitCode(0);

    $jobRun = JobRun::query()->where('job_name', 'run-verification')->latest('started_at')->first();
    expect($jobRun->triggered_by)->toBe('cron');
    expect($jobRun->status)->toBe(JobRunStatus::Succeeded);
});

test('the three jobs are scheduled matching vercel.json exactly', function () {
    $schedule = app(Schedule::class);
    $events = collect($schedule->events())->keyBy(fn ($event) => trim(str_replace("'artisan'", '', $event->command)));

    $ingest = $events->first(fn ($event) => str_contains($event->command, 'jobs:ingest-prices'));
    $alerts = $events->first(fn ($event) => str_contains($event->command, 'jobs:generate-alerts'));
    $verification = $events->first(fn ($event) => str_contains($event->command, 'jobs:run-verification'));

    expect($ingest->expression)->toBe('0 5 * * *');
    expect($alerts->expression)->toBe('0 6 * * *');
    expect($verification->expression)->toBe('0 3 1 * *');
});
