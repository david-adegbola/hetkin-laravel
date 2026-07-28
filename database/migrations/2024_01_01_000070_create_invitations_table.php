<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `Invitation` Prisma model exactly, including
 * one deliberate irregularity: `invited_by_user_id` has NO foreign key
 * constraint — the Prisma schema declares it as a bare scalar
 * (`invitedByUserId String`) with no `@relation` to `User`, so Prisma never
 * generated one either. Preserved as-is rather than "fixed" here; adding a
 * constraint the original never had is a schema change this rewrite
 * doesn't get to make unilaterally.
 *
 * `status` is a plain string (default "pending"), not a backed enum: the
 * Prisma schema itself never defined an enum for it either (unlike
 * `OrgRole`/`OrganizationStatus`) — only "pending" and "accepted" are ever
 * written anywhere in the original codebase, but nothing there enforces
 * that set at the type level, so this port doesn't invent a stricter
 * constraint the source never had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('email');
            $table->string('role')->default('VIEWER');
            $table->string('status')->default('pending');
            $table->ulid('invited_by_user_id');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
