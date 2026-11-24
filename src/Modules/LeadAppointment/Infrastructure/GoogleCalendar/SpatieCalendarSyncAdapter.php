<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\GoogleCalendar;

use Google_Service_Calendar_EventDateTime;
use Illuminate\Support\Facades\Log;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Ports\CalendarSyncPort;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Spatie\GoogleCalendar\Event;
use Throwable;

/**
 * One shared company calendar. The package authenticates with one credential and
 * does not manage per-technician OAuth tokens; that is a deliberate scope
 * boundary. Every method swallows and logs failures: sync must never block a
 * booking.
 */
final readonly class SpatieCalendarSyncAdapter implements CalendarSyncPort
{
    private const string SERVICE_KEY = 'google-calendar';

    public function __construct(private CircuitBreakerInterface $breaker) {}

    public function create(AppointmentData $appointment): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        return $this->breaker->call(
            self::SERVICE_KEY,
            function () use ($appointment): string {
                $event = new Event;
                $this->fill($event, $appointment);
                $saved = $event->save(null, ['sendUpdates' => 'all']);

                return (string) $saved->googleEvent->getId();
            },
            function (Throwable $exception) use ($appointment): ?string {
                $this->logFailure('create', $appointment, $exception);

                return null;
            },
        );
    }

    public function update(AppointmentData $appointment): void
    {
        if (! $this->isConfigured() || $appointment->googleEventId === null) {
            return;
        }

        $this->breaker->call(
            self::SERVICE_KEY,
            function () use ($appointment): bool {
                $event = Event::find($appointment->googleEventId);
                $this->fill($event, $appointment);
                $event->save('updateEvent', ['sendUpdates' => 'all']);

                return true;
            },
            function (Throwable $exception) use ($appointment): bool {
                $this->logFailure('update', $appointment, $exception);

                return true;
            },
        );
    }

    public function delete(AppointmentData $appointment): void
    {
        if (! $this->isConfigured() || $appointment->googleEventId === null) {
            return;
        }

        $this->breaker->call(
            self::SERVICE_KEY,
            function () use ($appointment): bool {
                Event::find($appointment->googleEventId)->delete();

                return true;
            },
            function (Throwable $exception) use ($appointment): bool {
                $this->logFailure('delete', $appointment, $exception);

                return true;
            },
        );
    }

    private function fill(Event $event, AppointmentData $appointment): void
    {
        $event->googleEvent->setSummary(
            ucfirst(str_replace('_', ' ', $appointment->kind)).' — '.$appointment->customerName,
        );
        $event->googleEvent->setDescription(
            collect([
                $appointment->addressLine,
                $appointment->notes,
            ])->filter()->implode("\n"),
        );
        $event->googleEvent->setStart($this->dateTime($appointment->startsAtLocal, $appointment->timezone));
        $event->googleEvent->setEnd($this->dateTime($appointment->endsAtLocal, $appointment->timezone));

        if ($appointment->customerEmail !== null) {
            $event->addAttendee([
                'email' => $appointment->customerEmail,
                'name' => $appointment->customerName,
            ]);
        }
    }

    private function dateTime(string $iso, string $timezone): Google_Service_Calendar_EventDateTime
    {
        $dateTime = new Google_Service_Calendar_EventDateTime;
        $dateTime->setDateTime($iso);
        $dateTime->setTimeZone($timezone);

        return $dateTime;
    }

    private function isConfigured(): bool
    {
        return (bool) config('lead-appointment.google_calendar.enabled', false)
            && filled(config('google-calendar.calendar_id'));
    }

    private function logFailure(string $operation, AppointmentData $appointment, Throwable $exception): void
    {
        Log::warning('lead-appointment.google_calendar.'.$operation.'_failed', [
            'appointment_uuid' => $appointment->uuid,
            'google_event_id' => $appointment->googleEventId,
            'error' => $exception->getMessage(),
        ]);
    }
}
