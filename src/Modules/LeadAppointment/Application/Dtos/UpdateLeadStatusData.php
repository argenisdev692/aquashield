<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Modules\LeadAppointment\Domain\Enums\InspectionStatus;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class UpdateLeadStatusData extends Data
{
    public function __construct(
        public LeadStatus $status,
        public ?string $note = null,
        public ?string $inspectionDate = null,
        public ?string $inspectionTime = null,
        public ?InspectionStatus $inspectionStatus = null,
    ) {}
}
