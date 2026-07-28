<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `ProductionSchedule` Prisma model exactly.
 * Versioned, never overwritten — every submission is a new row, so "what
 * did the schedule look like when this recommendation was made" is always
 * reconstructable. Has a direct `organization_id`, so it's scoped by
 * BelongsToOrganization (confirmed in v2's own RLS policy list, which
 * includes `production_schedule`) — unlike `schedule_blocks`, see that
 * migration's comment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status')->default('DRAFT');
            $table->foreignUlid('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['organization_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_schedules');
    }
};
