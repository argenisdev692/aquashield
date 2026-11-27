<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Enums;

use Carbon\CarbonImmutable;
use Modules\LeadAppointment\Domain\ValueObjects\DateWindow;

/**
 * The dashboard's metric period. One enum owns both the current window and the
 * comparison window, so a KPI chip can never compare against a period the
 * cards did not count. `Last30Days` is the default the URL omits.
 */
enum DashboardRange: string
{
    case Last30Days = 'last_30_days';
    case ThisMonth = 'this_month';
    case PreviousMonth = 'previous_month';

    public function label(): string
    {
        return match ($this) {
            self::Last30Days => 'Last 30 days',
            self::ThisMonth => 'This month',
            self::PreviousMonth => 'Previous month',
        };
    }

    public function window(CarbonImmutable $now): DateWindow
    {
        return match ($this) {
            self::Last30Days => new DateWindow($now->subDays(29)->startOfDay(), $now->endOfDay()),
            self::ThisMonth => new DateWindow($now->startOfMonth(), $now->endOfMonth()),
            self::PreviousMonth => new DateWindow(
                $now->subMonthNoOverflow()->startOfMonth(),
                $now->subMonthNoOverflow()->endOfMonth(),
            ),
        };
    }

    /** The equal-length period immediately before the current window. */
    public function comparisonWindow(CarbonImmutable $now): DateWindow
    {
        return match ($this) {
            self::Last30Days => new DateWindow(
                $now->subDays(59)->startOfDay(),
                $now->subDays(30)->endOfDay(),
            ),
            self::ThisMonth => new DateWindow(
                $now->subMonthNoOverflow()->startOfMonth(),
                $now->subMonthNoOverflow()->endOfMonth(),
            ),
            self::PreviousMonth => new DateWindow(
                $now->subMonthsNoOverflow(2)->startOfMonth(),
                $now->subMonthsNoOverflow(2)->endOfMonth(),
            ),
        };
    }
}
