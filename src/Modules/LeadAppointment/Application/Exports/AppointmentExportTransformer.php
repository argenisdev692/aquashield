<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Exports;

use Modules\LeadAppointment\Application\Dtos\AppointmentFilterData;
use Modules\LeadAppointment\Application\Ports\AppointmentReader;
use Shared\Support\SpreadsheetCell;

final readonly class AppointmentExportTransformer
{
    public function __construct(
        private AppointmentFilterData $filters,
        private AppointmentReader $appointments,
    ) {}

    /**
     * @return array<string, string>
     */
    public function columns(): array
    {
        return [
            'customer' => 'Customer',
            'kind' => 'Kind',
            'status' => 'Status',
            'emergency' => 'Emergency',
            'starts_at' => 'Starts at',
            'ends_at' => 'Ends at',
            'timezone' => 'Timezone',
            'region' => 'Region',
            'address' => 'Address',
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
     * @return iterable<int, array<string, string|int|float>>
     */
    public function rows(): iterable
    {
        foreach ($this->appointments->stream($this->filters) as $appointment) {
            yield [
                'customer' => SpreadsheetCell::sanitise($appointment->customerName),
                'kind' => SpreadsheetCell::sanitise($appointment->kind),
                'status' => SpreadsheetCell::sanitise($appointment->status),
                'emergency' => $appointment->isEmergency ? 'Yes' : 'No',
                'starts_at' => SpreadsheetCell::sanitise($appointment->startsAtLocal),
                'ends_at' => SpreadsheetCell::sanitise($appointment->endsAtLocal),
                'timezone' => SpreadsheetCell::sanitise($appointment->timezone),
                'region' => SpreadsheetCell::sanitise($appointment->region),
                'address' => SpreadsheetCell::sanitise($appointment->addressLine),
            ];
        }
    }
}
