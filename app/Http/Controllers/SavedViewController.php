<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\SavedViewData;
use App\Http\Requests\StoreSavedViewRequest;
use App\Models\SavedView;
use App\Models\User;
use App\Support\SavedViewCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Personal table views. JSON only: the table reads them with TanStack Query and
 * a mutation does not need a page visit.
 *
 * Every read and write goes through `$user->savedViews()`, so an object-level
 * check cannot be forgotten: another user's UUID resolves to 404, not to data.
 */
final readonly class SavedViewController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->actor($request);
        $resource = $request->query('resource');

        if (! is_string($resource) || ! SavedViewCatalog::supports($resource)) {
            abort(422, 'Unknown saved-view resource.');
        }

        $views = $user->savedViews()
            ->where('resource', $resource)
            ->orderBy('name')
            ->get();

        return response()->json(['views' => SavedViewData::collect($views)->toArray()]);
    }

    public function store(StoreSavedViewRequest $request): JsonResponse
    {
        $user = $this->actor($request);
        $data = $request->validated();

        /** @var array<string, mixed> $query */
        $query = is_array($data['query'] ?? null) ? $data['query'] : [];
        $resource = (string) $data['resource'];
        $name = (string) $data['name'];

        $existing = $user->savedViews()
            ->where('resource', $resource)
            ->where('name', $name)
            ->first();

        if ($existing === null
            && $user->savedViews()->where('resource', $resource)->count() >= SavedViewCatalog::MAX_VIEWS_PER_RESOURCE) {
            throw ValidationException::withMessages([
                'name' => 'You can save up to '.SavedViewCatalog::MAX_VIEWS_PER_RESOURCE.' views per screen. Delete one first.',
            ]);
        }

        $rawFilters = $query['filters'] ?? [];
        $filters = $this->normalizeFilters(is_array($rawFilters) ? $rawFilters : []);

        $view = $existing ?? new SavedView([
            'user_id' => $user->id,
            'resource' => $resource,
            'name' => $name,
        ]);

        $view->fill([
            'search' => $this->nullableString($query['search'] ?? null),
            'sort_field' => $this->nullableString($query['sort_field'] ?? null),
            'sort_order' => (int) ($query['sort_order'] ?? 1),
            'per_page' => (int) ($query['per_page'] ?? 15),
            'filters' => $filters,
        ]);
        $view->save();

        return response()->json(
            ['view' => SavedViewData::fromModel($view)],
            $existing === null ? 201 : 200,
        );
    }

    public function destroy(Request $request, string $savedView): Response
    {
        $user = $this->actor($request);

        // Scoped lookup: another user's UUID is a 404, never a delete.
        $view = $user->savedViews()->where('uuid', $savedView)->first();

        abort_if($view === null, 404);

        $view->delete();

        return response()->noContent();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * Drop empty values so "no filter" and "cleared filter" store the same
     * view, and normalise every string the way the query builder reads it.
     *
     * @param array<array-key, mixed> $filters
     * @return array<string, string|list<string>>
     */
    private function normalizeFilters(array $filters): array
    {
        $normalized = [];

        foreach ($filters as $key => $value) {
            if (is_string($value)) {
                $trimmed = trim($value);

                if ($trimmed !== '') {
                    $normalized[(string) $key] = $trimmed;
                }

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $list = [];

            foreach ($value as $item) {
                if (! is_string($item)) {
                    continue;
                }

                $trimmed = trim($item);

                if ($trimmed !== '') {
                    $list[] = $trimmed;
                }
            }

            if ($list !== []) {
                $normalized[(string) $key] = $list;
            }
        }

        return $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
