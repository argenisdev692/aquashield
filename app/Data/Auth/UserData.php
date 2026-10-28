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
 * Allowlisted user shape for the Inertia shell and the Sanctum API.
 *
 * Never return the Eloquent model directly: `password`, `remember_token`,
 * `google_id`, 2FA secrets and avatar object keys must not cross the boundary.
 * Permissions — never roles — are what the frontend uses to hide UI.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class UserData extends Data
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $avatarUrl,
        public readonly bool $twoFactorEnabled,
        public readonly array $permissions,
    ) {}

    public static function fromModel(User $user, StoragePort $storage): self
    {
        $avatarUrl = null;

        if ($user->avatar_path !== null && $user->avatar_path !== '') {
            try {
                $avatarUrl = $storage->publicUrl($user->avatar_path);
            } catch (Throwable) {
                // A storage outage must not turn the profile page into a 500;
                // the shell falls back to initials.
                $avatarUrl = null;
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
            name: (string) $user->name,
            email: (string) $user->email,
            avatarUrl: $avatarUrl,
            twoFactorEnabled: $user->hasEnabledTwoFactorAuthentication(),
            permissions: $permissions,
        );
    }
}
