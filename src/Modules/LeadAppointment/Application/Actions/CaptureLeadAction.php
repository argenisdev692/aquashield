<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\LeadAppointment\Application\Dtos\CaptureLeadData;
use Modules\LeadAppointment\Application\Dtos\LeadData;
use Modules\LeadAppointment\Application\Ports\LeadRepository;
use Modules\LeadAppointment\Domain\Enums\LeadSource;
use Modules\LeadAppointment\Domain\Events\LeadCaptured;
use Modules\LeadAppointment\Domain\Services\DuplicateDetector;
use Modules\LeadAppointment\Domain\Services\EmailSpamGuard;
use Modules\LeadAppointment\Domain\Services\RegionResolver;
use Modules\LeadAppointment\Domain\Services\SpamGuard;

/**
 * The single write path for a captured lead. Duplicates are linked, spam is
 * flagged (never rejected), and the event fires after the repository has
 * committed — the listener sends mail and never blocks the visitor.
 */
final readonly class CaptureLeadAction
{
    public function __construct(
        private LeadRepository $leads,
        private DuplicateDetector $duplicates,
        private SpamGuard $spamGuard,
        private EmailSpamGuard $emailGuard,
        private RegionResolver $regions,
        private Dispatcher $events,
    ) {}

    public function execute(
        CaptureLeadData $data,
        LeadSource $source,
        ?string $sourceDetail = null,
        ?string $externalSource = null,
        ?string $externalId = null,
        ?int $actorId = null,
    ): LeadData {
        $candidates = $this->leads->findDuplicateCandidates(
            $data->email ?? '',
            $data->phone,
            $data->address ?? '',
            (int) config('lead-appointment.dedupe_window_days', 30),
        );

        $match = $this->duplicates->detect(
            $data->email,
            $data->phone,
            $data->firstName,
            $data->lastName,
            $data->address,
            $candidates,
            (int) config('lead-appointment.dedupe_window_days', 30),
        );

        $spam = $this->spamGuard
            ->assess(
                trim($data->firstName.' '.$data->lastName.' '.($data->companyName ?? '')),
                $data->damageDescription ?? '',
            )
            ->merge($this->emailGuard->assess($data->email));

        $data->region = $this->regions
            ->resolve($data->state, $data->city, $data->zipCode, $data->region)
            ?->value;

        $lead = $this->leads->create(
            data: $data,
            source: $source,
            duplicateOfUuid: $match?->leadUuid,
            spam: $spam,
            sourceDetail: $sourceDetail,
            externalSource: $externalSource,
            externalId: $externalId,
            createdBy: $actorId,
        );

        $this->events->dispatch(new LeadCaptured($lead->uuid, $lead->isEmergency));

        return $lead;
    }
}
