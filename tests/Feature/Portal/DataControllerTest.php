<?php

use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\CurrentOrganization;
use Illuminate\Http\UploadedFile;

function meterCsvUpload(int $hours = 24): string
{
    $lines = ['Start time,Quantity'];
    $ts = new DateTimeImmutable('2024-01-01T00:00');
    for ($i = 0; $i < $hours; $i++) {
        $lines[] = $ts->modify("+{$i} hours")->format('Y-m-d\TH:i').',10.0';
    }

    return implode("\n", $lines);
}

test('the data page lists metering points across the organization\'s sites', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $site = Site::factory()->for($organization, 'organization')->create(['name' => 'Factory floor']);
    MeteringPoint::factory()->for($site)->create(['label' => 'Main meter']);

    $response = $this->actingAs($user, 'web')->get(route('data'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Portal/Data')
        ->has('meteringPoints', 1)
        ->where('meteringPoints.0.label', 'Main meter')
        ->where('meteringPoints.0.site_name', 'Factory floor'));
});

test('a non-member cannot reach the data page', function () {
    $response = $this->get(route('data'));

    $response->assertRedirect(route('login'));
});

test('uploading a valid CSV creates readings and flashes the row count', function () {
    ['user' => $user] = portalUser();
    $file = UploadedFile::fake()->createWithContent('export.csv', meterCsvUpload());

    $response = $this->actingAs($user, 'web')->post(route('data.upload.store'), ['file' => $file]);

    $response->assertRedirect();
    $response->assertSessionHas('status.rowsWritten', 24);
});

test('uploading an unparseable file returns a validation error, not a 500', function () {
    ['user' => $user] = portalUser();
    $file = UploadedFile::fake()->createWithContent('bad.csv', 'not,a,valid,file');

    $response = $this->actingAs($user, 'web')->post(route('data.upload.store'), ['file' => $file]);

    $response->assertSessionHasErrors('file');
});

test('the upload page forwards the flashed status into Inertia props', function () {
    ['user' => $user] = portalUser();
    $file = UploadedFile::fake()->createWithContent('export.csv', meterCsvUpload());
    $this->actingAs($user, 'web')->post(route('data.upload.store'), ['file' => $file]);

    $response = $this->get(route('data.upload'));

    $response->assertInertia(fn ($page) => $page
        ->component('Portal/DataUpload')
        ->where('status.rowsWritten', 24));
});
