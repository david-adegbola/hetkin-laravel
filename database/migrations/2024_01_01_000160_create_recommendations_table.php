<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `Recommendation` Prisma model exactly. No
 * `organization_id` — like `schedule_blocks`/`meter_readings`
 * (MIGRATION_PLAN.md ADR-29/32), absent from v2's own RLS policy list;
 * tenant scoping is via the `optimization_run` relation only.
 *
 * No `updated_at` — the source model only has `createdAt`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('optimization_run_id')->constrained('optimization_runs')->cascadeOnDelete();
            $table->foreignUlid('schedule_block_id')->nullable()->constrained('schedule_blocks')->nullOnDelete();
            $table->timestamp('proposed_start_at');
            $table->timestamp('proposed_end_at');
            $table->integer('projected_savings_cents');
            $table->string('delivery_risk');
            $table->text('rationale');
            $table->text('business_impact')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('optimization_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
