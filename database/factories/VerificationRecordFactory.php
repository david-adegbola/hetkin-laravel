<?php

namespace Database\Factories;

use App\Domain\Billing\Enums\VerificationReviewStatus;
use App\Domain\Billing\Models\Baseline;
use App\Domain\Billing\Models\VerificationRecord;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VerificationRecord>
 */
class VerificationRecordFactory extends Factory
{
    protected $model = VerificationRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Offset by a random number of months back, not just "last month" —
        // several records for the same organization are routinely created
        // within one test, and period_start/period_end participate in a
        // real unique constraint (organization_id, period_start,
        // period_end), same as the source schema.
        $periodEnd = now()->utc()->startOfMonth()->subMonthsNoOverflow(fake()->numberBetween(0, 1000));
        $periodStart = $periodEnd->copy()->subMonthNoOverflow();

        return [
            'organization_id' => Organization::factory(),
            'baseline_id' => Baseline::factory(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'baseline_cost_cents' => 900_000,
            'actual_cost_cents' => 700_000,
            'verified_savings_cents' => 200_000,
            'variance_percent' => 5.0,
            'variance_flagged' => false,
            'methodology_version' => 'provisional-period-comparison-v1',
            'review_status' => VerificationReviewStatus::ShadowPendingReview,
        ];
    }

    public function shadowConfirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'review_status' => VerificationReviewStatus::ShadowConfirmed,
            'reviewed_at' => now(),
        ]);
    }

    public function liveApproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'review_status' => VerificationReviewStatus::LiveApproved,
            'reviewed_at' => now(),
        ]);
    }

    public function disputed(): static
    {
        return $this->state(fn (array $attributes) => [
            'review_status' => VerificationReviewStatus::Disputed,
            'reviewed_at' => now(),
        ]);
    }
}
