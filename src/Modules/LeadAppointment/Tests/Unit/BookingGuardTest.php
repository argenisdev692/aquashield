<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\LeadAppointment\Domain\Exceptions\CapacityExceededException;
use Modules\LeadAppointment\Domain\Exceptions\PastDateSchedulingException;
use Modules\LeadAppointment\Domain\Exceptions\SlotUnavailableException;
use Modules\LeadAppointment\Domain\Services\BookingGuard;
use Modules\LeadAppointment\Domain\ValueObjects\BookingIntent;
use Modules\LeadAppointment\Domain\ValueObjects\ResolvedDay;
use Modules\LeadAppointment\Domain\ValueObjects\TimeInterval;
use Modules\LeadAppointment\Domain\ValueObjects\TimeWindow;

function guardDay(string $timezone = 'America/Chicago'): ResolvedDay
{
    return ResolvedDay::open(
        '2026-01-05',
        [new TimeWindow('08:00', '17:00')],
        'company_rule',
        $timezone,
    );
}

function guardIntent(string $start = '2026-01-05 08:00', bool $emergency = false, int $duration = 420): BookingIntent
{
    $startsAt = CarbonImmutable::parse($start, 'America/Chicago')->utc();

    return new BookingIntent(
        startsAt: $startsAt,
        endsAt: $startsAt->addMinutes($duration),
        travelBufferMinutes: 60,
        dailyCapacity: 1,
        isEmergency: $emergency,
    );
}

it('accepts one 7 h inspection plus the 1 h travel buffer inside the window', function (): void {
    app(BookingGuard::class)->assertBookable(
        guardIntent(),
        guardDay(),
        [],
        0,
        CarbonImmutable::parse('2026-01-01'),
    );

    expect(true)->toBeTrue();
});

it('rejects a past booking', function (): void {
    expect(fn () => app(BookingGuard::class)->assertBookable(
        guardIntent(),
        guardDay(),
        [],
        0,
        CarbonImmutable::parse('2026-01-10'),
    ))->toThrow(PastDateSchedulingException::class);
});

it('rejects a 16:00 start because 7 h would end at 23:00', function (): void {
    expect(fn () => app(BookingGuard::class)->assertBookable(
        guardIntent('2026-01-05 16:00'),
        guardDay(),
        [],
        0,
        CarbonImmutable::parse('2026-01-01'),
    ))->toThrow(SlotUnavailableException::class);
});

it('rejects a second inspection on the same day', function (): void {
    $existing = new TimeInterval(
        CarbonImmutable::parse('2026-01-05 08:00', 'America/Chicago')->utc(),
        CarbonImmutable::parse('2026-01-05 15:00', 'America/Chicago')->utc(),
    );

    expect(fn () => app(BookingGuard::class)->assertBookable(
        guardIntent('2026-01-05 16:00', duration: 60),
        guardDay(),
        [$existing],
        1,
        CarbonImmutable::parse('2026-01-01'),
    ))->toThrow(CapacityExceededException::class);
});

it('rejects an overlap with the travel buffer of an earlier visit', function (): void {
    $existing = new TimeInterval(
        CarbonImmutable::parse('2026-01-05 08:00', 'America/Chicago')->utc(),
        CarbonImmutable::parse('2026-01-05 15:00', 'America/Chicago')->utc(),
    );

    expect(fn () => app(BookingGuard::class)->assertBookable(
        guardIntent('2026-01-05 15:30', duration: 60),
        guardDay(),
        [$existing],
        0,
        CarbonImmutable::parse('2026-01-01'),
    ))->toThrow(SlotUnavailableException::class);
});

it('lets an emergency bypass the closed day and the daily cap, but never an overlap', function (): void {
    $closed = ResolvedDay::closed('2026-01-05', 'company_exception', 'America/Chicago', 'Holiday');
    $intent = guardIntent(emergency: true);

    app(BookingGuard::class)->assertBookable($intent, $closed, [], 1, CarbonImmutable::parse('2026-01-01'));

    $existing = new TimeInterval(
        CarbonImmutable::parse('2026-01-05 08:00', 'America/Chicago')->utc(),
        CarbonImmutable::parse('2026-01-05 15:00', 'America/Chicago')->utc(),
    );

    expect(fn () => app(BookingGuard::class)->assertBookable(
        $intent,
        $closed,
        [$existing],
        1,
        CarbonImmutable::parse('2026-01-01'),
    ))->toThrow(SlotUnavailableException::class);
});
