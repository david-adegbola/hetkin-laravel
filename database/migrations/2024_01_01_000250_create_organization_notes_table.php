<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `OrganizationNote` Prisma model exactly. Has a
 * direct `organization_id` and is in v2's RLS policy list (confirmed
 * alongside Stage 3's own scoping determination), so it gets
 * BelongsToOrganization. Also tracks the authoring admin
 * (`author_admin_user_id`) — the one model in this port referencing both
 * a tenant and an internal AdminUser.
 *
 * Append-only in v2 — no delete action exists anywhere for notes — but
 * that's an application-layer convention, not enforced at the DB/model
 * level here (unlike AuditLogEntry, which really is structurally
 * append-only for hash-chain integrity reasons).
 *
 * No `updated_at` — the source model only has `createdAt`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('author_admin_user_id')->constrained('admin_users');
            $table->string('category')->default('GENERAL');
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_notes');
    }
};
