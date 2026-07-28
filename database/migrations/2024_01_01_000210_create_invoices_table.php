<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the Next.js app's `Invoice` Prisma model exactly. Has a direct
 * `organization_id` and is in v2's RLS policy list, so it gets
 * BelongsToOrganization. `verification_record_id` is unique — at most one
 * Invoice per VerificationRecord, matching the idempotency check in
 * App\Domain\Billing\Actions\GenerateInvoiceAction.
 *
 * `stripe_invoice_id` is a schema-only placeholder, ported unchanged from
 * v2: no code anywhere calls the Stripe API. "Payment" is an admin
 * manually setting `paid_at`/`payment_note` — see MarkInvoicePaidAction.
 *
 * No `updated_at` — the source model only has `createdAt` plus the
 * lifecycle timestamps below.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignUlid('verification_record_id')->unique()->constrained('verification_records');
            $table->string('stripe_invoice_id')->nullable();
            $table->integer('base_fee_cents');
            $table->integer('savings_share_cents');
            $table->integer('total_cents');
            $table->string('status')->default('DRAFT');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('payment_note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
