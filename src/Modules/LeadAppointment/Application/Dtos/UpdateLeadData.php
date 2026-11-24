<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Modules\LeadAppointment\Domain\Enums\InspectionStatus;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class UpdateLeadData extends Data
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
        public bool $isEmergency = false,
        public string $preferredLanguage = 'en',
        public ?string $damageDescription = null,
        public ?string $insuranceCarrier = null,
        public ?string $claimNumber = null,
        public ?string $notes = null,
        public ?string $ownerUuid = null,
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
