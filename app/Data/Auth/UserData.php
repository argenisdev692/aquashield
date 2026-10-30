<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Models\User;
use Shared\Domain\Ports\StoragePort;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Throwable;

/**
 * Allowlisted user payload for Inertia and the API.
 *
 * Never expose the persistence model: password hashes, Google ids, 2FA
 * secrets and the raw object key stay server-side. The profile photo is
 * delivered as a resolved URL so the client never builds a storage path.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class UserData extends Data
{
    /**
     * @param list<string> $permissions
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
        public readonly ?string $gender,
        public readonly ?string $profilePhotoUrl,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly bool $termsAndConditions,
        public readonly ?int $registeredById,
        public readonly bool $twoFactorEnabled,
        public readonly array $permissions,
    ) {}

    public static function fromModel(User $user, StoragePort $storage): self
    {
        $profilePhotoUrl = null;

        $profilePhotoPath = array_key_exists('profile_photo_path', $user->getAttributes())
            ? $user->profile_photo_path
            : null;

        if ($profilePhotoPath !== null && $profilePhotoPath !== '') {
            try {
                $profilePhotoUrl = $storage->publicUrl($profilePhotoPath);
            } catch (Throwable) {
                // A storage outage must not turn the profile page into a 500;
                // the shell falls back to initials.
                $profilePhotoUrl = null;
            }
        }

        /** @var list<string> $permissions */
        $permissions = $user->getAllPermissions()
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
            gender: $user->gender,
            profilePhotoUrl: $profilePhotoUrl,
            latitude: $user->latitude !== null ? (float) $user->latitude : null,
            longitude: $user->longitude !== null ? (float) $user->longitude : null,
            termsAndConditions: (bool) $user->terms_and_conditions,
            registeredById: $user->registered_by_id,
            twoFactorEnabled: $user->hasEnabledTwoFactorAuthentication(),
            permissions: $permissions,
        );
    }
}
