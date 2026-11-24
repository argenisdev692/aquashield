<?php

declare(strict_types=1);

namespace Shared\Support;

use DateTimeInterface;

/**
 * One definition of the spreadsheet-cell contract.
 *
 * Normalises a date to `Y-m-d H:i`, a bool to 0/1 and null to an empty cell,
 * then neutralises the formula prefixes Excel and Sheets execute (`=`, `+`,
 * `-`, `@`, tab, carriage return). Every export transformer calls this; a
 * divergent copy is a formula-injection vulnerability, not a style choice.
 */
final class SpreadsheetCell
{
    public static function sanitise(mixed $value): string|int|float
    {
        return $value
            |> (fn (mixed $v): mixed => $v instanceof DateTimeInterface ? $v->format('Y-m-d H:i') : $v)
            |> (fn (mixed $v): mixed => is_bool($v) ? (int) $v : $v)
            |> (fn (mixed $v): int|float|string => $v ?? '')
            |> (fn (int|float|string $v): int|float|string => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) === 1 ? "'".$v : $v);
    }
}
