<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `SavingsAssessment` Prisma model exactly. Has a
 * direct `organization_id` and is in v2's RLS policy list, so it gets
 * BelongsToOrganization. `baseline_id` has no ON DELETE clause in the
 * source (Prisma defaults to RESTRICT) — a locked baseline should never be
 * removable out from under an assessment that cites it.
 *
 * No `updated_at` — the source model only has `generatedAt`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_assessments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('baseline_id')->constrained('baselines');
            $table->string('status')->default('DRAFT');
            $table->integer('current_annual_cost_cents');
            $table->integer('optimized_annual_cost_cents');
            $table->integer('estimated_annual_savings_cents');
            $table->integer('hetkin_share_cents');
            $table->integer('net_customer_savings_cents');
            $table->boolean('is_provisional')->default(true);
            $table->integer('sample_day_count')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->string('report_pdf_url')->nullable();
            $table->timestamp('generated_at')->useCurrent();

            $table->index('organization_id');
            $table->index('baseline_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_assessments');
    }
};
