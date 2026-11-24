<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Exports;

use Modules\LeadAppointment\Application\Dtos\LeadSourceReportData;
use Shared\Support\SpreadsheetCell;

final readonly class ReportExportTransformer
{
    public function __construct(private LeadSourceReportData $report) {}

    /**
     * @return array<string, string>
     */
    public function columns(): array
    {
        return [
            'source' => 'Source',
            'leads' => 'Leads',
            'appointments' => 'Appointments',
            'won' => 'Won',
            'conversion_rate' => 'Conversion rate',
            'average_first_contact_minutes' => 'Avg first contact (min)',
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
        foreach ($this->report->rows as $row) {
            yield [
                'source' => SpreadsheetCell::sanitise($row->sourceLabel),
                'leads' => $row->leads,
                'appointments' => $row->appointments,
                'won' => $row->won,
                'conversion_rate' => SpreadsheetCell::sanitise(number_format($row->conversionRate * 100, 1).'%'),
                'average_first_contact_minutes' => $row->averageFirstContactMinutes ?? '',
            ];
        }
    }
}
