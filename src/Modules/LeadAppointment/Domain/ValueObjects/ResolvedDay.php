<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\ValueObjects;

/**
 * The effective availability of one date after the precedence chain has run:
 * company exception > company rule > closed. `source` says which rung won, so
 * the UI can explain a closed day. Technician-scoped rules return with the
 * Claim + Scope Sheet assignment phase.
 */
final readonly class ResolvedDay
{
    /**
     * @param list<TimeWindow> $windows
     */
    public function __construct(
        public string $date,
        public bool $isOpen,
        public array $windows,
        public string $source,
        public string $timezone,
        public ?string $reason = null,
    ) {}

    /**
     * @param list<TimeWindow> $windows
     */
    public static function open(string $date, array $windows, string $source, string $timezone, ?string $reason = null): self
    {
        return new self($date, true, $windows, $source, $timezone, $reason);
    }

    public static function closed(string $date, string $source, string $timezone, ?string $reason = null): self
    {
        return new self($date, false, [], $source, $timezone, $reason);
    }

    public function containsTime(string $time): bool
    {
        if (! $this->isOpen) {
            return false;
        }

        foreach ($this->windows as $window) {
            if ($window->contains($time)) {
                return true;
            }
        }

        return false;
    }
}
