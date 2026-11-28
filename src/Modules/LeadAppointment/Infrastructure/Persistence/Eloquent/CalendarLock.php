<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Persistence\Eloquent;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Serialises every write that can change company availability or consume the
 * single crew's daily capacity. Callers must already be inside a transaction.
 */
final readonly class CalendarLock
{
    public function acquire(): void
    {
        $locked = DB::table('scheduling_locks')
            ->where('key', 'company')
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            throw new RuntimeException('The company scheduling lock is missing.');
        }
    }
}
