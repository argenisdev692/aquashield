<?php

declare(strict_types=1);

namespace Modules\Users\Exports;

use Modules\Users\Data\UserFilterData;
use Modules\Users\Queries\UserListQuery;
use Shared\Domain\Ports\ExportPort;
use Shared\Support\SpreadsheetCell;

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
     *
     * Applies to the streamed formats (CSV/XLSX). PDF has its own, lower cap:
     * dompdf renders the entire table in the request.
     */
    public const int MAX_ROWS = 10_000;

    /**
     * PDF is rendered in-request by dompdf, which materialises the whole table
     * plus its object graph. 500 rows keeps the render inside a normal
     * `memory_limit`; a larger report is a CSV/XLSX download or a queued export.
     */
    public const int MAX_PDF_ROWS = 500;

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
            'status' => 'Account status',
            'record_state' => 'Record state',
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
                'first_name' => SpreadsheetCell::sanitise($user->first_name),
                'last_name' => SpreadsheetCell::sanitise($user->last_name),
                'username' => SpreadsheetCell::sanitise($user->username),
                'email' => SpreadsheetCell::sanitise($user->email),
                'phone' => SpreadsheetCell::sanitise($user->phone),
                'city' => SpreadsheetCell::sanitise($user->city),
                'state' => SpreadsheetCell::sanitise($user->state),
                'country' => SpreadsheetCell::sanitise($user->country),
                'roles' => SpreadsheetCell::sanitise(
                    $user->roles->pluck('name')->implode(', '),
                ),
                'status' => SpreadsheetCell::sanitise($user->status->label()),
                'record_state' => SpreadsheetCell::sanitise($user->deleted_at === null ? 'Active' : 'Deleted'),
                'created_at' => SpreadsheetCell::sanitise($user->created_at),
            ];
        }
    }
}
