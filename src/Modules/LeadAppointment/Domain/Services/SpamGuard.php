<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Services;

use Modules\LeadAppointment\Domain\ValueObjects\SpamAssessment;

/**
 * Framework-free content scorer. This is the second line of defence after the
 * honeypot: it FLAGS a lead for review, never rejects it. Near-zero false
 * positives is the design goal; the threshold lives in config.
 */
final readonly class SpamGuard
{
    /**
     * @param array<string, int> $keywordWeights lowercase keyword => weight
     */
    public function __construct(
        private array $keywordWeights,
        private int $threshold,
        private int $maxLinks,
    ) {}

    public function assess(string $subject, string $message): SpamAssessment
    {
        $haystack = mb_strtolower(trim($subject.' '.$message));
        $reasons = [];
        $score = 0;

        foreach ($this->keywordWeights as $keyword => $weight) {
            if ($this->containsKeyword($haystack, (string) $keyword)) {
                $score += $weight;
                $reasons[] = 'keyword:'.$keyword;
            }
        }

        $links = $this->countLinks($haystack);

        if ($links > $this->maxLinks) {
            $score += ($links - $this->maxLinks) * 2;
            $reasons[] = 'links:'.$links;
        }

        if ($this->isShouting($message)) {
            $score += 2;
            $reasons[] = 'all_caps';
        }

        if ($this->hasCharacterFlooding($message)) {
            $score += 2;
            $reasons[] = 'char_flood';
        }

        return new SpamAssessment(
            score: $score,
            isSpam: $score >= $this->threshold,
            reasons: $reasons,
        );
    }

    private function containsKeyword(string $haystack, string $keyword): bool
    {
        if (str_contains($keyword, ' ')) {
            return str_contains($haystack, $keyword);
        }

        return preg_match('/\b'.preg_quote($keyword, '/').'\b/u', $haystack) === 1;
    }

    private function countLinks(string $haystack): int
    {
        return (int) preg_match_all('#(https?://|www\.)#i', $haystack);
    }

    private function isShouting(string $message): bool
    {
        $letters = (string) preg_replace('/[^a-z]/i', '', $message);

        if (mb_strlen($letters) < 20) {
            return false;
        }

        $upper = (string) preg_replace('/[^A-Z]/', '', $message);

        return mb_strlen($upper) / mb_strlen($letters) > 0.7;
    }

    private function hasCharacterFlooding(string $message): bool
    {
        return preg_match('/(.)\1{9,}/u', $message) === 1;
    }
}
