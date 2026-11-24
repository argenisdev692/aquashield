<?php

declare(strict_types=1);

namespace Modules\AccessControl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\AccessControl\Data\RoleFilterData;
use Modules\AccessControl\Exports\ExportLimit;
use Modules\AccessControl\Exports\RoleExportTransformer;
use Modules\AccessControl\Queries\RoleListQuery;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class RoleExportController
{
    public function __construct(
        private ExportPort $exporter,
        private RoleListQuery $roles,
        private AuditPort $audit,
    ) {}

    public function __invoke(Request $request, string $format): StreamedResponse|Response
    {
        $filters = RoleFilterData::fromRequest($request);
        $rowCount = $this->roles->count($filters);
        $limit = ExportLimit::for($format);

        if ($rowCount > $limit) {
            abort(422, 'The '.$format.' export is limited to '.$limit.' rows. Narrow the filters.');
        }

        $this->audit->log('role.exported', null, [
            'format' => $format,
            'rows' => $rowCount,
            'limit' => $limit,
            'filters' => [
                'type' => $filters->type,
                'permission' => $filters->permission,
                'date_from' => $filters->dateFrom,
                'date_to' => $filters->dateTo,
                'has_search' => $filters->search !== null && $filters->search !== '',
            ],
        ], $request->user(), 'access-control');

        $transformer = new RoleExportTransformer($filters, $this->roles);
        $filename = 'roles.'.$format;

        if ($format === 'pdf') {
            return $this->exporter->pdf($filename, 'exports.roles', [
                'title' => 'Roles export',
                'columns' => $transformer->columns(),
                'rows' => $transformer->rows(),
            ]);
        }

        return $this->exporter->tabular($filename, $transformer->headers(), $transformer->rows());
    }
}
