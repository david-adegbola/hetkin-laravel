<?php

namespace Database\Factories;

use App\Domain\Metering\Models\Site;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Domain\Scheduling\Models\ScheduleBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleBlock>
 */
class ScheduleBlockFactory extends Factory
{
    protected $model = ScheduleBlock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+2 weeks');

        return [
            'schedule_id' => ProductionSchedule::factory(),
            'site_id' => Site::factory(),
            'asset_label' => fake()->randomElement(['Line A', 'Extruder 2', 'Cold store', 'Kiln 1']),
            'start_at' => $start,
            'end_at' => (clone $start)->modify('+4 hours'),
            'estimated_kwh' => fake()->randomFloat(3, 10, 500),
        ];
    }

    public function withoutEstimatedKwh(): static
    {
        return $this->state(fn (array $attributes) => [
            'estimated_kwh' => null,
        ]);
    }
}
