<?php

use App\Domain\Jobs\Actions\TriggerIngestPricesAction;
use App\Domain\Jobs\Enums\JobRunStatus;
use App\Domain\Jobs\Models\JobRun;
use App\Domain\Pricing\Actions\IngestPricesAction;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Log;

test('creates a succeeded job run triggered by the admin id', function () {
    $admin = AdminUser::factory()->create();
    $this->mock(IngestPricesAction::class, function ($mock) {
        $mock->shouldReceive('execute')->once()->andReturn(['entriesFetched' => 48, 'rowsWritten' => 48]);
    });

    app(TriggerIngestPricesAction::class)->execute($admin);

    $jobRun = JobRun::query()->latest('started_at')->first();
    expect($jobRun->job_name)->toBe('ingest-prices');
    expect($jobRun->triggered_by)->toBe($admin->id);
    expect($jobRun->status)->toBe(JobRunStatus::Succeeded);
    expect($jobRun->summary)->toBe(['entriesFetched' => 48, 'rowsWritten' => 48]);
});

test('a failed run is recorded but the exception is swallowed, not propagated', function () {
    Log::shouldReceive('error')->once();
    $admin = AdminUser::factory()->create();
    $this->mock(IngestPricesAction::class, function ($mock) {
        $mock->shouldReceive('execute')->once()->andThrow(new RuntimeException('ENTSO-E blew up'));
    });

    app(TriggerIngestPricesAction::class)->execute($admin);

    $jobRun = JobRun::query()->latest('started_at')->first();
    expect($jobRun->status)->toBe(JobRunStatus::Failed);
    expect($jobRun->error_message)->toBe('ENTSO-E blew up');
});
