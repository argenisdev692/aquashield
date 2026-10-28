<?php

declare(strict_types=1);

namespace Modules\Users\Exports;

use DateTimeInterface;
use Modules\Users\Data\UserFilterData;
use Modules\Users\Queries\UserListQuery;
use Shared\Domain\Ports\ExportPort;

/**
 * Row -> column transformation for the Users export.
 *
 * Deliberately NOT a response builder: streaming, driver selection and PDF
 * rendering live behind {@see ExportPort}. The query comes
 * from the same UserListQuery as the index, so a filter can never be applied to
 * the screen but forgotten in the file.
 */
final readonly class UserExportTransformer
{
    /**
     * A filtered export larger than this is a report, not a table download.
     * The controller refuses before streaming so the user can narrow the
     * filters instead of receiving a truncated file that looks complete.
     */
    public const int MAX_ROWS = 10_000;

    public function __construct(
        private UserFilterData $filters,
        private UserListQuery $users,
    ) {}

    /**
     * Machine column name => human header label.
     *
     * @return array<string, string>
     */
    public function columns(): array
    {
        return [
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'username' => 'Username',
            'email' => 'Email',
            'phone' => 'Phone',
            'city' => 'City',
            'state' => 'State',
            'country' => 'Country',
            'roles' => 'Roles',
            'status' => 'Status',
            'created_at' => 'Created at',
        ];
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return array_values($this->columns());
    }

    /**
     * Lazy row stream; `lazy(500)` keeps memory flat while still eager-loading
     * roles per chunk (a bare cursor would lazy-load them one row at a time).
     *
     * @return iterable<int, array<string, string|int|float>>
     */
    public function rows(): iterable
    {
        $query = $this->users
            ->builder($this->filters)
            ->with('roles:id,name');

        foreach ($query->lazy(500) as $user) {
            yield [
                'first_name' => $this->cell($user->first_name),
                'last_name' => $this->cell($user->last_name),
                'username' => $this->cell($user->username),
                'email' => $this->cell($user->email),
                'phone' => $this->cell($user->phone),
                'city' => $this->cell($user->city),
                'state' => $this->cell($user->state),
                'country' => $this->cell($user->country),
                'roles' => $this->cell(
                    $user->roles->pluck('name')->implode(', '),
                ),
                'status' => $this->cell($user->deleted_at === null ? 'Active' : 'Trashed'),
                'created_at' => $this->cell($user->created_at),
            ];
        }
    }

    /**
     * One export-safe cell.
     *
     * The pipe normalises a Carbon to a string, a bool to 0/1 and null to an
     * empty cell, then neutralises spreadsheet formula prefixes. Names and
     * notes are user input; a cell beginning with `=`, `+`, `-` or `@` is
     * executable in Excel and Sheets.
     */
    private function cell(mixed $value): string|int|float
    {
        return $value
            |> (fn (mixed $v): mixed => $v instanceof DateTimeInterface ? $v->format('Y-m-d H:i') : $v)
            |> (fn (mixed $v): mixed => is_bool($v) ? (int) $v : $v)
            |> (fn (mixed $v): int|float|string => $v ?? '')
            |> (fn (int|float|string $v): int|float|string => is_string($v) && preg_match('/^[=+\-@]/', $v) === 1 ? "'".$v : $v);
    }
}
