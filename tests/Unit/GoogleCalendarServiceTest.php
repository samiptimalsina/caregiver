<?php

namespace Tests\Unit;

use App\Services\GoogleCalendarService;
use Google\Service\Calendar\Event;
use ReflectionClass;
use Tests\TestCase;

class GoogleCalendarServiceTest extends TestCase
{
    public function test_meet_event_payload_requests_a_google_meet_conference(): void
    {
        $service = $this->makeServiceWithoutConstructor();
        $buildEventPayload = new \ReflectionMethod($service, 'buildEventPayload');
        $payload = $buildEventPayload->invoke($service, [
            'summary' => 'Live lesson',
            'start' => '2026-11-27 12:00:00',
            'end' => '2026-11-27 13:00:00',
            'meet' => true,
        ]);

        $this->assertSame(
            'hangoutsMeet',
            $payload['conferenceData']['createRequest']['conferenceSolutionKey']['type']
        );
        $this->assertNotEmpty($payload['conferenceData']['createRequest']['requestId']);
    }

    public function test_non_meet_event_does_not_request_a_conference(): void
    {
        $service = $this->makeServiceWithoutConstructor();
        $buildEventPayload = new \ReflectionMethod($service, 'buildEventPayload');
        $payload = $buildEventPayload->invoke($service, [
            'summary' => 'Live lesson',
            'start' => '2026-11-27 12:00:00',
            'end' => '2026-11-27 13:00:00',
            'meet' => false,
        ]);

        $this->assertArrayNotHasKey('conferenceData', $payload);
    }

    public function test_meet_url_is_read_from_the_calendar_event(): void
    {
        $service = $this->makeServiceWithoutConstructor();
        $getMeetUrl = new \ReflectionMethod($service, 'getMeetUrl');
        $event = new Event(['hangoutLink' => 'https://meet.google.com/abc-defg-hij']);

        $this->assertSame('https://meet.google.com/abc-defg-hij', $getMeetUrl->invoke($service, $event));
    }

    private function makeServiceWithoutConstructor(): GoogleCalendarService
    {
        $reflection = new ReflectionClass(GoogleCalendarService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $setting = $reflection->getProperty('setting');
        $setting->setValue($service, (object) ['timezone' => 'UTC']);

        return $service;
    }
}
