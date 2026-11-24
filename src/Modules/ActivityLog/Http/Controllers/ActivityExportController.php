<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\ActivityLog\Data\ActivityFilterData;
use Modules\ActivityLog\Exports\ActivityExportTransformer;
use Modules\ActivityLog\Queries\ActivityListQuery;
use Shared\Domain\Ports\AuditPort;
use Shared\Domain\Ports\ExportPort;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ActivityExportController
{
    public function __construct(
        private ExportPort $exporter,
        private ActivityListQuery $activity,
        private AuditPort $audit,
    ) {}

    public function __invoke(Request $request, string $format): StreamedResponse|Response
    {
        $filters = ActivityFilterData::fromRequest($request);
        $rowCount = $this->activity->count($filters);

        if ($rowCount > ActivityExportTransformer::MAX_ROWS) {
            abort(422, 'The export is limited to '.ActivityExportTransformer::MAX_ROWS.' rows. Narrow the filters.');
        }

        // Audit-worthy egress (OWASP A09). No search term, no PII: the filter
        // summary is deliberately non-identifying.
        $this->audit->log('activity.exported', null, [
            'format' => $format,
            'rows' => $rowCount,
            'filters' => [
                'log_name' => $filters->logName,
                'event' => $filters->event,
                'date_from' => $filters->dateFrom,
                'date_to' => $filters->dateTo,
                'has_search' => $filters->search !== null && $filters->search !== '',
            ],
        ], $request->user(), 'activity');

        $transformer = new ActivityExportTransformer($filters, $this->activity);
        $filename = 'activity-log.'.$format;

        if ($format === 'pdf') {
            return $this->exporter->pdf($filename, 'exports.activity-log', [
                'title' => 'Activity log',
                'columns' => $transformer->columns(),
                'rows' => $transformer->rows(),
            ]);
        }

        return $this->exporter->tabular($filename, $transformer->headers(), $transformer->rows());
    }
}
