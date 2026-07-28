<?php

use App\Domain\Jobs\Actions\TriggerBackfillPricesAction;
use App\Domain\Jobs\Enums\JobRunStatus;
use App\Domain\Jobs\Models\JobRun;
use App\Domain\Pricing\Actions\BackfillPricesAction;
use App\Domain\Pricing\DTOs\BackfillChunkProgress;
use App\Domain\Pricing\Enums\PriceSource;
use App\Models\AdminUser;
use Illuminate\Support\Carbon;

test('creates a RUNNING job run up front, then updates it to SUCCEEDED with the result as summary', function () {
    $admin = AdminUser::factory()->create();
    $this->mock(BackfillPricesAction::class, function ($mock) {
        $mock->shouldReceive('execute')->once()->andReturn(['chunkCount' => 1, 'chunksWritten' => 1, 'chunksSkipped' => 0, 'chunksFailed' => 0, 'rowsWritten' => 24, 'failedRanges' => []]);
    });

    $outcome = app(TriggerBackfillPricesAction::class)->execute(
        $admin, PriceSource::Entsoe, Carbon::parse('2026-01-01', 'UTC'), Carbon::parse('2026-01-02', 'UTC'),
    );

    expect($outcome['status'])->toBe('success');
    expect($outcome['result']['rowsWritten'])->toBe(24);

    $jobRun = JobRun::query()->where('job_name', 'backfill-prices')->latest('started_at')->first();
    expect($jobRun->triggered_by)->toBe($admin->id);
    expect($jobRun->status)->toBe(JobRunStatus::Succeeded);
    expect($jobRun->summary['rowsWritten'])->toBe(24);
});

test('the onProgress callback updates the same job run row live, before completion', function () {
    $admin = AdminUser::factory()->create();
    $capturedJobRunId = null;
    $this->mock(BackfillPricesAction::class, function ($mock) use (&$capturedJobRunId) {
        $mock->shouldReceive('execute')->once()->andReturnUsing(function (...$args) use (&$capturedJobRunId) {
            $onProgress = collect($args)->last();
            $onProgress(new BackfillChunkProgress(0, 2, Carbon::parse('2026-01-01', 'UTC'), Carbon::parse('2026-01-02', 'UTC'), 'written', rowsWritten: 10));

            $jobRun = JobRun::query()->where('job_name', 'backfill-prices')->latest('started_at')->first();
            $capturedJobRunId = $jobRun->id;
            expect($jobRun->status)->toBe(JobRunStatus::Running);
            expect($jobRun->summary['chunksProcessed'])->toBe(1);
            expect($jobRun->summary['chunkCount'])->toBe(2);

            return ['chunkCount' => 2, 'chunksWritten' => 2, 'chunksSkipped' => 0, 'chunksFailed' => 0, 'rowsWritten' => 20, 'failedRanges' => []];
        });
    });

    app(TriggerBackfillPricesAction::class)->execute(
        $admin, PriceSource::Entsoe, Carbon::parse('2026-01-01', 'UTC'), Carbon::parse('2026-01-03', 'UTC'),
    );

    expect($capturedJobRunId)->not->toBeNull();
});

test('a thrown exception fails the job run and returns an error outcome, not propagated', function () {
    $admin = AdminUser::factory()->create();
    $this->mock(BackfillPricesAction::class, function ($mock) {
        $mock->shouldReceive('execute')->once()->andThrow(new RuntimeException('backfill blew up'));
    });

    $outcome = app(TriggerBackfillPricesAction::class)->execute(
        $admin, PriceSource::Entsoe, Carbon::parse('2026-01-01', 'UTC'), Carbon::parse('2026-01-02', 'UTC'),
    );

    expect($outcome['status'])->toBe('error');
    expect($outcome['message'])->toBe('backfill blew up');

    $jobRun = JobRun::query()->where('job_name', 'backfill-prices')->latest('started_at')->first();
    expect($jobRun->status)->toBe(JobRunStatus::Failed);
    expect($jobRun->error_message)->toBe('backfill blew up');
});
