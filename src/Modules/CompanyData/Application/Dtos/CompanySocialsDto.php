<?php

declare(strict_types=1);

namespace Modules\CompanyData\Application\Dtos;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Typed social links. A plain `array<string,string>` was documented by Scramble
 * as a list of strings, while PHP encodes it as an object — this class keeps the
 * OpenAPI schema and the JSON shape identical.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class CompanySocialsDto extends Data
{
    public function __construct(
        public readonly ?string $linkedin = null,
        public readonly ?string $twitter = null,
        public readonly ?string $instagram = null,
        public readonly ?string $facebook = null,
        public readonly ?string $tiktok = null,
        public readonly ?string $github = null,
    ) {}
}
