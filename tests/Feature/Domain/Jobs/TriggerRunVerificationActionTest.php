<?php

use App\Domain\Billing\Actions\RunVerificationAction;
use App\Domain\Jobs\Actions\TriggerRunVerificationAction;
use App\Domain\Jobs\Enums\JobRunStatus;
use App\Domain\Jobs\Models\JobRun;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Log;

test('creates a succeeded job run triggered by the admin id, with no locked baselines to consider', function () {
    $admin = AdminUser::factory()->create();

    app(TriggerRunVerificationAction::class)->execute($admin);

    $jobRun = JobRun::query()->latest('started_at')->first();
    expect($jobRun->job_name)->toBe('run-verification');
    expect($jobRun->triggered_by)->toBe($admin->id);
    expect($jobRun->status)->toBe(JobRunStatus::Succeeded);
    expect($jobRun->summary['organizationsConsidered'])->toBe(0);
});

test('a failed run is recorded but the exception is swallowed, not propagated', function () {
    Log::shouldReceive('error')->once();
    $admin = AdminUser::factory()->create();
    $this->mock(RunVerificationAction::class, function ($mock) {
        $mock->shouldReceive('execute')->once()->andThrow(new RuntimeException('verification blew up'));
    });

    app(TriggerRunVerificationAction::class)->execute($admin);

    $jobRun = JobRun::query()->latest('started_at')->first();
    expect($jobRun->status)->toBe(JobRunStatus::Failed);
    expect($jobRun->error_message)->toBe('verification blew up');
});
