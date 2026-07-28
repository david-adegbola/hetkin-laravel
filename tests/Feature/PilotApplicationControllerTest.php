<?php

use App\Domain\Pilots\Models\PilotApplication;
use Illuminate\Support\Facades\Notification;

test('the apply page renders for a guest', function () {
    $response = $this->get(route('pilot-program.apply'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('PilotProgram/Apply'));
});

test('submitting a valid application creates a PENDING application and redirects with a success status', function () {
    Notification::fake();

    $response = $this->post(route('pilot-program.apply.store'), [
        'company_name' => 'Acme Cold Storage Oy',
        'process_type' => 'COLD_STORAGE',
        'estimated_annual_spend_eur' => 200_000,
        'current_scheduling_method' => 'Excel',
        'contact_name' => 'Maria Applicant',
        'contact_email' => 'maria@acme.test',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'application-submitted');
    expect(PilotApplication::query()->where('contact_email', 'maria@acme.test')->exists())->toBeTrue();
});

test('submitting with missing required fields redirects back with validation errors', function () {
    $response = $this->post(route('pilot-program.apply.store'), []);

    $response->assertSessionHasErrors(['company_name', 'process_type', 'contact_name', 'contact_email']);
});

test('the sixth submission from the same IP within 10 minutes is rate-limited', function () {
    Notification::fake();

    $payload = [
        'company_name' => 'Acme Oy',
        'process_type' => 'COLD_STORAGE',
        'estimated_annual_spend_eur' => 200_000,
        'current_scheduling_method' => 'Excel',
        'contact_name' => 'Maria Applicant',
        'contact_email' => 'maria@acme.test',
    ];

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('pilot-program.apply.store'), $payload)->assertSessionHasNoErrors();
    }

    $this->post(route('pilot-program.apply.store'), $payload)->assertStatus(429);
});
