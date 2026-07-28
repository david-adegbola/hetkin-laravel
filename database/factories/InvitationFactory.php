<?php

namespace Database\Factories;

use App\Domain\Organizations\Enums\OrgRole;
use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => OrgRole::Viewer,
            'status' => 'pending',
            'invited_by_user_id' => User::factory(),
            // Matches Better Auth's org plugin config (src/lib/auth.ts's
            // invitationExpiresIn: 60 * 60 * 24 * 7 — seven days).
            'expires_at' => now()->addDays(7),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
