<?php

namespace Database\Factories;

use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertType;
use App\Domain\Alerts\Models\Alert;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'type' => AlertType::DataGap,
            'severity' => AlertSeverity::Warning,
            'title' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'read_at' => null,
            'created_at' => now(),
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => ['read_at' => now()]);
    }
}
