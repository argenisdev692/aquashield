<?php

declare(strict_types=1);

namespace Modules\CompanyData\Application\Dtos;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Input contract for the singleton update. Validation lives in the FormRequest;
 * this object is the typed carrier the Action receives, and its `toArray()`
 * form is the explicit fill allowlist.
 *
 * Storage keys (logo/signature paths) are deliberately absent: they belong to a
 * validated upload action, not to a generic profile replace.
 */
#[MapInputName(SnakeCaseMapper::class)]
#[MapOutputName(SnakeCaseMapper::class)]
final class SaveCompanyDataData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $companyName,
        public readonly string $email,
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
    ) {}
}
