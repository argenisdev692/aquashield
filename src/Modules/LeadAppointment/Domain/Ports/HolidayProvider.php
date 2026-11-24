<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Ports;

/**
 * Country holiday source. The production adapter wraps spatie/holidays; a test
 * can substitute a fixed array without knowing the package.
 */
interface HolidayProvider
{
    public function countryCode(): string;

    /**
     * @return array<string, string> Y-m-d => holiday name
     */
    public function forYear(int $year): array;
}
