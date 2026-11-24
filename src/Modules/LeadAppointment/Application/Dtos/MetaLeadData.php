<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Data;

/**
 * Normalised Graph API lead. `fieldData` keeps the raw `name => value` map so a
 * custom question is not lost when Meta changes a form; the mapper reads the
 * standard keys and falls back to the map for address fields.
 */
final class MetaLeadData extends Data
{
    /**
     * @param array<string, string> $fieldData
     */
    public function __construct(
        public string $externalId,
        public string $firstName,
        public string $lastName,
        public ?string $email,
        public ?string $phone,
        public ?string $companyName,
        public ?string $address,
        public ?string $city,
        public ?string $state,
        public ?string $zipCode,
        public ?string $message,
        public ?string $campaignId,
        public ?string $adsetId,
        public ?string $adId,
        public ?string $formId,
        public ?string $pageId,
        public array $fieldData,
    ) {}
}
