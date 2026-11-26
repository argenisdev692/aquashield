<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use LogicException;
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
     * Audit attribution must survive an actor's soft deletion; the package's
     * default relation applies the SoftDeletingScope and would render the
     * causer as “System”.
     *
     * @param Builder<ActivityEntry> $query
     * @return Builder<ActivityEntry>
     */
    public function scopeWithCauser(Builder $query): Builder
    {
        return $query->with([
            'causer' => static function (MorphTo $morphTo): void {
                $morphTo->withoutGlobalScopes([SoftDeletingScope::class]);
                $morphTo->select(['id', 'first_name', 'last_name']);
            },
        ]);
    }

    protected static function booted(): void
    {
        self::updating(static function (): void {
            throw new LogicException('Activity log entries are append-only.');
        });

        self::deleting(static function (): void {
            throw new LogicException('Activity log entries are append-only; use activitylog:clean for retention.');
        });
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
