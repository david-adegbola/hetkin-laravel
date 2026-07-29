<?php

namespace Database\Factories;

use App\Domain\Metering\Models\Site;
use App\Domain\Scheduling\Enums\LabourClassification;
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
            // Deliberately opposite of the migration's DB-level default
            // (LABOUR_BOUND, conservative for real/unclassified data) —
            // this factory's own default represents the common
            // already-optimization-eligible case existing tests build
            // against, matching every other factory's "happy path"
            // convention here (e.g. MemberFactory's role, InvitationFactory's
            // status).
            'labour_classification' => LabourClassification::Unmanned,
        ];
    }

    public function withoutEstimatedKwh(): static
    {
        return $this->state(fn (array $attributes) => [
            'estimated_kwh' => null,
        ]);
    }

    public function unmanned(): static
    {
        return $this->state(fn (array $attributes) => [
            'labour_classification' => LabourClassification::Unmanned,
        ]);
    }

    public function labourBound(): static
    {
        return $this->state(fn (array $attributes) => [
            'labour_classification' => LabourClassification::LabourBound,
        ]);
    }
}
