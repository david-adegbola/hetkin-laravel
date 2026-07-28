<?php

namespace Database\Factories;

use App\Domain\Metering\Enums\MeterDataSource;
use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\MeterReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeterReading>
 */
class MeterReadingFactory extends Factory
{
    protected $model = MeterReading::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'metering_point_id' => MeteringPoint::factory(),
            'hour_start' => fake()->dateTimeBetween('-30 days', 'now'),
            'consumption_kwh' => fake()->randomFloat(3, 0, 500),
            'source' => MeterDataSource::ManualUpload,
        ];
    }
}
