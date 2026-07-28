<?php

namespace Database\Factories;

use App\Domain\Optimization\Enums\DeliveryRisk;
use App\Domain\Optimization\Models\OptimizationRun;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Scheduling\Models\ScheduleBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recommendation>
 */
class RecommendationFactory extends Factory
{
    protected $model = Recommendation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+1 week');

        return [
            'optimization_run_id' => OptimizationRun::factory(),
            'schedule_block_id' => ScheduleBlock::factory(),
            'proposed_start_at' => $start,
            'proposed_end_at' => (clone $start)->modify('+2 hours'),
            'projected_savings_cents' => fake()->numberBetween(100, 10000),
            'delivery_risk' => DeliveryRisk::Low,
            'rationale' => 'Shift to a cheaper window.',
        ];
    }
}
