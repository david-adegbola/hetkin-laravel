<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `Organization` Prisma model (prisma/schema.prisma)
 * field-for-field, with one deliberate omission: `pilotApplicationId` (the
 * nullable FK to `PilotApplication`) is left out — that table doesn't exist
 * until Stage 8's pilot funnel, and Organizations doesn't need it to
 * function (an org can exist without ever having gone through a pilot
 * application, e.g. one created directly by an admin). Added via a
 * follow-up migration when Stage 8 builds that table, not stubbed here.
 *
 * `status`/`process_type` are plain strings with app-level PHP backed-enum
 * casts (App\Domain\Organizations\Enums\OrganizationStatus/ProcessType),
 * not native Postgres ENUM types — same reasoning as MIGRATION_PLAN.md
 * ADR-11 (admin_users.role): portable across the pgsql/sqlite connections
 * this app runs on, fully constrained at the application boundary either
 * way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->string('status')->default('PILOT');
            $table->unsignedInteger('base_fee_cents')->nullable();
            $table->decimal('revenue_share_percent', 5, 2)->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('process_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
