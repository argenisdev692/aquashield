<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Enums;

enum LeadSource: string
{
    case Website = 'website';
    case Meta = 'meta';
    case Phone = 'phone';
    case Referral = 'referral';
    case Manual = 'manual';
    case RetellAi = 'retell_ai';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::Meta => 'Meta Ads',
            self::Phone => 'Phone',
            self::Referral => 'Referral',
            self::Manual => 'Manual',
            self::RetellAi => 'Retell AI',
            self::Other => 'Other',
        };
    }
}
