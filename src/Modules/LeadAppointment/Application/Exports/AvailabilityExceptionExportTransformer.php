<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Exports;

use Modules\LeadAppointment\Application\Dtos\AvailabilityExceptionData;
use Modules\LeadAppointment\Application\Dtos\AvailabilityExceptionFilterData;
use Modules\LeadAppointment\Application\Ports\AvailabilityReader;
use Shared\Support\SpreadsheetCell;

/**
 * Same filter contract as the availability screen: the CSV/XLSX/PDF covers the
 * rows the operator was looking at, including the record-state choice.
 */
final readonly class AvailabilityExceptionExportTransformer
{
    public function __construct(
        private AvailabilityExceptionFilterData $filters,
        private AvailabilityReader $availability,
    ) {}

    /**
     * @return array<string, string>
     */
    public function columns(): array
    {
        return [
            'date' => 'Date',
            'availability' => 'Availability',
            'window' => 'Window',
            'reason' => 'Reason',
            'source' => 'Source',
        ];
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return array_values($this->columns());
    }

    /**
     * @return iterable<int, array<string, string>>
     */
    public function rows(): iterable
    {
        foreach ($this->availability->streamExceptions($this->filters) as $exception) {
            yield [
                'date' => SpreadsheetCell::sanitise($exception->date),
                'availability' => $exception->isAvailable ? 'Open' : 'Closed',
                'window' => SpreadsheetCell::sanitise($this->window($exception)),
                'reason' => SpreadsheetCell::sanitise($exception->reason ?? ''),
                'source' => SpreadsheetCell::sanitise(ucfirst($exception->source)),
            ];
        }
    }

    private function window(AvailabilityExceptionData $exception): string
    {
        if ($exception->startTime === null || $exception->endTime === null) {
            return '—';
        }

        return $exception->startTime.'–'.$exception->endTime;
    }
}
