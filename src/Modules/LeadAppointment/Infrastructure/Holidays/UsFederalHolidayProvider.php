<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Holidays;

use Carbon\CarbonImmutable;
use Modules\LeadAppointment\Domain\Ports\HolidayProvider;

/**
 * US federal holidays, self-contained. Eleven dates do not justify a package
 * dependency; the observed rule (Saturday → Friday, Sunday → Monday) is the
 * only subtle part and it is covered by the unit test. Manual exceptions remain
 * the override for state holidays and company closures.
 */
final readonly class UsFederalHolidayProvider implements HolidayProvider
{
    public function countryCode(): string
    {
        return 'us';
    }

    /**
     * @return array<string, string>
     */
    public function forYear(int $year): array
    {
        $holidays = [
            "New Year's Day" => $this->observed(CarbonImmutable::create($year, 1, 1)),
            'Martin Luther King Day' => CarbonImmutable::parse("third monday of January {$year}"),
            "Presidents' Day" => CarbonImmutable::parse("third monday of February {$year}"),
            'Memorial Day' => CarbonImmutable::parse("last monday of May {$year}"),
            'Independence Day' => $this->observed(CarbonImmutable::create($year, 7, 4)),
            'Labor Day' => CarbonImmutable::parse("first monday of September {$year}"),
            'Columbus Day' => CarbonImmutable::parse("second monday of October {$year}"),
            'Veterans Day' => $this->observed(CarbonImmutable::create($year, 11, 11)),
            'Thanksgiving' => CarbonImmutable::parse("fourth thursday of November {$year}"),
            'Christmas' => $this->observed(CarbonImmutable::create($year, 12, 25)),
        ];

        if ($year >= 2021) {
            $holidays['Juneteenth National Independence Day'] = $this->observed(
                CarbonImmutable::create($year, 6, 19),
            );
        }

        $result = [];

        foreach ($holidays as $name => $date) {
            $result[$date->toDateString()] = $name;
        }

        ksort($result);

        return $result;
    }

    private function observed(CarbonImmutable $date): CarbonImmutable
    {
        return match ($date->dayOfWeek) {
            CarbonImmutable::SATURDAY => $date->subDay(),
            CarbonImmutable::SUNDAY => $date->addDay(),
            default => $date,
        };
    }
}
