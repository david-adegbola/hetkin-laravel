<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `JobRun` Prisma model exactly. NOT
 * organization-scoped — a job run isn't owned by any one tenant. No
 * `updated_at` — the source model only has `startedAt`/`completedAt`.
 *
 * `triggered_by` is a plain string, not a foreign key — matches the
 * source's own `String` (holds either the literal `"cron"` or an
 * AdminUser's id), same "no @relation in the source, so none added here
 * either" reasoning as `invitations.invited_by_user_id`
 * (MIGRATION_PLAN.md ADR-47's predecessor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('job_name');
            $table->string('status')->default('RUNNING');
            $table->string('triggered_by');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();

            $table->index(['job_name', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_runs');
    }
};
