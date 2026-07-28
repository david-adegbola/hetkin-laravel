<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `PilotApplication` Prisma model exactly. NOT
 * organization-scoped — no `organization_id` column, absent from v2's own
 * RLS policy list (see MIGRATION_PLAN.md's Stage 8 ADR): a pilot
 * application exists precisely because no Organization exists yet for the
 * applicant.
 *
 * `reviewer_notes` is a real column, ported for schema fidelity, even
 * though no code path in v2 reads or writes it (no form field, no admin
 * action sets it) — not dead code introduced by this port, dead code
 * inherited from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pilot_applications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('status')->default('PENDING');

            $table->string('company_name');
            $table->string('process_type');
            $table->integer('estimated_annual_spend_eur');
            $table->string('current_scheduling_method');
            $table->text('win_condition')->nullable();

            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone')->nullable();

            $table->integer('icp_score')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->foreignUlid('reviewed_by_admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pilot_applications');
    }
};
