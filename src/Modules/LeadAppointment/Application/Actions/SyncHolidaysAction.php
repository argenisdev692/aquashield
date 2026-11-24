<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Actions;

use Modules\LeadAppointment\Domain\Services\HolidayMaterializer;

final readonly class SyncHolidaysAction
{
    public function __construct(private HolidayMaterializer $materializer) {}

    /**
     * @return array<string, int|string>
     */
    public function execute(?int $year = null): array
    {
        return $year === null
            ? $this->materializer->resync()
            : $this->materializer->materialize($year);
    }
}
