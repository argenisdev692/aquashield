<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\SavedView;
use App\Support\SavedViewCatalog;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Allowlisted saved-view payload.
 *
 * The model is never serialized: `user_id`, the owner relation and the raw
 * JSON column stay server-side. `filters` reached the model only after the
 * {@see SavedViewCatalog} key check.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class SavedViewData extends Data
{
    /**
     * @param array<string, string|list<string>> $filters
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $resource,
        public readonly string $name,
        public readonly ?string $search,
        public readonly ?string $sortField,
        public readonly int $sortOrder,
        public readonly int $perPage,
        public readonly array $filters,
    ) {}

    public static function fromModel(SavedView $view): self
    {
        return new self(
            uuid: $view->uuid,
            resource: $view->resource,
            name: $view->name,
            search: $view->search,
            sortField: $view->sort_field,
            sortOrder: $view->sort_order,
            perPage: $view->per_page,
            filters: $view->filters,
        );
    }
}
