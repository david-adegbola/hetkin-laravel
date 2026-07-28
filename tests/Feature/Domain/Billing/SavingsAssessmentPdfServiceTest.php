<?php

use App\Domain\Billing\Models\Baseline;
use App\Domain\Billing\Models\SavingsAssessment;
use App\Domain\Billing\Pdf\SavingsAssessmentPdfService;
use App\Domain\Billing\Reports\SavingsReportLoader;
use App\Domain\Billing\Reports\SavingsReportViewModelBuilder;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;

test('renders a well-formed PDF for a real assessment', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->for($organization, 'organization')->create();
    $assessment = SavingsAssessment::factory()->for($organization, 'organization')->for($baseline)->create();

    $data = app(SavingsReportLoader::class)->load($assessment->id, $organization->id);
    $vm = SavingsReportViewModelBuilder::build($data);

    $bytes = app(SavingsAssessmentPdfService::class)->render($vm);

    expect($bytes)->toBeString();
    expect(substr($bytes, 0, 4))->toBe('%PDF');
    expect(strlen($bytes))->toBeGreaterThan(1000);
});

test('renders without error for an assessment with no schedule or recommendations', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->for($organization, 'organization')->create();
    $assessment = SavingsAssessment::factory()->for($organization, 'organization')->for($baseline)->create([
        'period_start' => null,
        'period_end' => null,
    ]);

    $data = app(SavingsReportLoader::class)->load($assessment->id, $organization->id);
    $vm = SavingsReportViewModelBuilder::build($data);

    $bytes = app(SavingsAssessmentPdfService::class)->render($vm);

    expect(substr($bytes, 0, 4))->toBe('%PDF');
});
