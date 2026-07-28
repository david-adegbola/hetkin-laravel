<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Narrows `metadata` from `jsonb` to `json` — discovered via a live
 * Postgres smoke test in Stage 9 (SQLite's test suite can't catch this:
 * SQLite has no native jsonb type, so `jsonb()` migrations there just
 * produce a plain TEXT column that round-trips bytes exactly, masking the
 * bug entirely).
 *
 * On real Postgres, `jsonb` decomposes a JSON value into a binary form and
 * reconstitutes it on read — canonicalizing whitespace AND reordering
 * object keys (observed: `{"category":"GENERAL","pinned":true}` written,
 * `{"pinned": true, "category": "GENERAL"}` read back). App\Domain\Audit\
 * Services\AuditLogService::computeHash() re-serializes `metadata` via
 * `json_encode()` using the DTO's original key order every time — at write
 * time that matches what's about to be stored; at verify time
 * (AuditChainIntegrityChecker::verify(), reading metadata back out of the
 * jsonb column) it no longer does, so every entry with non-empty metadata
 * fails integrity verification despite nothing being tampered with.
 * Postgres's plain `json` type stores the input text verbatim — same
 * "the stored representation must byte-for-byte match what produced the
 * hash" reasoning the creating migration already applies to `created_at`'s
 * precision, just for a different column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_log_entries', function (Blueprint $table) {
            $table->json('metadata')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_log_entries', function (Blueprint $table) {
            $table->jsonb('metadata')->nullable()->change();
        });
    }
};
