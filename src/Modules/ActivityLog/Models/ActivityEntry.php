<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Models\Activity;

/**
 * Spatie's activity model plus a UUID for the panel.
 *
 * The package's auto-increment id stays the internal key; `uuid` is the only
 * identifier that crosses the HTTP boundary, matching every other module.
 *
 * @property string $uuid
 */
final class ActivityEntry extends Activity
{
    use HasUuids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
