<?php

use App\Domain\Organizations\Enums\ProcessType;
use App\Domain\Pilots\Services\IcpScoreCalculator;

test('flexible process type + high spend scores 100', function () {
    expect(IcpScoreCalculator::compute(ProcessType::ColdStorage, 150_000))->toBe(100);
});

test('flexible process type scores 60 plus the spend tier', function () {
    expect(IcpScoreCalculator::compute(ProcessType::MetalTreatment, 60_000))->toBe(90);
    expect(IcpScoreCalculator::compute(ProcessType::MetalTreatment, 20_000))->toBe(80);
    expect(IcpScoreCalculator::compute(ProcessType::MetalTreatment, 5_000))->toBe(65);
});

test('OTHER process type is the only one scored as inflexible', function () {
    expect(IcpScoreCalculator::compute(ProcessType::Other, 150_000))->toBe(60);
    expect(IcpScoreCalculator::compute(ProcessType::Other, 0))->toBe(25);
});

test('spend score boundaries are inclusive at the lower bound', function () {
    expect(IcpScoreCalculator::compute(ProcessType::Other, 150_000))->toBe(60);
    expect(IcpScoreCalculator::compute(ProcessType::Other, 149_999))->toBe(50);
    expect(IcpScoreCalculator::compute(ProcessType::Other, 50_000))->toBe(50);
    expect(IcpScoreCalculator::compute(ProcessType::Other, 49_999))->toBe(40);
    expect(IcpScoreCalculator::compute(ProcessType::Other, 10_000))->toBe(40);
    expect(IcpScoreCalculator::compute(ProcessType::Other, 9_999))->toBe(25);
});

test('every flexible process type scores 60 for process fit', function () {
    foreach ([
        ProcessType::ColdStorage,
        ProcessType::MetalTreatment,
        ProcessType::PlasticsMolding,
        ProcessType::WoodProcessing,
        ProcessType::WaterTreatment,
        ProcessType::FoodProcessing,
        ProcessType::ChemicalBatch,
    ] as $type) {
        expect(IcpScoreCalculator::compute($type, 0))->toBe(65);
    }
});

test('the minimum and maximum possible scores are 25 and 100', function () {
    expect(IcpScoreCalculator::compute(ProcessType::Other, 0))->toBe(25);
    expect(IcpScoreCalculator::compute(ProcessType::ColdStorage, 150_000))->toBe(100);
});
