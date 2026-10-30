<?php

declare(strict_types=1);

namespace Modules\Users\Data;

use App\Models\User;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Allowlisted admin-facing user payload.
 *
 * Never the persistence model: password hashes, remember tokens, Google ids and
 * 2FA secrets stay server-side. Roles and direct permissions are exposed
 * separately so the UI can show what a role grants versus what was granted on
 * top of it — the distinction that makes the "Manager + direct VIEW_USER"
 * scenario understandable.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ManagedUserData extends Data
{
    /**
     * @param list<string> $roles
     * @param list<string> $directPermissions
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $firstName,
        public readonly ?string $lastName,
        public readonly string $name,
        public readonly ?string $username,
        public readonly string $email,
        public readonly bool $emailVerified,
        public readonly ?string $phone,
        public readonly ?string $dateOfBirth,
        public readonly ?string $address,
        #[MapOutputName('address_2')]
        public readonly ?string $address2,
        public readonly ?string $zipCode,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $country,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?string $gender,
        public readonly array $roles,
        public readonly array $directPermissions,
        public readonly ?string $deletedAt,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public static function fromModel(User $user): self
    {
        $user->loadMissing(['roles:id,name', 'permissions:id,name']);

        /** @var list<string> $roles */
        $roles = $user->roles
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->values()
            ->all();

        /** @var list<string> $directPermissions */
        $directPermissions = $user->permissions
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->values()
            ->all();

        return new self(
            uuid: (string) $user->uuid,
            firstName: (string) $user->first_name,
            lastName: $user->last_name,
            name: (string) $user->name,
            username: $user->username,
            email: (string) $user->email,
            emailVerified: $user->hasVerifiedEmail(),
            phone: $user->phone,
            dateOfBirth: $user->date_of_birth,
            address: $user->address,
            address2: $user->address_2,
            zipCode: $user->zip_code,
            city: $user->city,
            state: $user->state,
            country: $user->country,
            latitude: $user->latitude,
            longitude: $user->longitude,
            gender: $user->gender,
            roles: $roles,
            directPermissions: $directPermissions,
            deletedAt: $user->deleted_at?->toIso8601String(),
            createdAt: $user->created_at?->toIso8601String(),
            updatedAt: $user->updated_at?->toIso8601String(),
        );
    }
}
