<?php

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Billing\Actions\GenerateInvoiceAction;
use App\Domain\Billing\Actions\IssueInvoiceAction;
use App\Domain\Billing\Actions\MarkInvoicePaidAction;
use App\Domain\Billing\Actions\VoidInvoiceAction;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Exceptions\BillingActionException;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\VerificationRecord;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Models\AdminUser;

test('generateInvoice refuses a record that is not LIVE_APPROVED', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $record = VerificationRecord::factory()->for($organization, 'organization')->create();

    expect(fn () => app(GenerateInvoiceAction::class)->execute($record->id, $admin))
        ->toThrow(BillingActionException::class, 'Only LIVE_APPROVED');
});

test('generateInvoice computes base fee plus revenue share', function () {
    $organization = Organization::factory()->create([
        'base_fee_cents' => 50_000,
        'revenue_share_percent' => 10,
    ]);
    $admin = AdminUser::factory()->create();
    $record = VerificationRecord::factory()->liveApproved()->for($organization, 'organization')->create([
        'verified_savings_cents' => 200_000,
    ]);

    $invoice = app(GenerateInvoiceAction::class)->execute($record->id, $admin);

    expect($invoice->base_fee_cents)->toBe(50_000);
    expect($invoice->savings_share_cents)->toBe(20_000);
    expect($invoice->total_cents)->toBe(70_000);
    expect($invoice->status)->toBe(InvoiceStatus::Draft);
});

test('generateInvoice never bills a share of negative verified savings', function () {
    $organization = Organization::factory()->create([
        'base_fee_cents' => 50_000,
        'revenue_share_percent' => 10,
    ]);
    $admin = AdminUser::factory()->create();
    $record = VerificationRecord::factory()->liveApproved()->for($organization, 'organization')->create([
        'verified_savings_cents' => -100_000,
    ]);

    $invoice = app(GenerateInvoiceAction::class)->execute($record->id, $admin);

    expect($invoice->savings_share_cents)->toBe(0);
    expect($invoice->total_cents)->toBe(50_000);
});

test('generateInvoice is idempotent — a second call returns the existing invoice', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $record = VerificationRecord::factory()->liveApproved()->for($organization, 'organization')->create();

    $first = app(GenerateInvoiceAction::class)->execute($record->id, $admin);
    $second = app(GenerateInvoiceAction::class)->execute($record->id, $admin);

    expect($second->id)->toBe($first->id);
    expect(Invoice::withoutGlobalScope(OrganizationScope::class)
        ->where('verification_record_id', $record->id)->count())->toBe(1);
});

test('issueInvoice moves a DRAFT invoice to ISSUED with a 14-day due date', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $invoice = Invoice::factory()->for($organization, 'organization')->create();

    $issued = app(IssueInvoiceAction::class)->execute($invoice->id, $admin);

    expect($issued->status)->toBe(InvoiceStatus::Issued);
    expect($issued->issued_at)->not->toBeNull();
    expect((int) $issued->issued_at->diffInDays($issued->due_at))->toBe(14);
});

test('issueInvoice is a no-op on a non-DRAFT invoice', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $invoice = Invoice::factory()->paid()->for($organization, 'organization')->create();

    $result = app(IssueInvoiceAction::class)->execute($invoice->id, $admin);

    expect($result->status)->toBe(InvoiceStatus::Paid);
});

test('voidInvoice accepts anything except PAID or already-VOID', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $draft = Invoice::factory()->for($organization, 'organization')->create();
    $paid = Invoice::factory()->paid()->for($organization, 'organization')->create();

    $voided = app(VoidInvoiceAction::class)->execute($draft->id, $admin);
    $stillPaid = app(VoidInvoiceAction::class)->execute($paid->id, $admin);

    expect($voided->status)->toBe(InvoiceStatus::Void);
    expect($stillPaid->status)->toBe(InvoiceStatus::Paid);
});

test('markInvoicePaid only accepts an ISSUED invoice', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $draft = Invoice::factory()->for($organization, 'organization')->create();

    expect(fn () => app(MarkInvoicePaidAction::class)->execute($draft->id, $admin))
        ->toThrow(BillingActionException::class, 'Only an ISSUED invoice');
});

test('markInvoicePaid stamps paid_at and stores a trimmed payment note', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $invoice = Invoice::factory()->issued()->for($organization, 'organization')->create();

    $paid = app(MarkInvoicePaidAction::class)->execute($invoice->id, $admin, '  bank ref 123  ');

    expect($paid->status)->toBe(InvoiceStatus::Paid);
    expect($paid->paid_at)->not->toBeNull();
    expect($paid->payment_note)->toBe('bank ref 123');
});

test('markInvoicePaid stores a null note for blank input', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $invoice = Invoice::factory()->issued()->for($organization, 'organization')->create();

    $paid = app(MarkInvoicePaidAction::class)->execute($invoice->id, $admin, '   ');

    expect($paid->payment_note)->toBeNull();
});

test('writes an audit log entry for each invoice lifecycle transition', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $record = VerificationRecord::factory()->liveApproved()->for($organization, 'organization')->create();

    $invoice = app(GenerateInvoiceAction::class)->execute($record->id, $admin);
    expect(AuditLogEntry::query()->latest('created_at')->first()->action)->toBe('invoice.generated');

    app(IssueInvoiceAction::class)->execute($invoice->id, $admin);
    expect(AuditLogEntry::query()->latest('created_at')->first()->action)->toBe('invoice.issued');

    app(MarkInvoicePaidAction::class)->execute($invoice->id, $admin);
    expect(AuditLogEntry::query()->latest('created_at')->first()->action)->toBe('invoice.marked_paid');
});
