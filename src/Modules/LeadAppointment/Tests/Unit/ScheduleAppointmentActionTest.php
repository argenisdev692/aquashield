<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\Dispatcher;
use Mockery\MockInterface;
use Modules\LeadAppointment\Application\Actions\ScheduleAppointmentAction;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Dtos\ScheduleAppointmentData;
use Modules\LeadAppointment\Application\Ports\AppointmentWriter;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\Events\AppointmentScheduled;
use Tests\TestCase;

uses(TestCase::class);

function actionAppointment(): AppointmentData
{
    return new AppointmentData(
        uuid: '0199a000-0000-7000-8000-000000000011',
        leadUuid: '0199a000-0000-7000-8000-000000000010',
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

it('delegates the locked write and dispatches after the writer returns', function (): void {
    $data = new ScheduleAppointmentData(
        leadUuid: '0199a000-0000-7000-8000-000000000010',
        kind: AppointmentKind::Inspection,
        startsAt: '2026-12-07 10:00',
    );
    $appointment = actionAppointment();

    $writer = Mockery::mock(AppointmentWriter::class, function (MockInterface $mock) use ($data, $appointment): void {
        $mock->shouldReceive('createWithLock')->once()->with($data, null)->andReturn($appointment);
    });

    $events = Mockery::mock(Dispatcher::class, function (MockInterface $mock): void {
        $mock->shouldReceive('dispatch')->once()->with(Mockery::type(AppointmentScheduled::class));
    });

    $result = (new ScheduleAppointmentAction($writer, $events))->execute($data);

    expect($result->uuid)->toBe($appointment->uuid)
        ->and($result->status)->toBe('scheduled');
});
