<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_assessments', function (Blueprint $table) {
            // A customer report must keep the exact recommendation run it
            // was generated with. Without this link, reopening an older
            // assessment could mix its estimate with a newer schedule.
            $table->foreignUlid('optimization_run_id')
                ->nullable()
                ->after('baseline_id')
                ->constrained('optimization_runs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('savings_assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('optimization_run_id');
        });
    }
};
