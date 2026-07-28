<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `Alert` Prisma model exactly. Has a direct
 * `organization_id` and is in v2's RLS policy list, so it gets
 * BelongsToOrganization. `read_at` exists purely for the 20h dedupe
 * window (App\Domain\Alerts\Actions\CreateAlertAction) and the two
 * "unread count" reads (portal dashboard, admin overview) — v2 never
 * writes to it anywhere (no "mark as read" feature exists), and this port
 * doesn't add one either; a permanently-null `read_at` is the expected,
 * matching state for every row.
 *
 * No `updated_at` — the source model only has `createdAt`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('type');
            $table->string('severity');
            $table->string('title');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
