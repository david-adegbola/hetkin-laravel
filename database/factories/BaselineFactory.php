<?php

namespace Database\Factories;

use App\Domain\Billing\Enums\BaselineStatus;
use App\Domain\Billing\Models\Baseline;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Baseline>
 */
class BaselineFactory extends Factory
{
    protected $model = Baseline::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'methodology_version' => 'provisional-heuristic-v1',
            'status' => BaselineStatus::Draft,
            'assumptions' => ['flexPct' => 0.3, 'cheapHoursPerDay' => 3],
            'baseline_annual_cost_cents' => 10_950_000,
        ];
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BaselineStatus::Locked,
            'locked_at' => now(),
        ]);
    }
}
