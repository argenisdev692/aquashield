<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Enums;

/**
 * Sales pipeline stage. The transition map is the single source of truth; a
 * controller may not move a lead to a stage the map forbids.
 */
enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case InspectionScheduled = 'inspection_scheduled';
    case Won = 'won';
    case Lost = 'lost';
    case Nurture = 'nurture';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::InspectionScheduled => 'Inspection scheduled',
            self::Won => 'Won',
            self::Lost => 'Lost',
            self::Nurture => 'Nurture',
        };
    }

    /**
     * Legacy `status_lead` vocabulary from the old appointments table. `Called`
     * is the operator's "already called" state; `Pending` is a follow-up, not a
     * scheduled inspection.
     */
    public static function fromLegacy(string $value): self
    {
        return match (mb_strtolower(trim($value))) {
            'new' => self::New,
            'called' => self::Contacted,
            'pending' => self::Nurture,
            'declined' => self::Lost,
            default => self::from(mb_strtolower(trim($value))),
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Won;
    }

    /**
     * The allowed next stages per stage, in the shape the board and detail
     * screen consume. One source for both controllers.
     *
     * @return array<string, list<string>>
     */
    public static function transitionMap(): array
    {
        $map = [];

        foreach (self::cases() as $from) {
            $map[$from->value] = array_values(array_map(
                static fn (self $to): string => $to->value,
                array_filter(
                    self::cases(),
                    static fn (self $to): bool => $from->canTransitionTo($to),
                ),
            ));
        }

        return $map;
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return false;
        }

        return in_array($next, match ($this) {
            self::New => [self::Contacted, self::Qualified, self::InspectionScheduled, self::Lost, self::Nurture],
            self::Contacted => [self::Qualified, self::InspectionScheduled, self::Lost, self::Nurture],
            self::Qualified => [self::InspectionScheduled, self::Lost, self::Nurture],
            self::InspectionScheduled => [self::Won, self::Lost, self::Nurture],
            self::Won => [],
            self::Lost => [self::Nurture, self::Contacted],
            self::Nurture => [self::Contacted, self::Qualified, self::Lost],
        }, true);
    }
}
