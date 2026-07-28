<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The follow-up migration `audit_log_entries`'s own creation migration
 * (2024_01_01_000020) said would land here: that table has to exist before
 * Organizations does (every domain may need to write to it from Stage 1
 * onward), so its `organization_id` column was created without a foreign
 * key constraint at the time. Now that `organizations` exists, add it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_log_entries', function (Blueprint $table) {
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_log_entries', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });
    }
};
