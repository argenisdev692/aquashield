<?php

declare(strict_types=1);

namespace Modules\Users\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Validated profile update payload. Roles and direct permissions are NOT here:
 * they have dedicated endpoints because each write is separately auditable and
 * separately authorised (ASSIGN_ROLES_USER / ASSIGN_PERMISSIONS_USER).
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class UpdateUserData extends Data
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $email,
        public readonly ?string $lastName = null,
        public readonly ?string $username = null,
        public readonly ?string $phone = null,
        public readonly ?string $dateOfBirth = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
        public readonly ?string $country = null,
    ) {}
}
