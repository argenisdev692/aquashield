<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Actions;

use Modules\LeadAppointment\Application\Dtos\LeadData;
use Modules\LeadAppointment\Application\Ports\LeadReader;
use Modules\LeadAppointment\Application\Ports\LeadWriter;
use Modules\LeadAppointment\Domain\Exceptions\LeadNotFoundException;

final readonly class MergeLeadAction
{
    public function __construct(
        private LeadReader $leads,
        private LeadWriter $leadWriter,
    ) {}

    public function execute(string $targetUuid, string $duplicateUuid): LeadData
    {
        if ($targetUuid === $duplicateUuid) {
            throw new \InvalidArgumentException('A lead cannot be merged into itself.');
        }

        if ($this->leads->find($targetUuid) === null || $this->leads->find($duplicateUuid) === null) {
            throw new LeadNotFoundException;
        }

        return $this->leadWriter->merge($targetUuid, $duplicateUuid);
    }
}
