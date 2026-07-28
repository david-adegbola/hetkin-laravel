<?php

namespace App\Domain\Organizations\Enums;

/** Mirrors the Next.js app's `ProcessType` Prisma enum exactly. */
enum ProcessType: string
{
    case ColdStorage = 'COLD_STORAGE';
    case MetalTreatment = 'METAL_TREATMENT';
    case PlasticsMolding = 'PLASTICS_MOLDING';
    case WoodProcessing = 'WOOD_PROCESSING';
    case WaterTreatment = 'WATER_TREATMENT';
    case FoodProcessing = 'FOOD_PROCESSING';
    case ChemicalBatch = 'CHEMICAL_BATCH';
    case Other = 'OTHER';

    /** Mirrors the Next.js app's PROCESS_TYPE_LABELS (src/lib/pilot.ts). */
    public function label(): string
    {
        return match ($this) {
            self::ColdStorage => 'Cold storage',
            self::MetalTreatment => 'Metal / heat treatment',
            self::PlasticsMolding => 'Plastics injection molding',
            self::WoodProcessing => 'Wood processing',
            self::WaterTreatment => 'Water treatment',
            self::FoodProcessing => 'Food processing',
            self::ChemicalBatch => 'Chemical / battery batch processing',
            self::Other => 'Other / not sure',
        };
    }
}
