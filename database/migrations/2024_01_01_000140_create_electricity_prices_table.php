<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `ElectricityPrice` Prisma model exactly — the
 * day-ahead price feed the recommendation engine depends on. Global
 * reference data, not tenant-owned: no `organization_id` column, matching
 * the source (and absent from v2's RLS policy list for the same reason
 * every other non-tenant-owned table in this port is).
 *
 * No `updated_at` — the source model only has `createdAt`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electricity_prices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('bidding_zone');
            $table->timestamp('hour_start');
            $table->decimal('eur_per_mwh', 8, 2);
            $table->string('source');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['bidding_zone', 'hour_start', 'source']);
            $table->index('hour_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electricity_prices');
    }
};
