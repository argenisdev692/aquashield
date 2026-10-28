<?php

declare(strict_types=1);

namespace Modules\CompanyData\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Resolved brand-asset URLs for the edit screen.
 *
 * Deliberately separate from CompanyDataDto: that class is the JSON contract
 * and carries storage keys. This one is what a browser can actually render —
 * a public URL for each logo and a signed, expiring URL for the signature —
 * so no object key and no signature path reaches the client.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CompanyAssetsDto extends Data
{
    public function __construct(
        public readonly ?string $logoUrl,
        public readonly ?string $logoWhiteUrl,
        public readonly ?string $markUrl,
        public readonly ?string $signatureUrl,
    ) {}
}
