<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Enums;

enum AppointmentKind: string
{
    case Inspection = 'inspection';
    case Estimate = 'estimate';
    case AdjusterMeeting = 'adjuster_meeting';
    case JobVisit = 'job_visit';
    case FollowUp = 'follow_up';

    public function label(): string
    {
        return match ($this) {
            self::Inspection => 'Roof inspection',
            self::Estimate => 'Estimate',
            self::AdjusterMeeting => 'Adjuster meeting',
            self::JobVisit => 'Job visit',
            self::FollowUp => 'Follow-up',
        };
    }
}
