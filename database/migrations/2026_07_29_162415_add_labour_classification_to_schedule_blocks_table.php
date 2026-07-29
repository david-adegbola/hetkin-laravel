<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Labour-Aware Shiftable Load Classification (new product scope, not a v2
 * port — see MIGRATION_PLAN.md). DB-level default is deliberately
 * `LABOUR_BOUND`, not `UNMANNED`: every schedule block that existed before
 * this migration, and every future insert that doesn't specify the column
 * explicitly, becomes conservatively ineligible for customer-facing
 * recommendations until a human classifies it — matching the brief's
 * "prefer a conservative default that prevents misleading customer-facing
 * recommendations until the load has been explicitly classified" exactly.
 * Existing rows keep working (nothing reads this column as NOT NULL
 * without a default), they just stop being optimization candidates until
 * reclassified — the intended behavior change, not a migration hazard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_blocks', function (Blueprint $table) {
            $table->string('labour_classification')->default('LABOUR_BOUND')->after('estimated_kwh');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_blocks', function (Blueprint $table) {
            $table->dropColumn('labour_classification');
        });
    }
};
