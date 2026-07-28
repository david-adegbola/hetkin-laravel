<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `VerificationRecord` Prisma model exactly. Has
 * a direct `organization_id` and is in v2's RLS policy list, so it gets
 * BelongsToOrganization. The `[organization_id, period_start, period_end]`
 * unique constraint is the upsert key the monthly verification job relies
 * on — re-running for the same period updates figures in place rather than
 * creating a duplicate row (see App\Domain\Billing\Actions\
 * RunVerificationAction).
 *
 * No `updated_at` — the source model only has `createdAt`/`reviewedAt`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('baseline_id')->constrained('baselines');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->integer('baseline_cost_cents');
            $table->integer('actual_cost_cents');
            $table->integer('verified_savings_cents');
            $table->decimal('variance_percent', 5, 2);
            $table->boolean('variance_flagged')->default(false);
            $table->string('methodology_version')->default('provisional-period-comparison-v1');
            $table->json('assumptions')->nullable();
            $table->string('review_status')->default('SHADOW_PENDING_REVIEW');
            $table->foreignUlid('reviewed_by_admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['organization_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_records');
    }
};
