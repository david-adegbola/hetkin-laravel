<?php

use App\Domain\Scheduling\Models\ScheduleBlock;

test('eligible only with an estimated kWh and an Unmanned classification', function () {
    $block = ScheduleBlock::factory()->unmanned()->make(['estimated_kwh' => 500]);

    expect($block->isEligibleForOptimization())->toBeTrue();
});

test('not eligible when Labour Bound, even with an estimated kWh', function () {
    $block = ScheduleBlock::factory()->labourBound()->make(['estimated_kwh' => 500]);

    expect($block->isEligibleForOptimization())->toBeFalse();
});

test('not eligible when Unmanned but missing an estimated kWh', function () {
    $block = ScheduleBlock::factory()->unmanned()->withoutEstimatedKwh()->make();

    expect($block->isEligibleForOptimization())->toBeFalse();
});

test('not eligible when both Labour Bound and missing an estimated kWh', function () {
    $block = ScheduleBlock::factory()->labourBound()->withoutEstimatedKwh()->make();

    expect($block->isEligibleForOptimization())->toBeFalse();
});
