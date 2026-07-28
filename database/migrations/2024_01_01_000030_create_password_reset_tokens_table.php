<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's standard password-reset-token shape, used by the framework's
 * own Password Broker (Password::sendResetLink / Password::reset) — not a
 * hand-rolled token system. Customer realm only (`users` table); the admin
 * realm has no self-serve reset flow yet (out of scope for this module —
 * account-security work here is customer-portal-only per the brief).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
