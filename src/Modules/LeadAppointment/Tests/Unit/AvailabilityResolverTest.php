<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Modules\LeadAppointment\Domain\Enums\AvailabilitySource;
use Modules\LeadAppointment\Domain\Services\AvailabilityResolver;
use Modules\LeadAppointment\Domain\ValueObjects\AvailabilityException;
use Modules\LeadAppointment\Domain\ValueObjects\TimeWindow;
use Modules\LeadAppointment\Tests\Support\FakeAvailabilityScheduleReader;

function resolverWithFake(): AvailabilityResolver
{
    $companyException = AvailabilityException::closed(
        '2026-01-06',
        'Company holiday',
        AvailabilitySource::Holiday,
    );

    $reader = new FakeAvailabilityScheduleReader(
        companyExceptions: ['2026-01-06' => $companyException],
        companyRules: [
            1 => [new TimeWindow('08:00', '12:00'), new TimeWindow('13:00', '17:00')],
            2 => [new TimeWindow('08:00', '17:00')],
            3 => [new TimeWindow('08:00', '17:00')],
            4 => [new TimeWindow('08:00', '17:00')],
            5 => [new TimeWindow('08:00', '17:00')],
            6 => [new TimeWindow('08:00', '14:00')],
        ],
    );

    return new AvailabilityResolver($reader);
}

it('closes a day with a company exception', function (): void {
    $day = resolverWithFake()->resolve(CarbonImmutable::parse('2026-01-06', 'America/Chicago'));

    expect($day->isOpen)->toBeFalse()
        ->and($day->source)->toBe('company_exception')
        ->and($day->reason)->toBe('Company holiday');
});

it('returns split windows from the company rule', function (): void {
    $day = resolverWithFake()->resolve(CarbonImmutable::parse('2026-01-12', 'America/Chicago'));

    expect($day->isOpen)->toBeTrue()
        ->and($day->source)->toBe('company_rule')
        ->and($day->windows)->toHaveCount(2)
        ->and($day->containsTime('09:00'))->toBeTrue()
        ->and($day->containsTime('12:30'))->toBeFalse()
        ->and($day->containsTime('14:00'))->toBeTrue();
});

it('closes a day with no rule', function (): void {
    $day = resolverWithFake()->resolve(CarbonImmutable::parse('2026-01-11', 'America/Chicago'));

    expect($day->isOpen)->toBeFalse()
        ->and($day->source)->toBe('default');
});

it('resolves a date range in one batched pass', function (): void {
    $days = resolverWithFake()->resolveRange(
        CarbonImmutable::parse('2026-01-05', 'America/Chicago'),
        CarbonImmutable::parse('2026-01-11', 'America/Chicago'),
    );

    expect($days)->toHaveCount(7)
        ->and($days['2026-01-06']->isOpen)->toBeFalse()
        ->and($days['2026-01-07']->isOpen)->toBeTrue()
        ->and($days['2026-01-11']->isOpen)->toBeFalse();
});
