<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Actions;

use Modules\LeadAppointment\Application\Dtos\LeadData;
use Modules\LeadAppointment\Application\Dtos\UpdateLeadData;
use Modules\LeadAppointment\Application\Ports\LeadRepository;
use Modules\LeadAppointment\Domain\Services\RegionResolver;

/**
 * The update write path. Region is derived from the submitted address; a
 * phone-only lead keeps its stored region, and an unsupported address clears
 * it instead of leaving a contradictory value behind.
 */
final readonly class UpdateLeadAction
{
    public function __construct(
        private LeadRepository $leads,
        private RegionResolver $regions,
    ) {}

    public function execute(string $uuid, UpdateLeadData $data): LeadData
    {
        $current = $this->leads->find($uuid);

        $data->region = $this->regions
            ->resolve($data->state, $data->city, $data->zipCode, $data->region ?? $current?->region)
            ?->value;

        return $this->leads->update($uuid, $data);
    }
}
