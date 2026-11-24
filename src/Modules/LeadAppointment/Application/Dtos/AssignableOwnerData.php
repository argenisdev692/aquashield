<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A user who can own a lead. Deliberately minimal: the lead form needs a
 * label and a value, not the Users module's access payload.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class AssignableOwnerData extends Data
{
    public function __construct(
        public string $uuid,
        public string $name,
    ) {}
}
