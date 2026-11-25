<?php

declare(strict_types=1);

use Mockery\MockInterface;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Ports\AppointmentReader;
use Modules\LeadAppointment\Application\Ports\AppointmentWriter;
use Modules\LeadAppointment\Application\Ports\CalendarSyncPort;
use Modules\LeadAppointment\Domain\Events\AppointmentCancelled;
use Modules\LeadAppointment\Domain\Events\AppointmentRescheduled;
use Modules\LeadAppointment\Domain\Events\AppointmentScheduled;
use Modules\LeadAppointment\Infrastructure\Listeners\SyncAppointmentToGoogleCalendar;
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
        $mock->shouldReceive('find')->once()->with($appointment->uuid)->andReturn($appointment);
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

    $appointments = Mockery::mock(AppointmentReader::class, function (MockInterface $mock) use ($appointment): void {
        $mock->shouldReceive('find')->once()->with($appointment->uuid)->andReturn($appointment);
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

    $appointments = Mockery::mock(AppointmentReader::class, function (MockInterface $mock) use ($appointment): void {
        $mock->shouldReceive('find')->once()->with($appointment->uuid)->andReturn($appointment);
    });

    $writer = Mockery::mock(AppointmentWriter::class);

    $calendar = Mockery::mock(CalendarSyncPort::class, function (MockInterface $mock): void {
        $mock->shouldReceive('delete')->once();
    });

    (new SyncAppointmentToGoogleCalendar($appointments, $writer, $calendar))
        ->handle(new AppointmentCancelled($appointment->uuid, 'Weather'));
});
