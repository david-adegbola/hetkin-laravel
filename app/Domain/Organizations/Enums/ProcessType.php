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
}
