<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widens `invited_by_user_id` from `ulid()` (Postgres `char(26)`) to a
 * plain `string()` — Stage 3 typed this column assuming it would only
 * ever hold a real User's ULID, which was true of every invite created so
 * far. Stage 8 introduces admin-originated invites (pilot-application
 * approval), which — matching the Next.js app's own
 * `invitedByUserId: `admin:${adminUser.id}`` convention exactly (see
 * ApprovePilotApplicationAction) — write a value longer than 26
 * characters. The source Prisma schema always typed this column as a
 * bare, unconstrained `String` (see the creating migration's own
 * comment), so `string()` is what it should have been from the start;
 * `char(26)` was an assumption this stage disproves, not a deliberate
 * constraint being loosened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('invited_by_user_id')->change();
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->ulid('invited_by_user_id')->change();
        });
    }
};
