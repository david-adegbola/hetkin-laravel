<?php

namespace Database\Factories;

use App\Domain\Billing\Enums\SavingsAssessmentStatus;
use App\Domain\Billing\Models\Baseline;
use App\Domain\Billing\Models\SavingsAssessment;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsAssessment>
 */
class SavingsAssessmentFactory extends Factory
{
    protected $model = SavingsAssessment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'baseline_id' => Baseline::factory(),
            'status' => SavingsAssessmentStatus::Draft,
            'current_annual_cost_cents' => 10_950_000,
            'optimized_annual_cost_cents' => 8_190_600,
            'estimated_annual_savings_cents' => 2_759_400,
            'hetkin_share_cents' => 275_940,
            'net_customer_savings_cents' => 2_483_460,
            'is_provisional' => true,
            'sample_day_count' => 5,
            'period_start' => now()->subDays(5),
            'period_end' => now(),
        ];
    }
}
