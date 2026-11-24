<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\LeadAppointment\Domain\Exceptions\CapacityExceededException;
use Modules\LeadAppointment\Domain\Exceptions\PastDateSchedulingException;
use Modules\LeadAppointment\Domain\Exceptions\SlotUnavailableException;
use Modules\LeadAppointment\Domain\ValueObjects\BookingIntent;
use Modules\LeadAppointment\Domain\ValueObjects\ResolvedDay;
use Modules\LeadAppointment\Domain\ValueObjects\TimeInterval;
use Modules\LeadAppointment\Domain\ValueObjects\TimeWindow;

/**
 * The scheduling invariant chain, with no repository or framework:
 * not in the past, inside one open window (unless emergency), one inspection
 * per day for the single crew, and no overlap with the travel buffer that
 * follows every visit.
 *
 * A 7 h inspection + 1 h travel occupies 8 h of a 9 h window. A 16:00 start
 * therefore ends at 23:00 and is rejected by the window check before capacity
 * is even considered.
 */
final readonly class BookingGuard
{
    /**
     * @param list<TimeInterval> $existing raw [starts_at, ends_at] intervals for the day
     */
    public function assertBookable(
        BookingIntent $intent,
        ResolvedDay $day,
        array $existing,
        int $dailyCount,
        ?CarbonImmutable $now = null,
    ): void {
        $now ??= CarbonImmutable::now();

        if ($intent->startsAt->lessThan($now)) {
            throw new PastDateSchedulingException;
        }

        if ($intent->endsAt->lessThanOrEqualTo($intent->startsAt)) {
            throw SlotUnavailableException::outsideWindow();
        }

        if (! $intent->isEmergency) {
            if (! $day->isOpen) {
                throw SlotUnavailableException::closed($day->reason ?? 'closed');
            }

            $localStart = $intent->startsAt->setTimezone($day->timezone);
            $localEnd = $intent->endsAt->setTimezone($day->timezone);

            if (! $this->fitsOneWindow($day->windows, $localStart->format('H:i'), $localEnd->format('H:i'))) {
                throw SlotUnavailableException::outsideWindow();
            }

            if ($dailyCount >= $intent->dailyCapacity) {
                throw new CapacityExceededException;
            }
        }

        $candidate = new TimeInterval(
            $intent->startsAt,
            $intent->endsAt->addMinutes($intent->travelBufferMinutes),
        );

        foreach ($existing as $interval) {
            $blocked = new TimeInterval(
                $interval->start,
                $interval->end->addMinutes($intent->travelBufferMinutes),
            );

            if ($candidate->overlaps($blocked)) {
                throw SlotUnavailableException::overlap();
            }
        }
    }

    /**
     * A booking must fit inside ONE window; it cannot span a lunch break.
     *
     * @param list<TimeWindow> $windows
     */
    private function fitsOneWindow(array $windows, string $start, string $end): bool
    {
        foreach ($windows as $window) {
            if ($start >= $window->start && $end <= $window->end) {
                return true;
            }
        }

        return false;
    }
}
