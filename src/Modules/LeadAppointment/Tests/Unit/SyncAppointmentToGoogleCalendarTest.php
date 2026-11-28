<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Ports\AppointmentReader;
use Modules\LeadAppointment\Application\Ports\AppointmentWriter;
use Modules\LeadAppointment\Application\Ports\CalendarSyncPort;
use Modules\LeadAppointment\Domain\Events\AppointmentCancelled;
use Modules\LeadAppointment\Domain\Events\AppointmentDeleted;
use Modules\LeadAppointment\Domain\Events\AppointmentRescheduled;
use Modules\LeadAppointment\Domain\Events\AppointmentScheduled;
use Modules\LeadAppointment\Infrastructure\GoogleCalendar\SpatieCalendarSyncAdapter;
use Modules\LeadAppointment\Infrastructure\Listeners\SyncAppointmentToGoogleCalendar;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Tests\TestCase;

uses(TestCase::class);

function googleAppointment(): AppointmentData
{
    return new AppointmentData(
        uuid: '0199a000-0000-7000-8000-000000000020',
        leadUuid: '0199a000-0000-7000-8000-000000000021',
        customerName: 'Ada Lovelace',
        customerEmail: 'ada@example.com',
        customerPhone: '+17135550199',
        addressLine: '123 Main St, Houston, TX, 77002',
        kind: 'inspection',
        status: 'scheduled',
        isEmergency: false,
        startsAt: '2026-12-07T16:00:00+00:00',
        endsAt: '2026-12-07T17:00:00+00:00',
        startsAtLocal: '2026-12-07T10:00:00-06:00',
        endsAtLocal: '2026-12-07T11:00:00-06:00',
        timezone: 'America/Chicago',
        region: 'houston',
        previousStartsAt: null,
        googleEventId: null,
        notes: null,
        cancelReason: null,
        createdAt: null,
        deletedAt: null,
    );
}

it('creates a Google event and stores its id on schedule', function (): void {
    $appointment = googleAppointment();

    $appointments = Mockery::mock(AppointmentReader::class, function (MockInterface $mock) use ($appointment): void {
        $mock->shouldReceive('find')->once()->with($appointment->uuid, true)->andReturn($appointment);
    });

    $writer = Mockery::mock(AppointmentWriter::class, function (MockInterface $mock) use ($appointment): void {
        $mock->shouldReceive('setGoogleEventId')->once()->with($appointment->uuid, 'event-1');
    });

    $calendar = Mockery::mock(CalendarSyncPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('create')->once()->andReturn('event-1');
    });

    (new SyncAppointmentToGoogleCalendar($appointments, $writer, $calendar))
        ->handle(new AppointmentScheduled($appointment->uuid));

    expect(true)->toBeTrue();
});

it('updates the same Google event on reschedule', function (): void {
    $appointment = googleAppointment();
    $appointment->googleEventId = 'event-1';

    $appointments = Mockery::mock(AppointmentReader::class, function (MockInterface $mock) use ($appointment): void {
        $mock->shouldReceive('find')->once()->with($appointment->uuid, true)->andReturn($appointment);
    });

    $writer = Mockery::mock(AppointmentWriter::class);
    $writer->shouldNotReceive('setGoogleEventId');

    $calendar = Mockery::mock(CalendarSyncPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('update')->once();
    });

    (new SyncAppointmentToGoogleCalendar($appointments, $writer, $calendar))
        ->handle(new AppointmentRescheduled($appointment->uuid, 'Weather'));
});

it('deletes the Google event on cancellation', function (): void {
    $appointment = googleAppointment();
    $appointment->status = 'cancelled';
    $appointment->googleEventId = 'event-1';

    $appointments = Mockery::mock(AppointmentReader::class, function (MockInterface $mock) use ($appointment): void {
        $mock->shouldReceive('find')->once()->with($appointment->uuid, true)->andReturn($appointment);
    });

    $writer = Mockery::mock(AppointmentWriter::class);

    $calendar = Mockery::mock(CalendarSyncPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('delete')->once();
    });

    (new SyncAppointmentToGoogleCalendar($appointments, $writer, $calendar))
        ->handle(new AppointmentCancelled($appointment->uuid, 'Weather'));
});

it('logs safe Google failure metadata without provider message content', function (): void {
    config([
        'lead-appointment.google_calendar.enabled' => true,
        'google-calendar.calendar_id' => 'calendar@example.test',
    ]);

    $breaker = Mockery::mock(CircuitBreakerInterface::class);
    $breaker->shouldReceive('call')
        ->once()
        ->andReturnUsing(fn (string $service, Closure $operation, ?Closure $fallback): mixed => $fallback(
            new RuntimeException('Provider rejected ada@example.com at 123 Main St'),
        ));

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'lead-appointment.google_calendar.create_failed'
            && $context['exception_class'] === RuntimeException::class
            && ! array_key_exists('error', $context)
            && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'ada@example.com'));

    $adapter = new SpatieCalendarSyncAdapter($breaker);

    expect(fn () => $adapter->create(googleAppointment()))->toThrow(RuntimeException::class);
});

it('reconciles a delayed schedule event to cancellation', function (): void {
    $appointment = googleAppointment();
    $appointment->status = 'cancelled';
    $appointment->googleEventId = 'event-1';

    $appointments = Mockery::mock(AppointmentReader::class, function (MockInterface $mock) use ($appointment): void {
        $mock->shouldReceive('find')->once()->with($appointment->uuid, true)->andReturn($appointment);
    });
    $writer = Mockery::mock(AppointmentWriter::class);
    $writer->shouldNotReceive('setGoogleEventId');
    $calendar = Mockery::mock(CalendarSyncPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('delete')->once()->with(Mockery::type(AppointmentData::class));
        $mock->shouldNotReceive('create');
    });

    (new SyncAppointmentToGoogleCalendar($appointments, $writer, $calendar))
        ->handle(new AppointmentScheduled($appointment->uuid));
});

it('reconciles a delete event using the trashed appointment', function (): void {
    $appointment = googleAppointment();
    $appointment->googleEventId = 'event-1';
    $appointment->deletedAt = '2026-12-07T12:00:00+00:00';

    $appointments = Mockery::mock(AppointmentReader::class, function (MockInterface $mock) use ($appointment): void {
        $mock->shouldReceive('find')->once()->with($appointment->uuid, true)->andReturn($appointment);
    });
    $writer = Mockery::mock(AppointmentWriter::class);
    $calendar = Mockery::mock(CalendarSyncPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('delete')->once();
        $mock->shouldNotReceive('create');
    });

    (new SyncAppointmentToGoogleCalendar($appointments, $writer, $calendar))
        ->handle(new AppointmentDeleted($appointment->uuid));
});

it('creates a missing remote event when a reschedule arrives', function (): void {
    $appointment = googleAppointment();

    $appointments = Mockery::mock(AppointmentReader::class, function (MockInterface $mock) use ($appointment): void {
        $mock->shouldReceive('find')->once()->with($appointment->uuid, true)->andReturn($appointment);
    });
    $writer = Mockery::mock(AppointmentWriter::class, function (MockInterface $mock) use ($appointment): void {
        $mock->shouldReceive('setGoogleEventId')->once()->with($appointment->uuid, 'event-2');
    });
    $calendar = Mockery::mock(CalendarSyncPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('create')->once()->andReturn('event-2');
        $mock->shouldNotReceive('update');
    });

    (new SyncAppointmentToGoogleCalendar($appointments, $writer, $calendar))
        ->handle(new AppointmentRescheduled($appointment->uuid, 'Weather'));
});
