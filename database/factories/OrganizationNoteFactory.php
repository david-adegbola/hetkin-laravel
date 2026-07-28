<?php

namespace Database\Factories;

use App\Domain\Organizations\Enums\OrganizationNoteCategory;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationNote;
use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationNote>
 */
class OrganizationNoteFactory extends Factory
{
    protected $model = OrganizationNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'author_admin_user_id' => AdminUser::factory(),
            'category' => OrganizationNoteCategory::General,
            'body' => fake()->paragraph(),
            'pinned' => false,
            'created_at' => now(),
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => ['pinned' => true]);
    }
}
