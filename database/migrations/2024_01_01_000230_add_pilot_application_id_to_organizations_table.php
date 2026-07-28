<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `pilot_application_id` column the organizations migration's own
 * comment forecast: "Added via a follow-up migration when Stage 8 builds
 * that table." Nullable + unique, matching the Next.js app's
 * `pilotApplicationId String? @unique` — most organizations (any created
 * directly rather than through the pilot funnel) have no pilot
 * application at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignUlid('pilot_application_id')->nullable()->unique()->constrained('pilot_applications');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pilot_application_id');
        });
    }
};
