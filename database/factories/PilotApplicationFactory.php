<?php

namespace Database\Factories;

use App\Domain\Organizations\Enums\ProcessType;
use App\Domain\Pilots\Enums\PilotApplicationStatus;
use App\Domain\Pilots\Models\PilotApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PilotApplication>
 */
class PilotApplicationFactory extends Factory
{
    protected $model = PilotApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => PilotApplicationStatus::Pending,
            'company_name' => fake()->company(),
            'process_type' => ProcessType::ColdStorage,
            'estimated_annual_spend_eur' => fake()->numberBetween(10_000, 500_000),
            'current_scheduling_method' => 'Excel',
            'contact_name' => fake()->name(),
            'contact_email' => fake()->unique()->safeEmail(),
            'icp_score' => 80,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PilotApplicationStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PilotApplicationStatus::Rejected,
            'reviewed_at' => now(),
        ]);
    }
}
