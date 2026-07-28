<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `MeterReading` Prisma model exactly. No
 * `organization_id` — see the metering_points migration's comment; same
 * reasoning applies here, one level further removed.
 *
 * The source schema's comment documents an intent to monthly-partition
 * this table in production via a raw-SQL migration alongside this one —
 * but no such migration actually exists anywhere in the source repository
 * (confirmed: no `*partition*` migration, no PARTITION DDL in any
 * migration.sql). That's aspirational documentation, not implemented
 * behavior, so it isn't ported here either — see MIGRATION_PLAN.md ADR-29.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('metering_point_id')->constrained('metering_points')->cascadeOnDelete();
            $table->timestamp('hour_start');
            $table->decimal('consumption_kwh', 10, 3);
            $table->string('source');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['metering_point_id', 'hour_start']);
            $table->index('hour_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_readings');
    }
};
