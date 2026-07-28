<?php

namespace Database\Factories;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Scheduling\Enums\ScheduleStatus;
use App\Domain\Scheduling\Models\ProductionSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionSchedule>
 */
class ProductionScheduleFactory extends Factory
{
    protected $model = ProductionSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'version' => 1,
            'status' => ScheduleStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduleStatus::Active,
        ]);
    }

    public function superseded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ScheduleStatus::Superseded,
        ]);
    }
}
