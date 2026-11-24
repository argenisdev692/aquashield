<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Modules\LeadAppointment\Domain\Enums\InspectionStatus;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The visitor's payload. `source`, `external_source` and `external_id` are NOT
 * here on purpose: the caller (website vs Meta) decides them server-side, so a
 * visitor cannot claim a source.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class CaptureLeadData extends Data
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $companyName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $address = null,
        #[MapInputName('address_2')]
        public ?string $address2 = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $zipCode = null,
        public ?string $region = null,
        public ?string $damageDescription = null,
        public bool $isEmergency = false,
        public bool $smsConsent = false,
        public ?string $consentTextVersion = null,
        public string $preferredLanguage = 'en',
        public ?string $sourcePage = null,
        public ?string $utmSource = null,
        public ?string $utmMedium = null,
        public ?string $utmCampaign = null,
        public ?string $utmContent = null,
        public ?string $utmTerm = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $ownerUuid = null,
        public ?string $notes = null,
        public bool $insuranceProperty = false,
        public ?string $message = null,
        public ?bool $intentToClaim = null,
        public ?string $followUpDate = null,
        public ?string $inspectionDate = null,
        public ?string $inspectionTime = null,
        public ?InspectionStatus $inspectionStatus = null,
        public ?string $additionalNote = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}
}
