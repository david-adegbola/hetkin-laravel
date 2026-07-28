<?php

namespace Database\Factories;

use App\Domain\Optimization\Enums\RecommendationDecisionType;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Optimization\Models\RecommendationDecision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecommendationDecision>
 */
class RecommendationDecisionFactory extends Factory
{
    protected $model = RecommendationDecision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recommendation_id' => Recommendation::factory(),
            'decided_by_id' => User::factory(),
            'decision' => RecommendationDecisionType::Accepted,
        ];
    }

    public function rejected(string $reason = 'Conflicts with a delivery commitment.'): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => RecommendationDecisionType::Rejected,
            'reason' => $reason,
        ]);
    }

    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => RecommendationDecisionType::Partial,
            'adjusted_start_at' => now()->addHours(2),
            'adjusted_end_at' => now()->addHours(4),
        ]);
    }
}
