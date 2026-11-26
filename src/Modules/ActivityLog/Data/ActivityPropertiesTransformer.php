<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Data;

use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * JSON `{}` is an object; a PHP empty array serialises as `[]`, which would
 * contradict the generated `Record<string, ...>` type for every event with no
 * allowlisted properties. The PHP property stays an array for the allowlist;
 * only the wire shape is forced to an object.
 */
final class ActivityPropertiesTransformer implements Transformer
{
    /**
     * @param array<string, string|int|list<string>> $value
     */
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): object
    {
        return (object) $value;
    }
}
