<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin (internal Hetkin staff) realm — mirrors the Next.js app's
 * `AdminUser`/`AdminSession` Prisma models, deliberately separate tables
 * from the customer realm's `users`/`sessions` (MIGRATION_PLAN.md ADR-4).
 *
 * `role` is a plain string with an app-level PHP backed-enum cast
 * (App\Domain\Auth\Enums\AdminRole), not a Postgres-native ENUM type — kept
 * portable across the pgsql (prod) and sqlite (test) connections rather
 * than requiring a real Postgres instance just to run the test suite. The
 * enum still fully constrains valid values at the application boundary; see
 * MIGRATION_PLAN.md ADR-11.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('role');
            // Soft-disable rather than delete, so audit-log actorId
            // attribution never dangles — a deactivated admin's history
            // stays intact, they just can't authenticate or act.
            $table->boolean('active')->default(true);
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('admin_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUlid('user_id')->nullable()->constrained('admin_users')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_sessions');
        Schema::dropIfExists('admin_users');
    }
};
