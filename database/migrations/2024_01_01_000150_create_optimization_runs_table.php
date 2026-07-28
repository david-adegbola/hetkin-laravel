<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `OptimizationRun` Prisma model exactly. Has a
 * direct `organization_id` and is in v2's RLS policy list, so it gets
 * BelongsToOrganization — unlike `recommendations`/`recommendation_decisions`
 * below it, see those migrations' comments.
 *
 * No `updated_at` — the source model only has `startedAt`/`completedAt`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('optimization_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('schedule_id')->constrained('production_schedules');
            $table->string('trigger');
            $table->string('status')->default('PENDING');
            $table->string('methodology_version');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('optimization_runs');
    }
};
