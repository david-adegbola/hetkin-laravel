<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only, hash-chained audit log — mirrors the Next.js app's
 * `AuditLogEntry` Prisma model exactly (see App\Domain\Audit\Services\
 * AuditLogService, the only class permitted to write here).
 *
 * `organization_id` has no foreign key constraint yet: Organizations
 * doesn't exist as a table until Stage 3. The constraint is added there via
 * a follow-up migration (`Schema::table('audit_log_entries')->foreign(...)`),
 * not here — this table needs to exist before Organizations does, since
 * every domain from here on may need to write to it.
 *
 * No `updated_at`: matches the original's deliberate append-only shape —
 * an audit entry is never updated after creation, only ever superseded by
 * a later entry.
 *
 * `created_at` is microsecond precision (6), not the default 0: the hash
 * chain's integrity check (AuditChainIntegrityChecker::verify()) recomputes
 * each entry's hash from the value read back out of this column, so
 * whatever precision AuditLogService::record() used to compute the
 * original hash MUST survive the round trip through storage — a
 * second-precision column would silently truncate `created_at` for any two
 * entries written within the same second, making the recomputed hash
 * diverge from the one actually stored even though nothing was tampered
 * with. See App\Domain\Audit\Models\AuditLogEntry's matching cast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('organization_id')->nullable();
            $table->string('actor_type'); // App\Domain\Audit\Enums\AuditActorType
            $table->string('actor_id');
            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_id');
            $table->jsonb('metadata')->nullable();
            $table->char('previous_hash', 64);
            $table->char('hash', 64);
            $table->timestamp('created_at', precision: 6)->useCurrent();

            $table->index(['organization_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log_entries');
    }
};
