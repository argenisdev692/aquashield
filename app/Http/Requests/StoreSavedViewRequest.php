<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\SavedViewCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A saved view is user-written data that is later replayed into a list URL, so
 * every field is bounded: a known resource, a short name, a fixed set of query
 * keys and filter keys drawn from {@see SavedViewCatalog}. Anything else is a
 * 422, not a stored payload.
 */
final class StoreSavedViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resource' => ['required', 'string', Rule::in(SavedViewCatalog::resources())],
            'name' => ['required', 'string', 'min:1', 'max:'.SavedViewCatalog::MAX_NAME_LENGTH],
            'query' => ['required', 'array:search,sort_field,sort_order,per_page,filters'],
            'query.search' => ['nullable', 'string', 'max:255'],
            'query.sort_field' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'query.sort_order' => ['nullable', 'integer', 'in:1,-1'],
            'query.per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'query.filters' => ['nullable', 'array', 'max:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim((string) preg_replace('/\s+/', ' ', $name))]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $resource = $this->input('resource');
            $filters = $this->input('query.filters');

            if (! is_string($resource) || ! is_array($filters)) {
                return;
            }

            $allowed = SavedViewCatalog::filterKeys($resource);

            foreach ($filters as $key => $value) {
                $name = (string) $key;

                if (! in_array($name, $allowed, true)) {
                    $validator->errors()->add(
                        'query.filters',
                        "Unknown filter [{$name}] for [{$resource}].",
                    );

                    continue;
                }

                if (is_array($value)) {
                    if (! array_is_list($value)) {
                        $validator->errors()->add(
                            'query.filters',
                            "Filter [{$name}] must be text or a list of text values.",
                        );

                        continue;
                    }

                    if (count($value) > 5) {
                        $validator->errors()->add(
                            'query.filters',
                            "Filter [{$name}] accepts at most 5 values.",
                        );
                    }

                    foreach ($value as $item) {
                        if (! is_string($item) || mb_strlen($item) > 255) {
                            $validator->errors()->add(
                                'query.filters',
                                "Filter [{$name}] values must be text up to 255 characters.",
                            );

                            break;
                        }
                    }

                    continue;
                }

                if (! is_string($value) || mb_strlen($value) > 255) {
                    $validator->errors()->add(
                        'query.filters',
                        "Filter [{$name}] must be text up to 255 characters.",
                    );
                }
            }
        });
    }
}
