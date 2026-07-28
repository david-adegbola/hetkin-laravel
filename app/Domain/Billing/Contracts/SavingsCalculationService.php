<?php

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\DTOs\SavingsCalculationInput;
use App\Domain\Billing\DTOs\SavingsCalculationResult;
use App\Domain\Billing\Exceptions\InsufficientDataException;

/**
 * Mirrors the Next.js app's `SavingsCalculationService` interface
 * (src/lib/savings/types.ts). Everything that calls this — Actions, the
 * report view-model — is built against this interface, not against any one
 * formula, so a future legally-reviewed methodology drops in as a second
 * implementation without touching call sites. Bound via the container
 * (App\Providers\AppServiceProvider), same "single swap point" pattern as
 * App\Domain\Optimization\Contracts\RecommendationEngine (see
 * MIGRATION_PLAN.md ADR-38).
 */
interface SavingsCalculationService
{
    public function methodologyVersion(): string;

    public function isProvisional(): bool;

    /**
     * @throws InsufficientDataException
     */
    public function calculate(SavingsCalculationInput $input): SavingsCalculationResult;
}
