<?php

namespace Database\Seeders;

use App\Domain\Auth\Enums\AdminRole;
use App\Domain\Organizations\Enums\OrgRole;
use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local-dev only — never run in production (`php artisan db:seed` is never
 * part of the deploy pipeline; this exists purely so `migrate:fresh --seed`
 * leaves a developer with something loggable-into). Creates one account per
 * realm (README.md's "Test accounts" table is the source of truth for
 * these exact credentials):
 *
 *  - one active AdminUser (full `Admin` role, so every admin-console page
 *    is reachable — Team management included)
 *  - one active-status Organization with one customer User as its Owner
 *
 * Deliberately does NOT seed Sites, MeteringPoints, ScheduleBlocks, or any
 * downstream Optimization/Billing data — a brand-new organization showing
 * empty states everywhere is itself correct, documented behavior (see
 * resources/js/Pages/Marketing/Beta.tsx's own copy: "Everything shows zero
 * or empty at first — that's expected on a brand-new account, not a bug").
 * Populate further data by hand through the portal UI (CSV upload, adding
 * schedule blocks) to exercise those flows for real, rather than seeding
 * fabricated data that would let their forms go untested.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        AdminUser::factory()->create([
            'name' => 'Dev Admin',
            'email' => 'admin@hetkin.test',
            'password' => Hash::make('password'),
            'role' => AdminRole::Admin,
            'active' => true,
        ]);

        $organization = Organization::factory()->active()->create([
            'name' => 'Acme Manufacturing Oy',
        ]);

        $owner = User::factory()->create([
            'name' => 'Dev Owner',
            'email' => 'owner@hetkin.test',
            'password' => Hash::make('password'),
        ]);

        Member::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => OrgRole::Owner,
        ]);
    }
}
