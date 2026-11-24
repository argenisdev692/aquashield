<?php

declare(strict_types=1);

namespace Modules\Users\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The identity subset shown on the public activation page.
 *
 * Deliberately only name + email: the page must never receive the persistence
 * model, password hashes, invitation marker internals or 2FA data.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ActivationUserData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}
}
