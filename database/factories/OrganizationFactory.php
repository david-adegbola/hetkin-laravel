<?php

namespace Database\Factories;

use App\Domain\Organizations\Enums\OrganizationStatus;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'status' => OrganizationStatus::Pilot,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrganizationStatus::Active,
        ]);
    }
}
