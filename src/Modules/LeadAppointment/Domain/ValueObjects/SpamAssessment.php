<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\ValueObjects;

final readonly class SpamAssessment
{
    /**
     * @param list<string> $reasons
     */
    public function __construct(
        public int $score,
        public bool $isSpam,
        public array $reasons,
    ) {}

    public function merge(self $other): self
    {
        return new self(
            score: $this->score + $other->score,
            isSpam: $this->isSpam || $other->isSpam,
            reasons: array_values(array_unique([...$this->reasons, ...$other->reasons])),
        );
    }
}
