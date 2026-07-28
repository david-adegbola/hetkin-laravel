<?php

namespace Database\Factories;

use App\Domain\Organizations\Enums\OrgRole;
use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    /**
     * `organization_id` is always given explicitly here, never left to
     * BelongsToOrganization's creating-hook fallback: most tests build a
     * Member without any CurrentOrganization bound (that binding is what
     * this model exists to help resolve), so relying on the fallback would
     * make ordinary factory calls throw.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'role' => OrgRole::Viewer,
        ];
    }

    public function role(OrgRole $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
        ]);
    }
}
