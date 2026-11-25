<?php

declare(strict_types=1);

namespace Shared\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * One definition of the user-facing search predicate.
 *
 * The table search is a substring match on a name column. Two properties have
 * to hold on every supported engine:
 *
 * 1. It is case-insensitive. PostgreSQL's `LIKE` is case-sensitive, so a plain
 *    `like` silently returns nothing for lowercase input. `ILIKE` is the
 *    PostgreSQL spelling; MySQL's default collation and SQLite's ASCII `LIKE`
 *    are already case-insensitive.
 * 2. `%` and `_` are literals in the user's term. Permission names are full of
 *    underscores, so treating `_` as a single-character wildcard makes the
 *    search lie; `%` would widen it to every row.
 *
 * The value remains a bound parameter; only the operator and the column are
 * code constants, so this is not a raw-SQL injection path.
 */
final class SearchPattern
{
    public static function escape(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    /**
     * @param Builder<*> $query
     */
    public static function apply(Builder $query, string $column, string $term): void
    {
        $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql'
            ? 'ilike'
            : 'like';

        $wrapped = $query->getQuery()->getGrammar()->wrap($column);

        $query->whereRaw(
            $wrapped.' '.$operator.' ? escape ?',
            ['%'.self::escape($term).'%', '\\'],
        );
    }
}
