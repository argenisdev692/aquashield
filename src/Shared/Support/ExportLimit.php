<?php

declare(strict_types=1);

namespace Shared\Support;

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
}
