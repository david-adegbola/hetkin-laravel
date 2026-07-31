<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings_assessments', function (Blueprint $table) {
            // Captures the exact customer-facing view model at generation
            // time. Historic rows remain null and use the legacy live-data
            // report loader, while new reports stay reproducible if prices,
            // meter data, schedules, recommendations, or pricing change.
            $table->json('report_snapshot')->nullable()->after('report_pdf_url');
        });
    }

    public function down(): void
    {
        Schema::table('savings_assessments', function (Blueprint $table) {
            $table->dropColumn('report_snapshot');
        });
    }
};
