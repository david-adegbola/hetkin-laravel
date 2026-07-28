<?php

use App\Domain\Jobs\Enums\JobRunStatus;
use App\Domain\Jobs\Models\JobRun;
use App\Domain\Jobs\Services\JobRunTracker;

test('records a succeeded run with the callback result as summary', function () {
    $jobRun = app(JobRunTracker::class)->track('ingest-prices', 'cron', fn () => ['entriesFetched' => 48, 'rowsWritten' => 48]);

    expect($jobRun->status)->toBe(JobRunStatus::Succeeded);
    expect($jobRun->job_name)->toBe('ingest-prices');
    expect($jobRun->triggered_by)->toBe('cron');
    expect($jobRun->completed_at)->not->toBeNull();
    expect($jobRun->summary)->toBe(['entriesFetched' => 48, 'rowsWritten' => 48]);
    expect($jobRun->error_message)->toBeNull();
});

test('records a failed run with the exception message and re-throws', function () {
    expect(fn () => app(JobRunTracker::class)->track('generate-alerts', 'cron', function () {
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class, 'boom');

    $jobRun = JobRun::query()->latest('started_at')->first();
    expect($jobRun->status)->toBe(JobRunStatus::Failed);
    expect($jobRun->error_message)->toBe('boom');
    expect($jobRun->summary)->toBeNull();
    expect($jobRun->completed_at)->not->toBeNull();
});

test('creates the run RUNNING before the callback executes', function () {
    app(JobRunTracker::class)->track('run-verification', 'cron', function () {
        $running = JobRun::query()->latest('started_at')->first();
        expect($running->status)->toBe(JobRunStatus::Running);
        expect($running->completed_at)->toBeNull();

        return [];
    });
});
