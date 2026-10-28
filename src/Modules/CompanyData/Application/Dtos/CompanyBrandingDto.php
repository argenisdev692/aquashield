<?php

declare(strict_types=1);

namespace Modules\CompanyData\Application\Dtos;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Public branding contract for the welcome page and external landing sites.
 *
 * This is deliberately NOT CompanyDataDto: bank details, tax ids, the owner
 * id and the signature path must never be reachable without authentication.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class CompanyBrandingDto extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $url,
        public readonly string $logoUrl,
        public readonly string $logoWhiteUrl,
        public readonly string $markUrl,
        public readonly ?string $address,
        #[MapInputName('address_2')]
        #[MapOutputName('address_2')]
        public readonly ?string $address2,
        public readonly ?string $zipCode,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $country,
        public readonly ?string $countryCode,
        public readonly ?string $phone,
        public readonly ?string $supportEmail,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly CompanySocialsDto $socials,
    ) {}
}
