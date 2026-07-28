<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `ScheduleBlock` Prisma model exactly. No
 * `organization_id` — like `metering_points`/`meter_readings`
 * (MIGRATION_PLAN.md ADR-29), this table is absent from v2's own RLS
 * policy list (which covers `production_schedule` but not
 * `schedule_block`), so it isn't given BelongsToOrganization either;
 * tenant scoping is via its `schedule` relation only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_blocks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('schedule_id')->constrained('production_schedules')->cascadeOnDelete();
            $table->foreignUlid('site_id')->constrained('sites');
            $table->string('asset_label');
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->string('description')->nullable();
            $table->decimal('estimated_kwh', 10, 3)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('schedule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_blocks');
    }
};
