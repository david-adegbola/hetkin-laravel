<?php

use Illuminate\Http\UploadedFile;

function scheduleImportCsv(): string
{
    return "asset_label,start_at,end_at,estimated_kwh,labour_classification\nLine A,2026-08-01 08:00,2026-08-01 12:00,100,UNMANNED";
}

test('the schedule import page is available to a portal user', function () {
    ['user' => $user] = portalUser();

    $this->actingAs($user, 'web')->get(route('schedule.import'))
        ->assertInertia(fn ($page) => $page->component('Portal/ScheduleImport'));
});

test('an ERP CSV import redirects to the schedule with its result', function () {
    ['user' => $user] = portalUser();
    $file = UploadedFile::fake()->createWithContent('erp.csv', scheduleImportCsv());

    $this->actingAs($user, 'web')->post(route('schedule.import.store'), ['file' => $file])
        ->assertRedirect(route('schedule'))
        ->assertSessionHas('status.rowsWritten', 1)
        ->assertSessionHas('status.newVersion', 1);
});

test('an invalid schedule import returns a file error', function () {
    ['user' => $user] = portalUser();
    $file = UploadedFile::fake()->createWithContent('bad.csv', 'asset_label,start_at,end_at\nLine A,not-a-date,2026-08-01');

    $this->actingAs($user, 'web')->post(route('schedule.import.store'), ['file' => $file])
        ->assertSessionHasErrors('file');
});
