<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `MeteringPoint` Prisma model exactly. No
 * `organization_id` column — the source schema doesn't have one either;
 * tenant scoping for this table is via its `site` relation only, matching
 * v2's own RLS policy list (prisma/migrations/20260724230000_row_level_
 * security_policies), which covers `site` but deliberately not
 * `metering_point` or `meter_reading` for the same reason (see
 * MIGRATION_PLAN.md ADR-29).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metering_points', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('label');
            $table->string('datahub_metering_point_id')->nullable()->unique();
            $table->string('data_source')->default('MANUAL_UPLOAD');
            $table->boolean('connection_healthy')->default(true);
            $table->timestamp('last_reading_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metering_points');
    }
};
