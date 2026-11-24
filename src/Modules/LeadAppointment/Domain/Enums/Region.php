<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Enums;

/**
 * The three service metros. A fixed enum, not a table: expanding to a fourth
 * market is a business event that deserves a migration and a review, not a row
 * an operator can create by accident.
 */
enum Region: string
{
    case Houston = 'houston';
    case Dallas = 'dallas';
    case Florida = 'florida';

    public function label(): string
    {
        return match ($this) {
            self::Houston => 'Houston',
            self::Dallas => 'Dallas',
            self::Florida => 'Florida',
        };
    }

    public function state(): string
    {
        return match ($this) {
            self::Houston, self::Dallas => 'TX',
            self::Florida => 'FL',
        };
    }

    /**
     * IANA timezone used to render availability and appointments. Florida's
     * panhandle is America/Chicago; an appointment stores its own timezone so
     * that edge case is explicit rather than silently wrong.
     */
    public function timezone(): string
    {
        return match ($this) {
            self::Houston, self::Dallas => 'America/Chicago',
            self::Florida => 'America/New_York',
        };
    }
}
