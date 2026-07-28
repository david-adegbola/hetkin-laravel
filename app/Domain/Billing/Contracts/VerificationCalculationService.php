<?php

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\DTOs\VerificationCalculationInput;
use App\Domain\Billing\DTOs\VerificationCalculationResult;
use App\Domain\Billing\Exceptions\InsufficientVerificationDataException;

/**
 * Mirrors the Next.js app's `VerificationCalculationService` interface
 * (src/lib/verification/types.ts). Same "single swap point" container
 * binding as SavingsCalculationService — see MIGRATION_PLAN.md ADR-38.
 */
interface VerificationCalculationService
{
    public function methodologyVersion(): string;

    public function isProvisional(): bool;

    /**
     * @throws InsufficientVerificationDataException
     */
    public function calculate(VerificationCalculationInput $input): VerificationCalculationResult;
}
