<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\SavedViewCatalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A named, per-user table view.
 *
 * `filters` is a JSON map whose keys are validated against
 * {@see SavedViewCatalog} before they reach this model; it never
 * stores a raw query string, SQL or an arbitrary key/value bag.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $resource
 * @property string $name
 * @property string|null $search
 * @property string|null $sort_field
 * @property int $sort_order
 * @property int $per_page
 * @property array<string, string|list<string>> $filters
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedView newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedView newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedView query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedView whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedView whereFilters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedView whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedView whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedView wherePerPage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedView whereResource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedView whereSearch($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedView whereSortField($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedView whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedView whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedView whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SavedView whereUuid($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['user_id', 'resource', 'name', 'search', 'sort_field', 'sort_order', 'per_page', 'filters'])]
final class SavedView extends Model
{
    use HasUuids;

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'sort_order' => 'integer',
            'per_page' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
