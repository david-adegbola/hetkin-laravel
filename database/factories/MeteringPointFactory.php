<?php

namespace Database\Factories;

use App\Domain\Metering\Enums\MeterDataSource;
use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeteringPoint>
 */
class MeteringPointFactory extends Factory
{
    protected $model = MeteringPoint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'label' => 'Manual upload',
            'data_source' => MeterDataSource::ManualUpload,
            'connection_healthy' => true,
        ];
    }

    public function datahub(): static
    {
        return $this->state(fn (array $attributes) => [
            'label' => 'Datahub',
            'datahub_metering_point_id' => fake()->unique()->numerify('###################'),
            'data_source' => MeterDataSource::Datahub,
        ]);
    }
}
