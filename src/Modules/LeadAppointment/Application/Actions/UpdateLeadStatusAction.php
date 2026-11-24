<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\LeadAppointment\Application\Dtos\LeadData;
use Modules\LeadAppointment\Application\Dtos\LogLeadActivityData;
use Modules\LeadAppointment\Application\Dtos\UpdateLeadStatusData;
use Modules\LeadAppointment\Application\Ports\LeadRepository;
use Modules\LeadAppointment\Domain\Enums\LeadActivityType;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Modules\LeadAppointment\Domain\Events\LeadStatusChanged;
use Modules\LeadAppointment\Domain\Exceptions\LeadNotFoundException;
use Modules\LeadAppointment\Domain\Services\StatusTransition;

final readonly class UpdateLeadStatusAction
{
    public function __construct(
        private LeadRepository $leads,
        private StatusTransition $transitions,
        private Dispatcher $events,
    ) {}

    public function execute(string $uuid, UpdateLeadStatusData $data, ?int $actorId = null): LeadData
    {
        $lead = $this->leads->find($uuid);

        if ($lead === null) {
            throw new LeadNotFoundException;
        }

        $from = LeadStatus::from($lead->status);

        if ($from !== $data->status) {
            $this->transitions->assertLead($from, $data->status);
        }

        $updated = $this->leads->updateStatus(
            $uuid,
            $data->status,
            $data->inspectionDate,
            $data->inspectionTime,
            $data->inspectionStatus,
        );

        if ($data->note !== null && $data->note !== '') {
            $this->leads->logActivity(
                $uuid,
                new LogLeadActivityData(LeadActivityType::Note, null, $data->note, null),
                $actorId,
            );
        }

        if ($from !== $data->status) {
            $this->events->dispatch(new LeadStatusChanged($uuid, $from, $data->status));
        }

        return $updated;
    }
}
