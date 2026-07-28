<?php

namespace Database\Factories;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\VerificationRecord;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'verification_record_id' => VerificationRecord::factory(),
            'base_fee_cents' => 50_000,
            'savings_share_cents' => 20_000,
            'total_cents' => 70_000,
            'status' => InvoiceStatus::Draft,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Issued,
            'issued_at' => now(),
            'due_at' => now()->addDays(14),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Paid,
            'issued_at' => now()->subDays(3),
            'due_at' => now()->addDays(11),
            'paid_at' => now(),
        ]);
    }
}
