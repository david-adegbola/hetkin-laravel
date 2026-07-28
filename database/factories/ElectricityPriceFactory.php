<?php

namespace Database\Factories;

use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Models\ElectricityPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ElectricityPrice>
 */
class ElectricityPriceFactory extends Factory
{
    protected $model = ElectricityPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bidding_zone' => BiddingZone::FI,
            'hour_start' => fake()->dateTimeBetween('-7 days', '+7 days'),
            'eur_per_mwh' => fake()->randomFloat(2, -10, 200),
            'source' => PriceSource::Entsoe,
        ];
    }
}
