<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Mockery\MockInterface;
use Modules\LeadAppointment\Application\Actions\ScheduleAppointmentAction;
use Modules\LeadAppointment\Application\Dtos\AppointmentData;
use Modules\LeadAppointment\Application\Dtos\LeadData;
use Modules\LeadAppointment\Application\Dtos\ScheduleAppointmentData;
use Modules\LeadAppointment\Application\Ports\AppointmentRepository;
use Modules\LeadAppointment\Application\Ports\LeadRepository;
use Modules\LeadAppointment\Domain\Enums\AppointmentKind;
use Modules\LeadAppointment\Domain\Services\AvailabilityResolver;
use Modules\LeadAppointment\Domain\Services\BookingGuard;
use Modules\LeadAppointment\Domain\Services\StatusTransition;
use Modules\LeadAppointment\Domain\ValueObjects\TimeWindow;
use Modules\LeadAppointment\Tests\Support\FakeAvailabilityScheduleReader;
use Tests\TestCase;

uses(TestCase::class);

/**
 * DB-free action test: every dependency is a port or a pure domain service.
 */
function actionLead(): LeadData
{
    return new LeadData(
        uuid: '0199a000-0000-7000-8000-000000000010',
        firstName: 'Ada',
        lastName: 'Lovelace',
        fullName: 'Ada Lovelace',
        companyName: null,
        email: 'ada@example.com',
        phone: '+17135550199',
        address: '123 Main St',
        address2: null,
        city: 'Houston',
        state: 'TX',
        zipCode: '77002',
        addressLine: '123 Main St, Houston, TX, 77002',
        region: 'houston',
        source: 'website',
        sourceDetail: 'Website form',
        status: 'new',
        ownerUuid: null,
        ownerName: null,
        duplicateOfUuid: null,
        isEmergency: false,
        isSpam: false,
        spamScore: 0,
        spamReasons: [],
        preferredLanguage: 'en',
        damageDescription: null,
        insuranceCarrier: null,
        claimNumber: null,
        smsConsent: false,
        smsConsentAt: null,
        lastContactedAt: null,
        convertedAt: null,
        notes: null,
        attribution: null,
        createdAt: null,
        updatedAt: null,
        deletedAt: null,
    );
}

function actionAppointment(string $leadUuid): AppointmentData
{
    return new AppointmentData(
        uuid: '0199a000-0000-7000-8000-000000000011',
        leadUuid: $leadUuid,
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

it('schedules through ports and a pure availability engine without touching the database', function (): void {
    $lead = actionLead();
    $startsAt = CarbonImmutable::now('America/Chicago')
        ->next(CarbonInterface::MONDAY)
        ->setTime(10, 0)
        ->format('Y-m-d H:i');

    $leads = Mockery::mock(LeadRepository::class, function (MockInterface $mock) use ($lead): void {
        $mock->shouldReceive('find')->once()->with($lead->uuid)->andReturn($lead);
        $mock->shouldReceive('markInspectionScheduled')->once()->with($lead->uuid);
    });

    $appointments = Mockery::mock(AppointmentRepository::class, function (MockInterface $mock) use ($lead): void {
        $mock->shouldReceive('existingIntervalsForDay')->once()->andReturn([]);
        $mock->shouldReceive('dailyCountForDay')->once()->andReturn(0);
        $mock->shouldReceive('createWithLock')->once()->andReturn(actionAppointment($lead->uuid));
    });

    $events = Mockery::mock(Dispatcher::class, function (MockInterface $mock): void {
        $mock->shouldReceive('dispatch')->once();
    });

    $resolver = new AvailabilityResolver(new FakeAvailabilityScheduleReader(
        companyRules: [1 => [new TimeWindow('08:00', '17:00')]],
    ));

    $action = new ScheduleAppointmentAction(
        leads: $leads,
        appointments: $appointments,
        availability: $resolver,
        guard: new BookingGuard,
        transitions: new StatusTransition,
        events: $events,
    );

    $result = $action->execute(new ScheduleAppointmentData(
        leadUuid: $lead->uuid,
        kind: AppointmentKind::Inspection,
        startsAt: $startsAt,
    ));

    expect($result->uuid)->toBe('0199a000-0000-7000-8000-000000000011')
        ->and($result->status)->toBe('scheduled');
});
