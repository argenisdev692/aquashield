<?php

declare(strict_types=1);

namespace Modules\AccessControl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\AccessControl\Data\PermissionFilterData;
use Modules\AccessControl\Exports\ExportLimit;
use Modules\AccessControl\Exports\PermissionExportTransformer;
use Modules\AccessControl\Queries\PermissionListQuery;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class PermissionExportController
{
    public function __construct(
        private ExportPort $exporter,
        private PermissionListQuery $permissions,
        private AuditPort $audit,
    ) {}

    public function __invoke(Request $request, string $format): StreamedResponse|Response
    {
        $filters = PermissionFilterData::fromRequest($request);
        $rowCount = $this->permissions->count($filters);
        $limit = ExportLimit::for($format);

        if ($rowCount > $limit) {
            abort(422, 'The '.$format.' export is limited to '.$limit.' rows. Narrow the filters.');
        }

        $this->audit->log('permission.exported', null, [
            'format' => $format,
            'rows' => $rowCount,
            'limit' => $limit,
            'filters' => [
                'type' => $filters->type,
                'group' => $filters->group,
                'role' => $filters->role,
                'date_from' => $filters->dateFrom,
                'date_to' => $filters->dateTo,
                'has_search' => $filters->search !== null && $filters->search !== '',
            ],
        ], $request->user(), 'access-control');

        $transformer = new PermissionExportTransformer($filters, $this->permissions);
        $filename = 'permissions.'.$format;

        if ($format === 'pdf') {
            return $this->exporter->pdf($filename, 'exports.permissions', [
                'title' => 'Permissions export',
                'columns' => $transformer->columns(),
                'rows' => $transformer->rows(),
            ]);
        }

        return $this->exporter->tabular($filename, $transformer->headers(), $transformer->rows());
    }
}
