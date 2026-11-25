<?php

declare(strict_types=1);

namespace Shared\Support;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Per-format export ceilings.
 *
 * CSV/XLSX stream through `simple-excel`, so 10 000 rows stay memory-flat.
 * PDF is rendered in-request by dompdf, where 10 000 rows is an availability
 * risk; it gets a much lower ceiling until the queued export lands.
 */
final class ExportLimit
{
    public const int TABULAR = 10_000;

    public const int PDF = 2_000;

    public static function for(string $format): int
    {
        return $format === 'pdf' ? self::PDF : self::TABULAR;
    }

    /**
     * Return the format ceiling or abort with a 422 before any row is streamed.
     * Keeping the decision here makes the cap testable without a ten-thousand
     * row fixture.
     */
    public static function ensureWithin(int $rows, string $format): int
    {
        $limit = self::for($format);

        if ($rows > $limit) {
            throw new HttpException(
                422,
                'The '.$format.' export is limited to '.$limit.' rows. Narrow the filters.',
            );
        }

        return $limit;
    }
}
