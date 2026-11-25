<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Exports;

use Modules\LeadAppointment\Application\Dtos\LeadFilterData;
use Modules\LeadAppointment\Application\Ports\LeadReader;
use Shared\Support\SpreadsheetCell;

/**
 * Row -> column transformation for the lead export. The query comes from the
 * same filter contract as the list, so a filter can never be applied on screen
 * and forgotten in the file.
 */
final readonly class LeadExportTransformer
{
    public function __construct(
        private LeadFilterData $filters,
        private LeadReader $leads,
    ) {}

    /**
     * @return array<string, string>
     */
    public function columns(): array
    {
        return [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'city' => 'City',
            'state' => 'State',
            'region' => 'Region',
            'source' => 'Source',
            'status' => 'Status',
            'owner' => 'Owner',
            'emergency' => 'Emergency',
            'spam' => 'Spam',
            'last_contacted_at' => 'Last contact',
            'created_at' => 'Created at',
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
        foreach ($this->leads->stream($this->filters) as $lead) {
            yield [
                'name' => SpreadsheetCell::sanitise($lead->fullName),
                'email' => SpreadsheetCell::sanitise($lead->email),
                'phone' => SpreadsheetCell::sanitise($lead->phone),
                'city' => SpreadsheetCell::sanitise($lead->city),
                'state' => SpreadsheetCell::sanitise($lead->state),
                'region' => SpreadsheetCell::sanitise($lead->region),
                'source' => SpreadsheetCell::sanitise($lead->source),
                'status' => SpreadsheetCell::sanitise($lead->status),
                'owner' => SpreadsheetCell::sanitise($lead->ownerName),
                'emergency' => $lead->isEmergency ? 'Yes' : 'No',
                'spam' => $lead->isSpam ? 'Yes' : 'No',
                'last_contacted_at' => SpreadsheetCell::sanitise($lead->lastContactedAt),
                'created_at' => SpreadsheetCell::sanitise($lead->createdAt),
            ];
        }
    }
}
