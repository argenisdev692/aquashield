<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Admin/API lead payload. Built by the repository from the Eloquent model; no
 * persistence type crosses the Application boundary.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class LeadData extends Data
{
    /**
     * @param list<string> $spamReasons
     * @param array<string, string>|null $attribution
     */
    public function __construct(
        public string $uuid,
        public string $firstName,
        public string $lastName,
        public string $fullName,
        public ?string $companyName,
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public ?string $address2,
        public ?string $city,
        public ?string $state,
        public ?string $zipCode,
        public string $addressLine,
        public ?string $region,
        public string $source,
        public ?string $sourceDetail,
        public string $status,
        public ?string $ownerUuid,
        public ?string $ownerName,
        public ?string $duplicateOfUuid,
        public bool $isEmergency,
        public bool $isSpam,
        public int $spamScore,
        public array $spamReasons,
        public string $preferredLanguage,
        public ?string $damageDescription,
        public ?string $insuranceCarrier,
        public ?string $claimNumber,
        public bool $smsConsent,
        public ?string $smsConsentAt,
        public ?string $lastContactedAt,
        public ?string $convertedAt,
        public ?string $notes,
        public ?array $attribution,
        public ?string $createdAt,
        public ?string $updatedAt,
        public ?string $deletedAt,
        public bool $insuranceProperty = false,
        public ?string $message = null,
        public ?bool $intentToClaim = null,
        public ?string $followUpDate = null,
        public ?string $additionalNote = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $registrationDate = null,
        public ?string $inspectionDate = null,
        public ?string $inspectionTime = null,
        public ?string $inspectionStatus = null,
    ) {}
}
