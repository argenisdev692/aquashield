<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Actions;

use Modules\LeadAppointment\Application\Dtos\CaptureLeadData;
use Modules\LeadAppointment\Application\Dtos\LeadData;
use Modules\LeadAppointment\Application\Dtos\MetaLeadData;
use Modules\LeadAppointment\Application\Ports\LeadReader;
use Modules\LeadAppointment\Domain\Enums\LeadSource;
use Modules\LeadAppointment\Domain\Enums\Region;

/**
 * Maps Meta's normalised lead to the same CaptureLeadData the website uses, so
 * dedupe, spam scoring and notifications cannot diverge between sources.
 */
final readonly class IngestMetaLeadAction
{
    public function __construct(
        private CaptureLeadAction $capture,
        private LeadReader $leads,
    ) {}

    public function execute(MetaLeadData $meta): LeadData
    {
        $existing = $this->leads->findByExternalId('meta', $meta->externalId);

        if ($existing !== null) {
            return $existing;
        }

        $region = $this->regionFor($meta);

        $data = new CaptureLeadData(
            firstName: $meta->firstName,
            lastName: $meta->lastName,
            companyName: $meta->companyName,
            email: $meta->email,
            phone: $meta->phone,
            address: $meta->address,
            city: $meta->city,
            state: $meta->state,
            zipCode: $meta->zipCode,
            region: $region->value,
            damageDescription: $meta->message,
            preferredLanguage: 'en',
        );

        return $this->capture->execute(
            data: $data,
            source: LeadSource::Meta,
            sourceDetail: 'Meta Lead Ads',
            externalSource: 'meta',
            externalId: $meta->externalId,
        );
    }

    private function regionFor(MetaLeadData $meta): Region
    {
        $candidate = $meta->fieldData['region'] ?? $meta->fieldData['metro'] ?? null;

        if (is_string($candidate)) {
            $region = Region::tryFrom(mb_strtolower(trim($candidate)));

            if ($region !== null) {
                return $region;
            }
        }

        if ($meta->state !== null && strtoupper($meta->state) === 'FL') {
            return Region::Florida;
        }

        if ($meta->city !== null && str_contains(mb_strtolower($meta->city), 'dallas')) {
            return Region::Dallas;
        }

        return Region::Houston;
    }
}
