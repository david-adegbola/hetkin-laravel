<?php

namespace Database\Factories;

use App\Domain\Optimization\Enums\OptimizationStatus;
use App\Domain\Optimization\Enums\OptimizationTrigger;
use App\Domain\Optimization\Models\OptimizationRun;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Scheduling\Models\ProductionSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OptimizationRun>
 */
class OptimizationRunFactory extends Factory
{
    protected $model = OptimizationRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'schedule_id' => ProductionSchedule::factory(),
            'trigger' => OptimizationTrigger::Manual,
            'status' => OptimizationStatus::Completed,
            'methodology_version' => 'provisional-window-shift-v1',
            'started_at' => now(),
            'completed_at' => now(),
        ];
    }
}
