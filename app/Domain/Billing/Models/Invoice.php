<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Organizations\Concerns\BelongsToOrganization;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mirrors the Next.js app's `Invoice` Prisma model. Generated only from a
 * VerificationRecord — never entered manually, so there is structurally no
 * path from "someone typed a number" to a customer bill. Has a direct
 * `organization_id` and is in v2's RLS policy list, so it gets
 * BelongsToOrganization.
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable([
    'organization_id', 'verification_record_id', 'stripe_invoice_id',
    'base_fee_cents', 'savings_share_cents', 'total_cents', 'status',
    'issued_at', 'due_at', 'paid_at', 'payment_note',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::saving(function (self $invoice): void {
            // As with a savings assessment's net amount, total_cents is a
            // stored projection of its components, never a separately
            // editable billing input.
            $invoice->total_cents = $invoice->totalCents();
        });
    }

    /** See App\Domain\Organizations\Models\Organization::newFactory()'s docblock. */
    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function totalCents(): int
    {
        return (int) $this->base_fee_cents + (int) $this->savings_share_cents;
    }

    /**
     * @return BelongsTo<VerificationRecord, $this>
     */
    public function verificationRecord(): BelongsTo
    {
        return $this->belongsTo(VerificationRecord::class);
    }
}
