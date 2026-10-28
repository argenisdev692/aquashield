<?php

declare(strict_types=1);

namespace App\Data\Auth;

use Illuminate\Http\Request;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Symfony\Component\HttpFoundation\Response;

#[MapOutputName(SnakeCaseMapper::class)]
final class AvatarData extends Data
{
    public function __construct(
        public readonly ?string $avatarUrl,
    ) {}

    protected function calculateResponseStatus(Request $request): int
    {
        return Response::HTTP_OK;
    }
}
