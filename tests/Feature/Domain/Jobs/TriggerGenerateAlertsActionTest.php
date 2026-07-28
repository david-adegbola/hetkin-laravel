<?php

use App\Domain\Alerts\Actions\GenerateAlertsAction;
use App\Domain\Jobs\Actions\TriggerGenerateAlertsAction;
use App\Domain\Jobs\Enums\JobRunStatus;
use App\Domain\Jobs\Models\JobRun;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Log;

test('creates a succeeded job run triggered by the admin id', function () {
    $admin = AdminUser::factory()->create();

    app(TriggerGenerateAlertsAction::class)->execute($admin);

    $jobRun = JobRun::query()->latest('started_at')->first();
    expect($jobRun->job_name)->toBe('generate-alerts');
    expect($jobRun->triggered_by)->toBe($admin->id);
    expect($jobRun->status)->toBe(JobRunStatus::Succeeded);
    expect($jobRun->summary['organizationsChecked'])->toBe(0);
});

test('a failed run is recorded but the exception is swallowed, not propagated', function () {
    Log::shouldReceive('error')->once();
    $admin = AdminUser::factory()->create();
    $this->mock(GenerateAlertsAction::class, function ($mock) {
        $mock->shouldReceive('execute')->once()->andThrow(new RuntimeException('generate-alerts blew up'));
    });

    app(TriggerGenerateAlertsAction::class)->execute($admin);

    $jobRun = JobRun::query()->latest('started_at')->first();
    expect($jobRun->status)->toBe(JobRunStatus::Failed);
    expect($jobRun->error_message)->toBe('generate-alerts blew up');
});
