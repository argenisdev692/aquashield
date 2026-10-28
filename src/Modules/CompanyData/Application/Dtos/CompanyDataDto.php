<?php

declare(strict_types=1);

namespace Modules\CompanyData\Application\Dtos;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Response contract for the singleton. It is intentionally an allowlist: no
 * `id`, no `user_id` and no `signature_path`. `typescript:transform` reads this
 * class, so every field added here becomes part of the frontend contract.
 *
 * Do not redeclare `from()`: Spatie Data already provides
 * `from(mixed ...$payloads)`, and a narrower parameter would be a PHP fatal.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class CompanyDataDto extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly ?string $name,
        public readonly string $companyName,
        public readonly ?string $email,
        public readonly ?string $description = null,
        public readonly ?string $phone = null,
        public readonly ?string $nifNipc = null,
        public readonly ?string $nie = null,
        public readonly ?string $bankBeneficiary = null,
        public readonly ?string $bankIban = null,
        public readonly ?string $bankBic = null,
        public readonly ?string $bankName = null,
        public readonly ?string $invoiceNotes = null,
        public readonly ?string $address = null,
        #[MapInputName('address_2')]
        #[MapOutputName('address_2')]
        public readonly ?string $address2 = null,
        public readonly ?string $zipCode = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
        public readonly ?string $country = null,
        public readonly ?string $countryCode = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?string $website = null,
        public readonly ?string $facebookLink = null,
        public readonly ?string $instagramLink = null,
        public readonly ?string $linkedinLink = null,
        public readonly ?string $twitterLink = null,
        public readonly ?string $tiktokLink = null,
        public readonly ?string $logoPath = null,
        public readonly ?string $logoWhitePath = null,
        public readonly ?string $markPath = null,
    ) {}
}
