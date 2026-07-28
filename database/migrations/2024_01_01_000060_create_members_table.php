<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `Member` Prisma model exactly — the
 * organization/user pivot carrying a role, not a plain many-to-many
 * without attributes. No `updated_at`: Prisma's model only has
 * `createdAt`, a membership row is created once and never edited in place
 * (a role change updates the `role` column but the original has no
 * `updatedAt` field to reflect that in either — matches exactly).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('VIEWER');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
