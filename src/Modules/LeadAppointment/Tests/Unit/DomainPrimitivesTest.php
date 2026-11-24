<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\Enums\Region;
use Modules\LeadAppointment\Domain\ValueObjects\TimeInterval;
use Modules\LeadAppointment\Domain\ValueObjects\TimeWindow;

it('allows a new lead to be scheduled without a manual qualification step', function (): void {
    expect(LeadStatus::New->canTransitionTo(LeadStatus::InspectionScheduled))->toBeTrue();
});

it('forbids a won lead from moving back into the pipeline', function (): void {
    foreach (LeadStatus::cases() as $status) {
        expect(LeadStatus::Won->canTransitionTo($status))->toBeFalse();
    }
});

it('maps each region to its IANA timezone', function (): void {
    expect(Region::Houston->timezone())->toBe('America/Chicago')
        ->and(Region::Dallas->timezone())->toBe('America/Chicago')
        ->and(Region::Florida->timezone())->toBe('America/New_York');
});

it('treats touching intervals as non-overlapping', function (): void {
    $first = new TimeInterval(
        CarbonImmutable::parse('2026-01-05 10:00:00'),
        CarbonImmutable::parse('2026-01-05 10:30:00'),
    );
    $touching = new TimeInterval(
        CarbonImmutable::parse('2026-01-05 10:30:00'),
        CarbonImmutable::parse('2026-01-05 11:00:00'),
    );
    $overlapping = new TimeInterval(
        CarbonImmutable::parse('2026-01-05 10:29:00'),
        CarbonImmutable::parse('2026-01-05 10:45:00'),
    );

    expect($first->overlaps($touching))->toBeFalse()
        ->and($first->overlaps($overlapping))->toBeTrue();
});

it('rejects a malformed availability window', function (): void {
    expect(fn () => new TimeWindow('10:00', '09:00'))->toThrow(InvalidArgumentException::class);
    expect(fn () => new TimeWindow('25:00', '26:00'))->toThrow(InvalidArgumentException::class);
});
