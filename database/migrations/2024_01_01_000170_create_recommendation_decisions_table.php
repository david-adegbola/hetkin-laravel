<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `RecommendationDecision` Prisma model exactly.
 * No `organization_id` — scoped via `recommendation.optimization_run`,
 * same reasoning as the recommendations migration. Exactly one decision
 * per recommendation (`recommendation_id` unique).
 *
 * No `updated_at` — the source model only has `decidedAt`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_decisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('recommendation_id')->unique()->constrained('recommendations')->cascadeOnDelete();
            $table->foreignUlid('decided_by_id')->constrained('users');
            $table->string('decision');
            $table->text('reason')->nullable();
            $table->timestamp('adjusted_start_at')->nullable();
            $table->timestamp('adjusted_end_at')->nullable();
            $table->timestamp('decided_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_decisions');
    }
};
