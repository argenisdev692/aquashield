<?php

declare(strict_types=1);

namespace App\Data\Auth;

use Illuminate\Http\Request;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Symfony\Component\HttpFoundation\Response;

#[MapOutputName(SnakeCaseMapper::class)]
final class AuthTokenData extends Data
{
    public function __construct(
        public readonly string $token,
        public readonly string $tokenType,
        public readonly string $expiresAt,
        public readonly UserData $user,
    ) {}

    /**
     * Login and refresh answer 200, not Spatie Data's POST default 201: the
     * response is a credential envelope, not a newly addressable resource.
     */
    protected function calculateResponseStatus(Request $request): int
    {
        return Response::HTTP_OK;
    }
}
