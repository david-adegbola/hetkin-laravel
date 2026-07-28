<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `Baseline` Prisma model exactly. Has a direct
 * `organization_id` and is in v2's RLS policy list, so it gets
 * BelongsToOrganization.
 *
 * No `updated_at` — the source model only has `createdAt`/`lockedAt`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baselines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('methodology_version');
            $table->string('status')->default('DRAFT');
            $table->json('assumptions');
            $table->integer('baseline_annual_cost_cents');
            $table->timestamp('locked_at')->nullable();
            $table->foreignUlid('locked_by_admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baselines');
    }
};
