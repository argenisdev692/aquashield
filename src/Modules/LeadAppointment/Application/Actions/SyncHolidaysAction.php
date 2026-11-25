<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Actions;

use Modules\LeadAppointment\Application\Dtos\HolidayResyncResultData;
use Modules\LeadAppointment\Application\Dtos\HolidaySyncResultData;
use Modules\LeadAppointment\Domain\Services\HolidayMaterializer;

final readonly class SyncHolidaysAction
{
    public function __construct(private HolidayMaterializer $materializer) {}

    public function execute(?int $year = null): HolidaySyncResultData|HolidayResyncResultData
    {
        if ($year !== null) {
            $result = $this->materializer->materialize($year);

            return new HolidaySyncResultData(
                country: $result['country'],
                created: $result['created'],
                skipped: $result['skipped'],
            );
        }

        $result = $this->materializer->resync();

        return new HolidayResyncResultData(
            purged: $result['purged'],
            created: $result['created'],
        );
    }
}
