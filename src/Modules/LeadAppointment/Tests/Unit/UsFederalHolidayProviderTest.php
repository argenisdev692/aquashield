<?php

declare(strict_types=1);

use Modules\LeadAppointment\Infrastructure\Holidays\UsFederalHolidayProvider;

it('returns the eleven US federal holidays and observes weekends', function (): void {
    $provider = new UsFederalHolidayProvider;
    $holidays = $provider->forYear(2026);

    expect($holidays)->toHaveCount(11)
        ->and($holidays['2026-01-01'])->toBe("New Year's Day")
        ->and($holidays['2026-01-19'])->toBe('Martin Luther King Day')
        ->and($holidays['2026-02-16'])->toBe("Presidents' Day")
        ->and($holidays['2026-05-25'])->toBe('Memorial Day')
        ->and($holidays['2026-06-19'])->toBe('Juneteenth National Independence Day')
        ->and($holidays['2026-07-03'])->toBe('Independence Day')
        ->and($holidays['2026-09-07'])->toBe('Labor Day')
        ->and($holidays['2026-10-12'])->toBe('Columbus Day')
        ->and($holidays['2026-11-11'])->toBe('Veterans Day')
        ->and($holidays['2026-11-26'])->toBe('Thanksgiving')
        ->and($holidays['2026-12-25'])->toBe('Christmas');
});

it('observes a Saturday holiday on the preceding Friday', function (): void {
    $holidays = (new UsFederalHolidayProvider)->forYear(2021);

    // Juneteenth 2021 fell on a Saturday.
    expect($holidays['2021-06-18'])->toBe('Juneteenth National Independence Day');
});

it('does not invent Juneteenth before it became a federal holiday', function (): void {
    $holidays = (new UsFederalHolidayProvider)->forYear(2020);

    expect($holidays)->toHaveCount(10)
        ->and(array_key_exists('2020-06-19', $holidays))->toBeFalse();
});
