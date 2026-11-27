<?php

namespace Tests\Feature;

use App\Http\Middleware\MaintenanceModeMiddleware;
use Tests\TestCase;

class AppointmentRequestTest extends TestCase
{
    public function test_appointment_requests_reject_dates_in_the_past(): void
    {
        $this->withoutMiddleware(MaintenanceModeMiddleware::class);

        $response = $this->from(route('appointment.create'))->post(route('appointment.store'), [
            'name' => 'Test Student',
            'email' => 'student@example.com',
            'phone' => '9800000000',
            'appointment_date' => now()->subDay()->toDateString(),
            'appointment_time' => '10:30',
            'message' => 'Please contact me.',
        ]);

        $response->assertRedirect(route('appointment.create'));
        $response->assertSessionHasErrors('appointment_date');
    }
}
