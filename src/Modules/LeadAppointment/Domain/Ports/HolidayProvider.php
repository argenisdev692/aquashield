<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Ports;

/**
 * Country holiday source. The production adapter computes US federal dates
 * from Carbon and OPM observed rules; a test can substitute a fixed array.
 */
interface HolidayProvider
{
    public function countryCode(): string;

    /**
     * @return array<string, string> Y-m-d => holiday name
     */
    public function forYear(int $year): array;
}
