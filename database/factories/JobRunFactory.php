<?php

namespace Database\Factories;

use App\Domain\Jobs\Enums\JobRunStatus;
use App\Domain\Jobs\Models\JobRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobRun>
 */
class JobRunFactory extends Factory
{
    protected $model = JobRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_name' => 'run-verification',
            'status' => JobRunStatus::Running,
            'triggered_by' => 'cron',
            'started_at' => now(),
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobRunStatus::Succeeded,
            'completed_at' => now(),
            'summary' => ['processed' => fake()->numberBetween(1, 50)],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobRunStatus::Failed,
            'completed_at' => now(),
            'error_message' => 'Something went wrong.',
        ]);
    }
}
