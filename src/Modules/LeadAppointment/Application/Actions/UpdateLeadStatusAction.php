<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Actions;

use Modules\LeadAppointment\Application\Dtos\LeadData;
use Modules\LeadAppointment\Application\Dtos\LogLeadActivityData;
use Modules\LeadAppointment\Application\Dtos\UpdateLeadStatusData;
use Modules\LeadAppointment\Application\Ports\LeadReader;
use Modules\LeadAppointment\Application\Ports\LeadWriter;
use Modules\LeadAppointment\Domain\Enums\LeadActivityType;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\LeadAppointment\Domain\Exceptions\LeadNotFoundException;
use Modules\LeadAppointment\Domain\Services\StatusTransition;

final readonly class UpdateLeadStatusAction
{
    public function __construct(
        private LeadReader $leads,
        private LeadWriter $leadWriter,
        private StatusTransition $transitions,
    ) {}

    public function execute(string $uuid, UpdateLeadStatusData $data, ?int $actorId = null): LeadData
    {
        $lead = $this->leads->find($uuid);

        if ($lead === null) {
            throw new LeadNotFoundException;
        }

        $from = LeadStatus::from($lead->status);

        if ($data->status === LeadStatus::InspectionScheduled) {
            throw InvalidStatusTransitionException::for($from->value, $data->status->value);
        }

        if ($from !== $data->status) {
            $this->transitions->assertLead($from, $data->status);
        }

        $activity = $data->note !== null && $data->note !== ''
            ? new LogLeadActivityData(LeadActivityType::Note, null, $data->note, null)
            : null;

        return $this->leadWriter->updateStatus(
            $uuid,
            $data->status,
            $data->inspectionDate,
            $data->inspectionTime,
            $data->inspectionStatus,
            $activity,
            $actorId,
        );
    }
}
