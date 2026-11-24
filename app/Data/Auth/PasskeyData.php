<?php

declare(strict_types=1);

namespace App\Data\Auth;

use Laravel\Passkeys\Passkey;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Allowlisted passkey payload for the account screen.
 *
 * Never send the model: `credential` holds the public key and the AAGUID, which
 * the browser has no use for. The authenticator label is resolved by the
 * package's accessor and is safe to show.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PasskeyData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $authenticator,
        public readonly ?string $createdAt,
        public readonly ?string $lastUsedAt,
    ) {}

    public static function fromModel(Passkey $passkey): self
    {
        return new self(
            id: (int) $passkey->getKey(),
            name: (string) $passkey->name,
            authenticator: $passkey->authenticator,
            createdAt: $passkey->created_at?->toIso8601String(),
            lastUsedAt: $passkey->last_used_at?->toIso8601String(),
        );
    }
}
